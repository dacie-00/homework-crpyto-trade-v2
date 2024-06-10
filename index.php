<?php
declare(strict_types=1);

use App\Ask;
use App\Crypto\CryptoAPI;
use App\Crypto\CryptoDisplay;
use App\CurrencyRepository;
use App\Currency;
use App\ExchangeService;
use App\Transaction;
use App\Wallet;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\ConsoleOutput;

require_once "vendor/autoload.php";

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$euro = new Currency(-1, "Euro", "EUR", 1);


$consoleInput = new ArrayInput([]);
$consoleOutput = new ConsoleOutput();
$ask = new Ask($consoleInput, $consoleOutput);

$crypto = new CryptoAPI($_ENV["API_KEY"]);

if (!file_exists("storage/cryptoCache.json")) {
    $list = $crypto->getTop(5);
    $currencies = new CurrencyRepository();
    $currencies->add($euro);
    foreach ($list->data as $currency) {
        $currencies->add(new Currency(
            $currency->id,
            $currency->name,
            $currency->symbol,
            (int)($currency->quote->EUR->price),
        ));
    }
    file_put_contents("storage/cryptoCache.json", json_encode($currencies, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
} else {
    $currenciesJson = json_decode(file_get_contents("storage/cryptoCache.json"), false, 512, JSON_THROW_ON_ERROR);
    $currencies = new CurrencyRepository($currenciesJson);
}


$transactions = [];
$exchangeService = new ExchangeService();
$wallet = new Wallet();
$wallet->add($euro, 1000);

while(true) {
    $display = new CryptoDisplay($consoleOutput);
    $display->display($currencies->getAll());
    $mainAction = $ask->mainAction();
    switch ($mainAction) {
        case Ask::ACTION_BUY:
            $currencyName = $ask->crypto($currencies->getAll());
            $currency = $currencies->getCurrencyByName($currencyName);
            $canAfford = (int)($wallet->getCurrencyAmount($euro->ticker()) / $currency->exchangeRate());
            if ($canAfford <= 1) {
                echo "You cannot afford any of this currency\n";
                break;
            }
            $amount = $ask->quantity(1, $canAfford);

            $transactions[] = $exchangeService->exchange($wallet, $amount * $currency->exchangeRate(), $euro, $currency);
            break;
        case Ask::ACTION_SELL:
            $ownedCurrencies = [];
            foreach($wallet->contents() as $ticker => $_) {
                $ownedCurrencies[] = $currencies->getCurrencyByTicker($ticker);
            }
            $currencyName = $ask->crypto($ownedCurrencies);
            $currency = $currencies->getCurrencyByName($currencyName);
            $totalAmount = $wallet->getCurrencyAmount($currency->ticker());
            $amount = $ask->quantity(1, $totalAmount);
            $transactions[] = $exchangeService->exchange($wallet, $amount, $currency, $euro);
            break;
        case Ask::ACTION_WALLET:
            foreach ($wallet->contents() as $currency => $amount) {
                echo "$currency -> $amount\n";
            }
            break;
        case Ask::ACTION_HISTORY:
            /** @var Transaction $transaction */
            foreach ($transactions as $transaction) {
                echo "{$transaction->amountIn()} {$transaction->currencyIn()} -> {$transaction->amountOut()} {$transaction->currencyOut()}\n";
            }
    }
}

