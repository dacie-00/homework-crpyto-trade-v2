<?php
declare(strict_types=1);

use App\Ask;
use App\Display;
use App\Models\Transaction;
use App\Models\Wallet;
use App\Repositories\TransactionRepository;
use App\Services\BuyService;
use App\Services\SellService;
use App\Services\Cryptocurrency\CoinMarketCapApiService;
use Brick\Math\RoundingMode;
use Brick\Money\CurrencyConverter;
use Brick\Money\ExchangeRateProvider\BaseCurrencyProvider;
use Brick\Money\ExchangeRateProvider\ConfigurableProvider;
use Brick\Money\Money;
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
if (!$schemaManager->tablesExist(["transactions"])) {
    $table = new Table("transactions");
    $table->addColumn("amount_in", "decimal");
    $table->addColumn("currency_in", "decimal");
    $table->addColumn("amount_out", "decimal");
    $table->addColumn("currency_out", "decimal");
    $table->addColumn("created_at", "string");
    $schemaManager->createTable($table);
}
if (!$schemaManager->tablesExist(["wallet"])) {
    $table = new Table("wallet");
    $table->addColumn("ticker", "string");
    $table->addColumn("name", "string");
    $table->addColumn("amount", "decimal");
    $schemaManager->createTable($table);
}

$cryptoApi = new CoinMarketCapApiService($_ENV["COIN_MARKET_CAP_API_KEY"]);
//$cryptoApi = new CoinGeckoApiService($_ENV["COIN_GECKO_API_KEY"]);

$consoleInput = new ArrayInput([]);
$consoleOutput = new ConsoleOutput();
$ask = new Ask($consoleInput, $consoleOutput);


$provider = new ConfigurableProvider();
$transactionRepository = new TransactionRepository($connection);
$wallet = new Wallet($connection);


if ($wallet->isEmpty()) {
    echo "foo\n";
    $wallet->add(Money::of(1000, "EUR"));
}


$baseProvider = new BaseCurrencyProvider($provider, "EUR");
$currencyConverter = new CurrencyConverter($baseProvider);

$display = new Display($consoleOutput);
while (true) {
    $mainAction = $ask->mainAction();
    switch ($mainAction) {
        case Ask::ACTION_BUY:
            $ticker = readline("Enter the ticker of the cryptocurrency you wish to purchase - ");
            $amount = $ask->amount($wallet->getMoney("EUR")->getAmount());
            $extendedCurrencies = $cryptoApi->search([$ticker]);
            if (empty($extendedCurrencies)) {
                echo "Could not find any currency with ticker {$ticker}\n";
                break;
            }
            $extendedCurrency = $extendedCurrencies[0];

            $provider->setExchangeRate(
                "EUR",
                $extendedCurrency->code(),
                $extendedCurrency->exchangeRate()
            );
            $baseProvider = new BaseCurrencyProvider($provider, "EUR");
            // TODO: change this to currencyConverter?

            $money = $wallet->getMoney($extendedCurrency->code());
            (new BuyService($connection, $transactionRepository, $currencyConverter))
                ->execute($wallet, $amount, $extendedCurrency);
            break;
        case Ask::ACTION_SELL:
            $ownedCurrencies = [];
            foreach ($wallet->contents() as $money) {
                if ($money->getCurrency()->getCurrencyCode() === "EUR") {
                    continue;
                }
                $ownedCurrencies[] = $money->getCurrency();
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
                $extendedCurrency->code(),
                $extendedCurrency->exchangeRate()
            );
            $baseProvider = new BaseCurrencyProvider($provider, "EUR");
            // TODO: change this to currencyConverter?

            $money = $wallet->getMoney($extendedCurrency->code());
            (new SellService($connection, $transactionRepository, $currencyConverter))
                ->execute($wallet, $amount, $extendedCurrency);

            break;
        case Ask::ACTION_WALLET:
            $display->wallet($wallet);
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

