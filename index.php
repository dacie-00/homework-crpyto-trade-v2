<?php
declare(strict_types=1);

use App\Ask;
use App\Display;
use App\Models\Transaction;
use App\Models\Wallet;
use App\Repositories\TransactionRepository;
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
            $currency = $cryptoApi->search([$ticker]);
            if (empty($currency)) {
                echo "Could not find any currency with ticker {$ticker}\n";
                break;
            }
            $currency = $currency[0];

            $provider->setExchangeRate(
                "EUR",
                $currency->code(),
                $currency->exchangeRate()
            );
            $baseProvider = new BaseCurrencyProvider($provider, "EUR");

            $moneyToGet = Money::of(
                $currencyConverter->convert(
                    $wallet->getMoney("EUR"),
                    $currency->definition(),
                    RoundingMode::DOWN
                )->getAmount(),
                $currency->definition()
            );
            $moneyToSpend = Money::of($amount, "EUR");

            $connection->beginTransaction();
            $wallet->add($moneyToGet);
            $wallet->subtract($moneyToSpend);
            $transactionRepository->add(new Transaction
                (
                    $moneyToSpend->getAmount(),
                    $moneyToSpend->getCurrency()->getCurrencyCode(),
                    $moneyToGet->getAmount(),
                    $moneyToGet->getCurrency()->getCurrencyCode()
                )
            );
            $connection->commit();
            break;
        case Ask::ACTION_SELL:
            $ownedCurrencies = [];
            /** @var Money $money */
            foreach ($wallet->contents() as $money) {
                if ($money->getCurrency()->getCurrencyCode() === "EUR") {
                    continue;
                }
                $ownedCurrencies[] = $money->getCurrency();
            }
            $currency = $ask->crypto($ownedCurrencies);
            $amount = $ask->amount($money->getAmount());

            $currency = $cryptoApi->search([$currency->getCurrencyCode()]);
            if (empty($currency)) {
                echo "Failed to fetch currency price\n";
                break;
            }
            $currency = $currency[0];

            $provider->setExchangeRate(
                "EUR",
                $currency->code(),
                $currency->exchangeRate()
            );
            $baseProvider = new BaseCurrencyProvider($provider, "EUR");

            $money = $wallet->getMoney($currency->code());

            $moneyToSpend = Money::of($amount, $currency->definition());
            $moneyToGet = $currencyConverter->convert(
                Money::of($amount, $money->getCurrency()),
                "EUR",
                RoundingMode::DOWN);

            $connection->beginTransaction();
            $wallet->add($moneyToGet);
            $wallet->subtract($moneyToSpend);
            $transactionRepository->add(new Transaction
                (
                    $moneyToSpend->getAmount(),
                    $moneyToSpend->getCurrency()->getCurrencyCode(),
                    $moneyToGet->getAmount(),
                    $moneyToGet->getCurrency()->getCurrencyCode()
                )
            );
            $connection->commit();
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

