<?php
declare(strict_types=1);

namespace App;

use App\Models\ExtendedCurrency;
use App\Models\Transaction;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
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
     * @param ExtendedCurrency[] $currencies
     */
    public function currencies(array $currencies): void
    {
        $table = (new Table($this->output))
            ->setHeaderTitle("Cryptocurrencies")
            ->setHeaders([
                "Ticker",
                "Price (EUR)",
            ]);

        foreach ($currencies as $currency) {
            if ($currency->ticker() === "EUR") {
                continue;
            }
            $table->addRow([
                $currency->ticker(),
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
                "ExtendedCurrency",
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
                "Amount sent",
                "Ticker",
                "Transaction",
                "Amount received",
                "Ticker",
                "Date",
            ]);

        foreach ($transactions as $transaction) {
            $sentMoney = $transaction->sentMoney();
            $receivedMoney = $transaction->receivedMoney();
            $table->addRow([
                $sentMoney->getAmount(),
                $sentMoney->getCurrency()->getCurrencyCode(),
                $transaction->type() === Transaction::TYPE_BUY ? "bought" : "sold",
                $receivedMoney->getAmount(),
                $receivedMoney->getCurrency()->getCurrencyCode(),
                $transaction->createdAt()->timezone("Europe/Riga")->format("Y-m-d H:i:s"),
            ]);
        }
        $table->render();
    }
}