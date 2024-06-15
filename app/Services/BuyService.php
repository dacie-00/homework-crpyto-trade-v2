<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\ExtendedCurrency;
use App\Models\Transaction;
use App\Models\Wallet;
use App\Repositories\TransactionRepository;
use Brick\Math\RoundingMode;
use Brick\Money\CurrencyConverter;
use Brick\Money\Money;
use Doctrine\DBAL\Connection;

class BuyService
{
    private Connection $connection;
    private TransactionRepository $transactionRepository;
    private CurrencyConverter $currencyConverter;

    public function __construct(
        Connection $connection,
        TransactionRepository $transactionRepository,
        CurrencyConverter $currencyConverter
    ) {
        $this->connection = $connection;
        $this->transactionRepository = $transactionRepository;
        $this->currencyConverter = $currencyConverter;
    }

    public function execute(
        Wallet $wallet,
        float $amount,
        ExtendedCurrency $extendedCurrency
    ): Transaction {
        $moneyToGet = Money::of(
            $this->currencyConverter->convert(
                Money::of($amount, "EUR"),
                $extendedCurrency->definition(),
                RoundingMode::DOWN
            )->getAmount(),
            $extendedCurrency->definition()
        );
        $moneyToSpend = Money::of($amount, "EUR");

        $this->connection->beginTransaction();
        $wallet->add($moneyToGet);
        $wallet->subtract($moneyToSpend);
        $transaction = new Transaction
        (
            $moneyToSpend,
            Transaction::TYPE_BUY,
            $moneyToGet
        );
        $this->transactionRepository->add($transaction);
        $this->connection->commit();
        return $transaction;
    }
}