<?php
declare(strict_types=1);

namespace App;

use Brick\Math\RoundingMode;
use Brick\Money\Currency;
use Brick\Money\ExchangeRateProvider;
use Brick\Money\Money;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Output\OutputInterface;

class Display
{
    private OutputInterface $output;

    public function __construct(OutputInterface $output)
    {
        $this->output = $output;
    }

    /**
     * @param Currency[] $currencies
     */
    public function currencies(array $currencies, ExchangeRateProvider $provider): void
    {
        $table = (new Table($this->output))
            ->setHeaderTitle("Cryptocurrencies")
            ->setHeaders([
                "Name",
                "Currency",
                "Price (EUR)",
            ]);

        foreach ($currencies as $currency) {
            if ($currency->getCurrencyCode() === "EUR") {
                continue;
            }
            $table->addRow([
                $currency->getName(),
                $currency->getCurrencyCode(),
                $provider->getExchangeRate($currency->getCurrencyCode(), "EUR")->toScale(8, RoundingMode::DOWN),
            ]);
        }
        $table->render();
    }

    public function wallet($wallet): void
    {
        $table = (new Table($this->output))
            ->setHeaderTitle("Wallet")
            ->setHeaders([
                "Currency",
                "Amount",
            ]);

        /** @var Money $money */
        foreach ($wallet->contents() as $money) {
            $moneyWithoutZeros = rtrim((string)$money->getAmount(), "0");
            $table->addRow([
                $money->getCurrency(),
                $moneyWithoutZeros,
            ]);
        }
        $table->render();
    }

    public function transactions($transactions): void
    {
        $table = (new Table($this->output))
            ->setHeaderTitle("Transactions")
            ->setHeaders([
                "Amount in",
                "Currency In",
                "-->",
                "Amount out",
                "Currency out",
                "Date",
            ]);

        /** @var Transaction $transaction */
        foreach ($transactions as $transaction) {
            $amountIn = rtrim((string)$transaction->amountIn(), "0");
            $amountOut = rtrim((string)$transaction->amountOut(), "0");
            $table->addRow([
                $amountIn,
                $transaction->currencyIn(),
                "-->",
                $amountOut,
                $transaction->currencyOut(),
                $transaction->createdAt()->timezone("Europe/Riga")->format("Y-m-d H:i:s"),
            ]);
        }
        $table->render();
    }

}