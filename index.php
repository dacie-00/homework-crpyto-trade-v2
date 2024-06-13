<?php
declare(strict_types=1);

use App\Ask;
use App\CoinGeckoAPI;
use App\CoinMarketCapAPI;
use App\CurrencyRepository;
use App\Display;
use App\Transaction;
use App\TransactionRepository;
use App\Wallet;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Brick\Money\Currency;
use Brick\Money\CurrencyConverter;
use Brick\Money\ExchangeRateProvider\BaseCurrencyProvider;
use Brick\Money\ExchangeRateProvider\ConfigurableProvider;
use Brick\Money\Money;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Table;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\ConsoleOutput;

require_once "vendor/autoload.php";

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$connectionParams = [
    "driver" => "pdo_sqlite",
    "path" => "storage/database.sqlite"
];

$connection = DriverManager::getConnection($connectionParams);

$schemaManager = $connection->createSchemaManager();
if (!$schemaManager->tablesExist(["currencies"])) {
    $table = new Table("currencies");
    $table->addColumn("code", "string");
    $table->addColumn("name", "string");
    $table->addColumn("numeric_code", "integer");
    $table->addColumn("exchange_rate", "decimal");
    $schemaManager->createTable($table);
}
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
    $table->addColumn("currency", "string");
    $table->addColumn("amount", "decimal");
    $schemaManager->createTable($table);
}

$consoleInput = new ArrayInput([]);
$consoleOutput = new ConsoleOutput();
$ask = new Ask($consoleInput, $consoleOutput);

$cryptoApi = new CoinMarketCapAPI($_ENV["COIN_MARKET_CAP_API_KEY"]);
//$cryptoApi = new CoinGeckoAPI($_ENV["COIN_GECKO_API_KEY"]);

$provider = null;
$currencyRepository = new CurrencyRepository($connection);

if ($currencyRepository->isEmpty()) {
    $provider = new ConfigurableProvider();
    $currencyRepository->add(new \App\Currency(Currency::of("EUR"), BigDecimal::one()));

    $top = $cryptoApi->getTop(5);
    $currencyRepository->add($top);
    foreach ($top as $currency) {
        $provider->setExchangeRate(
            "EUR",
            $currency->code(),
            $currency->exchangeRate()
        );
    }
} else {
    $provider = new ConfigurableProvider();

    $savedCurrencies = $currencyRepository->getAll();

    $currencyCodes = [];
    foreach ($savedCurrencies as $currency) {
        $currencyCodes[] = $currency->code;
    }

    $currencyRepository->add($cryptoApi->search($currencyCodes));
    foreach ($savedCurrencies as $currency) {
        $provider->setExchangeRate(
            "EUR",
            $currency->code(),
            $currency->exchangeRate()
        );
    }
}

$transactionRepository = new TransactionRepository($connection);

$wallet = new Wallet($connection, $currencyRepository);
if ($wallet->isEmpty()) {
    $wallet->add(Money::of(1000, "EUR"));
}


$baseProvider = new BaseCurrencyProvider($provider, "EUR");
$display = new Display($consoleOutput);
$display->currencies($currencyRepository->getAll());
$currencyConverter = new CurrencyConverter($baseProvider);
while (true) {
    $mainAction = $ask->mainAction();
    switch ($mainAction) {
        case Ask::ACTION_BUY:
            $availableCurrencies = [];
            /** @var \App\Currency $currency */
            foreach ($currencyRepository->getAll() as $currency) {
                if ($currency->code() === "EUR") {
                    continue;
                }
                $availableCurrencies[] = $currency->definition();

            }
            $currencyName = $ask->crypto($availableCurrencies);
            $currency = $currencyRepository->getCurrencyByName($currencyName);
            $euro = $wallet->getMoney("EUR");

            $canAfford = $currencyConverter->convert($euro, $currency->definition(), RoundingMode::DOWN);
            if ($canAfford->isNegativeOrZero()) {
                echo "You cannot afford any of this currency\n";
                break;
            }
            $amount = $ask->amount($canAfford->getAmount());
            $moneyToGet = Money::of($amount, $currency->definition());
            $moneyToSpend = $currencyConverter->convert($moneyToGet, "EUR", RoundingMode::UP);

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
            break;
        case Ask::ACTION_SELL:
            $ownedCurrencies = [];
            foreach ($wallet->contents() as $money) {
                if ($money->getCurrency()->getCurrencyCode() === "EUR") {
                    continue;
                }
                $ownedCurrencies[] = $money->getCurrency();
            }
            $currencyName = $ask->crypto($ownedCurrencies);
            $currency = $currencyRepository->getCurrencyByName($currencyName);

            $money = $wallet->getMoney($currency->code());

            $amount = $ask->amount($money->getAmount());

            $moneyToSpend = Money::of($amount, $currency->definition());
            $moneyToGet = $currencyConverter->convert(Money::of($amount, $money->getCurrency()), "EUR", RoundingMode::DOWN);

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
            break;
        case Ask::ACTION_WALLET:
            $display->wallet($wallet);
            break;
        case Ask::ACTION_HISTORY:
            $display->transactions($transactionRepository->getAll());
            break;
        case Ask::ACTION_LIST:
            $display->currencies($currencyRepository->getAll());
            break;
        case Ask::ACTION_SEARCH:
            $query = $ask->query();
            $codes = explode(",", $query);
            $codes = array_map(static fn($value) => trim($value), $codes);
            $foundCurrencies = $cryptoApi->search($codes);
            if (empty($foundCurrencies)) {
                echo "No currency found!";
                break;
            }

            $currencyRepository->add($foundCurrencies);
            foreach ($foundCurrencies as $currency) {
                $provider->setExchangeRate(
                    "EUR",
                    $currency->code(),
                    $currency->exchangeRate()
                );
            }

            $display->currencies($foundCurrencies);

            $baseProvider = new BaseCurrencyProvider($provider, "EUR");
            break;
        case Ask::ACTION_EXIT:
            exit;
    }
}

