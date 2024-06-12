<?php
declare(strict_types=1);

use App\Ask;
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
$currencies = null;
if (!file_exists("storage/currencies.json")) {
    $provider = new ConfigurableProvider();
    $top = $coinMarketCap->getTop(5);

    $currencies = new CurrencyRepository();
    $currencies->add(new \App\Currency(Currency::of("EUR"), BigDecimal::one()));
    foreach ($top as $currency) {
        $currencies->add($currency);
        $provider->setExchangeRate(
            "EUR",
            $currency->code(),
            $currency->exchangeRate()
        );
    }
    save($currencies, "currencies");
} else {
    $provider = new ConfigurableProvider();

    $savedCurrencies = load("currencies");
    $currencyCodes = [];

    foreach ($savedCurrencies as $currency) {
        $currencyCodes[] = $currency->code;
    }

    $currencies = new CurrencyRepository($coinMarketCap->search($currencyCodes));
    foreach ($currencies->getAll() as $currency) {
        $provider->setExchangeRate(
            "EUR",
            $currency->code(),
            $currency->exchangeRate()
        );
    }
    $currencies->add(new \App\Currency(Currency::of("EUR"), BigDecimal::one()));
    save($currencies, "currencies");
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
    $wallet->add(Money::of(1000, "EUR"));
}


$baseProvider = new BaseCurrencyProvider($provider, "EUR");
$display = new Display($consoleOutput);
$display->currencies($currencies->getAll());
$currencyConverter = new CurrencyConverter($baseProvider);
while (true) {
    $mainAction = $ask->mainAction();
    switch ($mainAction) {
        case Ask::ACTION_BUY:
            $availableCurrencies = $currencies->getAll();
            foreach ($availableCurrencies as $index => $currency) {
                if ($currency->code() === "EUR") {
                    unset($availableCurrencies[$index]);
                    $availableCurrencies = array_values($availableCurrencies);
                }
            }
            $currencyName = $ask->crypto($availableCurrencies);
            $currency = $currencies->getCurrencyByName($currencyName);
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

            $money = $wallet->getMoney($currency->code());

            $amount = $ask->amount($money->getAmount());

            $moneyToSpend = Money::of($amount, $currency->definition());
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
            $display->currencies($currencies->getAll());
            break;
        case Ask::ACTION_SEARCH:
            $query = $ask->query();
            $codes = explode(",", $query);
            $codes = array_map(static fn($value) => trim($value), $codes);
            $foundCurrencies = $coinMarketCap->search($codes);
            if (empty($foundCurrencies)) {
                echo "No currency found!";
                break;
            }

            foreach ($foundCurrencies as $currency) {
                $provider->setExchangeRate(
                    "EUR",
                    $currency->code(),
                    $currency->exchangeRate()
                );
                $currencies->add($currency);
            }

            $display->currencies($foundCurrencies);

            save($currencies, "currencies");
            $baseProvider = new BaseCurrencyProvider($provider, "EUR");
            break;
        case Ask::ACTION_EXIT:
            exit;
    }
}

