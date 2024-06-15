<?php

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
    private CurrencyConverter $currencyConverter;
    private TransactionRepository $transactionRepository;
    private Connection $connection;

    public function __construct(Connection $connection, TransactionRepository $transactionRepository, CurrencyConverter $currencyConverter)
    {
        $this->currencyConverter = $currencyConverter;
        $this->transactionRepository = $transactionRepository;
        $this->connection = $connection;
    }

    public function execute(
        Wallet $wallet,
        float $amount,
        ExtendedCurrency $extendedCurrency
    ) {
        $moneyToGet = Money::of(
            $this->currencyConverter->convert(
                $wallet->getMoney("EUR"),
                $extendedCurrency->definition(),
                RoundingMode::DOWN
            )->getAmount(),
            $extendedCurrency->definition()
        );
        $moneyToSpend = Money::of($amount, "EUR");

        $this->connection->beginTransaction();
        $wallet->add($moneyToGet);
        $wallet->subtract($moneyToSpend);
        $this->transactionRepository->add(new Transaction
            (
                $moneyToSpend->getAmount(),
                $moneyToSpend->getCurrency()->getCurrencyCode(),
                $moneyToGet->getAmount(),
                $moneyToGet->getCurrency()->getCurrencyCode()
            )
        );
        $this->connection->commit();
    }
}