<?php
declare(strict_types=1);

namespace App;

use Brick\Math\BigDecimal;
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
     * @param \App\Currency[] $currencies
     */
    public function currencies(array $currencies): void
    {
        $table = (new Table($this->output))
            ->setHeaderTitle("Cryptocurrencies")
            ->setHeaders([
                "Name",
                "Currency",
                "Price (EUR)",
            ]);

        foreach ($currencies as $currency) {
            if ($currency->code() === "EUR") {
                continue;
            }
            $table->addRow([
                $currency->name(),
                $currency->code(),
                BigDecimal::one()->dividedBy($currency->exchangeRate(), 9, RoundingMode::DOWN)
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

    /**
     * @param Transaction[] $transactions
     */
    public function transactions(array $transactions): void
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

        foreach ($transactions as $transaction) {
            $amountIn = (string)$transaction->amountIn();
            $amountOut = (string)$transaction->amountOut();
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