<?php

namespace App\Services;

use App\Models\ExtendedCurrency;
use App\Models\Transaction;
use App\Models\Wallet;
use App\Repositories\TransactionRepository;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Brick\Money\CurrencyConverter;
use Brick\Money\Money;
use Doctrine\DBAL\Connection;

class SellService
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
    ) {
        $moneyToSpend = Money::of($amount, $extendedCurrency->definition());
        $moneyToGet = $this->currencyConverter->convert(
            Money::of($amount, $extendedCurrency->definition()),
            "EUR",
            RoundingMode::DOWN);

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