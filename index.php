<?php
declare(strict_types=1);

use App\Ask;
use App\Display;
use App\Exceptions\NoMoneyException;
use App\Models\User;
use App\Models\Wallet;
use App\Repositories\TransactionRepository;
use App\Repositories\UserRepository;
use App\Repositories\WalletRepository;
use App\Services\BuyService;
use App\Services\SellService;
use App\Services\Cryptocurrency\CoinMarketCapApiService;
use App\Services\WalletService;
use Brick\Math\BigDecimal;
use Brick\Money\CurrencyConverter;
use Brick\Money\ExchangeRateProvider\BaseCurrencyProvider;
use Brick\Money\ExchangeRateProvider\ConfigurableProvider;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Table;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\ConsoleOutput;

require_once "vendor/autoload.php";

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$connectionParams = [
    "driver" => "pdo_sqlite",
    "path" => "storage/database.sqlite",
];

$connection = DriverManager::getConnection($connectionParams);

$schemaManager = $connection->createSchemaManager();
if (!$schemaManager->tablesExist(["users"])) {
    $table = new Table("users");
    $table->addColumn("user_id", "string");
    $table->setPrimaryKey(["user_id"]);

    $table->addColumn("username", "string");
    $table->addColumn("password", "string");
    $schemaManager->createTable($table);

    (new UserRepository($connection))->insert(
        new User("Person", "password123", "funnyUser")
    );
}

if (!$schemaManager->tablesExist(["transactions"])) {
    $table = new Table("transactions");
    $table->addColumn("transaction_id", "string");
    $table->setPrimaryKey(["transaction_id"]);
    $table->addColumn("user_id", "string");
    $table->addForeignKeyConstraint("users", ["user_id"], ["user_id"]);

    $table->addColumn("sent_amount", "decimal");
    $table->addColumn("sent_ticker", "string");
    $table->addColumn("type", "string");
    $table->addColumn("received_amount", "decimal");
    $table->addColumn("received_ticker", "string");
    $table->addColumn("created_at", "string");
    $schemaManager->createTable($table);
}

if (!$schemaManager->tablesExist(["wallet"])) {
    $table = new Table("wallet");
    $table->addColumn("wallet_id", "string");
//    $table->setPrimaryKey(["wallet_id"]);
    $table->addColumn("user_id", "string");
    $table->addForeignKeyConstraint("users", ["user_id"], ["user_id"]);

    $table->addColumn("ticker", "string");
    $table->addColumn("amount", "decimal");
    $schemaManager->createTable($table);
    $connection->insert("wallet", [
        "wallet_id" => "funnyWallet",
        "user_id" => "funnyUser",
        "ticker" => "EUR",
        "amount" => BigDecimal::of(1000),
    ]);
}

$cryptoApi = new CoinMarketCapApiService($_ENV["COIN_MARKET_CAP_API_KEY"]);
//$cryptoApi = new CoinGeckoApiService($_ENV["COIN_GECKO_API_KEY"]);

$consoleInput = new ArrayInput([]);
$consoleOutput = new ConsoleOutput();
$ask = new Ask($consoleInput, $consoleOutput);


$provider = new ConfigurableProvider();
$transactionRepository = new TransactionRepository($connection);


$baseProvider = new BaseCurrencyProvider($provider, "EUR");
$currencyConverter = new CurrencyConverter($baseProvider);

$walletRepository = new WalletRepository($connection);
$userRepository = new UserRepository($connection);
$walletService = new WalletService(new WalletRepository($connection));
$wallet = new Wallet("funnyUser", "funnyWallet");

$display = new Display($consoleOutput);
while (true) {
    $mainAction = $ask->mainAction();
    switch ($mainAction) {
        case Ask::ACTION_BUY:
            try {
                $moneyInWallet = $walletService->getMoney($wallet, "EUR")->getAmount();
            } catch (NoMoneyException $e) {
                echo "You don't have any money left to spend on cryptocurrencies!\n";
                break;
            }
            $ticker = $ask->ticker();
            $amount = $ask->amount($moneyInWallet);
            $extendedCurrencies = $cryptoApi->search([$ticker]);
            if (empty($extendedCurrencies)) {
                echo "Could not find any currency with ticker {$ticker}\n";
                break;
            }
            $extendedCurrency = $extendedCurrencies[0];

            $provider->setExchangeRate(
                "EUR",
                $extendedCurrency->ticker(),
                $extendedCurrency->exchangeRate()
            );
            $baseProvider = new BaseCurrencyProvider($provider, "EUR");

            $transaction =(new BuyService($connection, $walletService, $transactionRepository, $currencyConverter))
                ->execute($wallet, $amount, $extendedCurrency);

            $sentMoney = $transaction->sentMoney();
            $receivedMoney = $transaction->receivedMoney();
            echo "{$receivedMoney->getAmount()} {$receivedMoney->getCurrency()->getCurrencyCode()} bought for " .
                "{$sentMoney->getAmount()} {$sentMoney->getCurrency()->getCurrencyCode()}\n";
            break;
        case Ask::ACTION_SELL:
            $ownedCurrencies = [];
            foreach ($walletService->getWalletById($wallet->id())->contents() as $money) {
                if ($money->getCurrency()->getCurrencyCode() === "EUR") {
                    continue;
                }
                $ownedCurrencies[] = $money->getCurrency();
            }
            if (empty($ownedCurrencies)) {
                echo "You don't own any cryptocurrencies!\n";
                break;
            }
            $currency = $ask->crypto($ownedCurrencies);
            $amount = $ask->amount($money->getAmount());

            $extendedCurrencies = $cryptoApi->search([$currency->getCurrencyCode()]);
            if (empty($extendedCurrencies)) {
                echo "Failed to fetch currency price\n";
                break;
            }
            $extendedCurrency = $extendedCurrencies[0];

            $provider->setExchangeRate(
                "EUR",
                $extendedCurrency->ticker(),
                $extendedCurrency->exchangeRate()
            );
            $baseProvider = new BaseCurrencyProvider($provider, "EUR");

            $transaction =
                (new SellService($connection, $walletService, $transactionRepository, $currencyConverter))
                ->execute($wallet, $amount, $extendedCurrency);

            $sentMoney = $transaction->sentMoney();
            $receivedMoney = $transaction->receivedMoney();
            echo "{$sentMoney->getAmount()} {$sentMoney->getCurrency()->getCurrencyCode()} sold for " .
                "{$receivedMoney->getAmount()} {$receivedMoney->getCurrency()->getCurrencyCode()}\n";

            break;
        case Ask::ACTION_WALLET:
            $display->wallet($walletService->getWalletById("a"));
            break;
        case Ask::ACTION_HISTORY:
            $display->transactions($transactionRepository->getAll());
            break;
        case Ask::ACTION_LIST:
            $display->currencies($cryptoApi->getTop());
            break;
        case Ask::ACTION_SEARCH:
            $query = $ask->query();
            $codes = explode(",", $query);
            $codes = array_map(static fn($value) => trim($value), $codes);
            $foundCurrencies = $cryptoApi->search($codes);
            if (empty($foundCurrencies)) {
                echo "No currency found!\n";
                break;
            }

            $display->currencies($foundCurrencies);

            break;
        case Ask::ACTION_EXIT:
            exit;
    }
}

