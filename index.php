<?php
declare(strict_types=1);

use App\Ask;
use App\Crypto\CryptoAPI;
use App\Crypto\CryptoDisplay;
use App\CurrencyRepository;
use App\Transaction;
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

function save(array $transactions, Wallet $wallet): void
{
    file_put_contents("storage/transactions.json", json_encode($transactions, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    file_put_contents("storage/wallet.json", json_encode($wallet, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
}

function load(string $fileName, bool $associative = false)
{
    if (!file_exists("storage/$fileName.json")) {
        return null;
    }
    return json_decode(file_get_contents("storage/$fileName.json"), $associative, 512, JSON_THROW_ON_ERROR);
}

$consoleInput = new ArrayInput([]);
$consoleOutput = new ConsoleOutput();
$ask = new Ask($consoleInput, $consoleOutput);

$crypto = new CryptoAPI($_ENV["API_KEY"]);

$provider = null;
$exchangeRates = [];
if (!file_exists("storage/currencyCache.json")) {
    $provider = new ConfigurableProvider();
    $list = $crypto->getTop(5);

    $currencies = new CurrencyRepository();
    $currencies->add(Currency::of("EUR"));

    foreach ($list->data as $currency) {
        $provider->setExchangeRate("EUR", $currency->symbol, 1 / $currency->quote->EUR->price);
        $exchangeRates[$currency->symbol] = ["sourceCurrencyCode" => "EUR", "targetCurrencyCode" => $currency->symbol, "exchangeRate" => 1 / $currency->quote->EUR->price];
        $currencies->add(new Currency
        (
            $currency->symbol,
            $currency->id,
            $currency->name,
            9
        ),
        );
    }
    file_put_contents("storage/currencyCache.json", json_encode($currencies, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    file_put_contents("storage/conversionRateCache.json", json_encode($exchangeRates, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
} else {
    $currencies = load("currencyCache");
    $currencies = new CurrencyRepository($currencies);

    $exchangeRates = load("conversionRateCache");

    $provider = new ConfigurableProvider();
    foreach ($exchangeRates as $exchangeRate) {
        $provider->setExchangeRate($exchangeRate->sourceCurrencyCode, $exchangeRate->targetCurrencyCode, $exchangeRate->exchangeRate);
    }
}

$transactions = [];
if ($transactionData = load("transactions")) {
    foreach ($transactionData as $transaction) {
        $transactions[] = new Transaction(
            BigDecimal::of($transaction->amountIn),
            $transaction->currencyIn,
            BigDecimal::of($transaction->amountOut),
            $transaction->currencyOut,
            $transaction->createdAt
        );
    }
}

$walletInfo = load("wallet", true);
$wallet = null;
if ($walletInfo) {
    $wallet = new Wallet($walletInfo[0], $walletInfo[1], $currencies);
} else {
    $wallet = new Wallet();
    $wallet->add(Money::of(1, "EUR"));
}


while (true) {
    $provider = new BaseCurrencyProvider($provider, "EUR");
    $display = new CryptoDisplay($consoleOutput);
    $display->display($currencies->getAll(), $provider);
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

            $canAfford = (new CurrencyConverter($provider))->convert($euro, $currency, RoundingMode::DOWN);
            if ($canAfford->isNegativeOrZero()) {
                echo "You cannot afford any of this currency\n";
                break;
            }
            $amount = $ask->amount($canAfford->getAmount());
            $moneyToGet = Money::of($amount, $currency);
            $moneyToSpend = (new CurrencyConverter($provider))->convert($moneyToGet, "EUR", RoundingMode::DOWN);

            $wallet->add($moneyToGet);
            $wallet->subtract($moneyToSpend);
            $transactions[] = new Transaction(
                $moneyToSpend->getAmount(),
                $moneyToSpend->getCurrency()->getCurrencyCode(),
                $moneyToGet->getAmount(),
                $moneyToGet->getCurrency()->getCurrencyCode()
            );
            save($transactions, $wallet);
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
            $moneyToGet = (new CurrencyConverter($provider))->convert(Money::of($amount, $money->getCurrency()), "EUR", RoundingMode::DOWN);

            $wallet->add($moneyToGet);
            $wallet->subtract($moneyToSpend);
            $transactions[] = new Transaction(
                $moneyToSpend->getAmount(),
                $moneyToSpend->getCurrency()->getCurrencyCode(),
                $moneyToGet->getAmount(),
                $moneyToGet->getCurrency()->getCurrencyCode()
            );
            save($transactions, $wallet);
            break;
        case Ask::ACTION_WALLET:
            $display->wallet($wallet);
            break;
        case Ask::ACTION_HISTORY:
            $display->transactions($transactions);
    }
}

