<?php
declare(strict_types=1);

use App\Ask;
use App\CoinMarketCapAPI;
use App\CurrencyRepository;
use App\Display;
use App\Transaction;
use App\TransactionRepository;
use App\Wallet;
use Brick\Math\RoundingMode;
use Brick\Money\Currency;
use Brick\Money\CurrencyConverter;
use Brick\Money\ExchangeRateProvider\BaseCurrencyProvider;
use Brick\Money\ExchangeRateProvider\ConfigurableProvider;
use Brick\Money\Money;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\ConsoleOutput;

require_once "vendor/autoload.php";

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

function save($content, string $fileName): void
{
    file_put_contents(
        "storage/$fileName.json",
        json_encode($content, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
}

function load(string $fileName, bool $associative = false)
{
    if (!file_exists("storage/$fileName.json")) {
        return null;
    }
    return json_decode(
        file_get_contents("storage/$fileName.json"),
        $associative,
        512,
        JSON_THROW_ON_ERROR);
}

$consoleInput = new ArrayInput([]);
$consoleOutput = new ConsoleOutput();
$ask = new Ask($consoleInput, $consoleOutput);

$coinMarketCap = new CoinMarketCapAPI($_ENV["API_KEY"]);

$provider = null;
$exchangeRates = [];
if (!file_exists("storage/currencyCache.json") | !file_exists("storage/exchangeRatesCache.json")) {
    $provider = new ConfigurableProvider();
    $top = $coinMarketCap->getTop(5);

    $currencies = new CurrencyRepository();
    $currencies->add(Currency::of("EUR"));

    foreach ($top->data as $currency) {
        $provider->setExchangeRate(
            "EUR",
            $currency->symbol,
            1 / $currency->quote->EUR->price);
        $exchangeRates[$currency->symbol] = [
            "sourceCurrencyCode" => "EUR",
            "targetCurrencyCode" => $currency->symbol,
            "exchangeRate" => 1 / $currency->quote->EUR->price,
        ];
        $currencies->add(new Currency(
            $currency->symbol,
            $currency->id,
            $currency->name,
            9
        ));
    }
    save($currencies, "currencyCache");
    save($exchangeRates, "exchangeRatesCache");
} else {
    $currencies = load("currencyCache");
    $currencies = new CurrencyRepository($currencies);

    $top = $coinMarketCap->getTop(5);
    $provider = new ConfigurableProvider();

    foreach ($top->data as $currency) {
        $provider->setExchangeRate(
            "EUR",
            $currency->symbol,
            1 / $currency->quote->EUR->price);
        $exchangeRates[$currency->symbol] = [
            "sourceCurrencyCode" => "EUR",
            "targetCurrencyCode" => $currency->symbol,
            "exchangeRate" => 1 / $currency->quote->EUR->price,
        ];
        if (!$currencies->exists($currency->symbol)) {
            $currencies->add(new Currency(
                $currency->symbol,
                $currency->id,
                $currency->name,
                9
            ));
        }
    }

    $exchangeRates = load("exchangeRatesCache", true);

    foreach ($exchangeRates as $exchangeRate) {
        $provider->setExchangeRate(
            $exchangeRate["sourceCurrencyCode"],
            $exchangeRate["targetCurrencyCode"],
            $exchangeRate["exchangeRate"]
        );
    }
}

$transactions = new TransactionRepository();
if ($transactionData = load("transactions")) {
    $transactions = new TransactionRepository($transactionData);
} else {
    $transactions = new TransactionRepository();
}

$walletInfo = load("wallet", true);
$wallet = null;
if ($walletInfo) {
    $wallet = new Wallet($walletInfo[0], $walletInfo[1], $currencies);
} else {
    $wallet = new Wallet();
    $wallet->add(Money::of(1, "EUR"));
}


$baseProvider = new BaseCurrencyProvider($provider, "EUR");
$display = new Display($consoleOutput);
$display->currencies($currencies->getAll(), $baseProvider);
$currencyConverter = new CurrencyConverter($baseProvider);
while (true) {
    $mainAction = $ask->mainAction();
    switch ($mainAction) {
        case Ask::ACTION_BUY:
            $availableCurrencies = $currencies->getAll();
            foreach ($availableCurrencies as $index => $currency) {
                if ($currency->getCurrencyCode() === "EUR") {
                    unset($availableCurrencies[$index]);
                    $availableCurrencies = array_values($availableCurrencies);
                }
            }
            $currencyName = $ask->crypto($availableCurrencies);
            $currency = $currencies->getCurrencyByName($currencyName);
            $euro = $wallet->getMoney("EUR");

            $canAfford = $currencyConverter->convert($euro, $currency, RoundingMode::DOWN);
            if ($canAfford->isNegativeOrZero()) {
                echo "You cannot afford any of this currency\n";
                break;
            }
            $amount = $ask->amount($canAfford->getAmount());
            $moneyToGet = Money::of($amount, $currency);
            $moneyToSpend = $currencyConverter->convert($moneyToGet, "EUR", RoundingMode::DOWN);

            $wallet->add($moneyToGet);
            $wallet->subtract($moneyToSpend);
            $transactions->add(new Transaction(
                $moneyToSpend->getAmount(),
                $moneyToSpend->getCurrency()->getCurrencyCode(),
                $moneyToGet->getAmount(),
                $moneyToGet->getCurrency()->getCurrencyCode()
            ));
            save($transactions, "transactions");
            save($wallet, "wallet");
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
            $currency = $currencies->getCurrencyByName($currencyName);

            $money = $wallet->getMoney($currency->getCurrencyCode());

            $amount = $ask->amount($money->getAmount());

            $moneyToSpend = Money::of($amount, $currency);
            $moneyToGet = $currencyConverter->convert(Money::of($amount, $money->getCurrency()), "EUR", RoundingMode::DOWN);

            $wallet->add($moneyToGet);
            $wallet->subtract($moneyToSpend);
            $transactions->add(new Transaction(
                $moneyToSpend->getAmount(),
                $moneyToSpend->getCurrency()->getCurrencyCode(),
                $moneyToGet->getAmount(),
                $moneyToGet->getCurrency()->getCurrencyCode()
            ));
            save($transactions, "transactions");
            save($wallet, "wallet");
            break;
        case Ask::ACTION_WALLET:
            $display->wallet($wallet);
            break;
        case Ask::ACTION_HISTORY:
            $display->transactions($transactions);
            break;
        case Ask::ACTION_LIST:
            $display->currencies($currencies->getAll(), $baseProvider);
            break;
        case Ask::ACTION_SEARCH:
            $query = $ask->query();
            $currency = $coinMarketCap->search($query);
            if (!$currency) {
                echo "No currency found!";
                break;
            }

            $newCurrency = new Currency(
                $currency->symbol,
                $currency->id,
                $currency->name,
                9
            );
            $provider->setExchangeRate(
                "EUR",
                $currency->symbol,
                1 / $currency->quote->EUR->price
            );
            $exchangeRates[$currency->symbol] = [
                "sourceCurrencyCode" => "EUR",
                "targetCurrencyCode" => $currency->symbol,
                "exchangeRate" => 1 / $currency->quote->EUR->price,
            ];
            if (!$currencies->exists($currency->symbol)) {
                $currencies->add($newCurrency);
            }

            $display->currencies([$newCurrency], $baseProvider);

            save($currencies, "currencyCache");
            save($exchangeRates, "exchangeRatesCache");
            $baseProvider = new BaseCurrencyProvider($provider, "EUR");
            break;
        case Ask::ACTION_EXIT:
            exit;
    }
}

