<?php
declare(strict_types=1);

use App\Ask;
use App\Crypto\CryptoAPI;
use App\Crypto\CryptoDisplay;
use App\Crypto\CryptoRepository;
use App\Currency;
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
$list = $crypto->getTop(15);

$currencies = new CryptoRepository();
foreach ($list->data as $currency) {
    $currencies->add(new Currency(
        $currency->id,
        $currency->name,
        $currency->symbol,
        (int)($currency->quote->EUR->price),
    ));
}

$transactions = [];
$wallet = new Wallet();

while(true) {
    $display = new CryptoDisplay($consoleOutput);
    $display->display($currencies->getAll());
    $mainAction = $ask->mainAction();
    switch ($mainAction) {
        case Ask::ACTION_BUY:
            $currencyName = $ask->crypto($currencies->getAll());
            $quantity = $ask->quantity(1);
            $currency = $currencies->getCurrencyByName($currencyName);
            $wallet->add($currency, $quantity);
            $transactions[] = new Transaction(
                $quantity,
                $currency->ticker(),
                "BUY",
                $quantity * $currency->exchangeRate(),
                $euro->ticker(),
            );
            break;
        case Ask::ACTION_SELL:
            $ownedCurrencies = [];
            foreach($wallet->contents() as $ticker => $_) {
                $ownedCurrencies[] = $currencies->getCurrencyByTicker($ticker);
            }
            $currencyName = $ask->crypto($ownedCurrencies);
            $currency = $currencies->getCurrencyByName($currencyName);
            $wallet->getCurrencyAmount($currency->ticker());
            $quantity = $ask->quantity();
            $wallet->subtract($currency, $quantity);
            $transactions[] = new Transaction(
                $quantity,
                $currency->ticker(),
                "SELL",
                $quantity * $currency->exchangeRate(),
                $euro->ticker(),
            );
            break;
        case Ask::ACTION_WALLET:
            foreach ($wallet->contents() as $currency => $amount) {
                echo "$currency -> $amount\n";
            }
            break;
        case Ask::ACTION_HISTORY:
            /** @var Transaction $transaction */
            foreach ($transactions as $transaction) {
                $verb = $transaction->type() === "BUY" ? "bought" : "sold";
                echo "{$transaction->amountIn()} {$transaction->currencyIn()} $verb for {$transaction->amountOut()} {$transaction->currencyOut()}\n";
            }
    }
}

