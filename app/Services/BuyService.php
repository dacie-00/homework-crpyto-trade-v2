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
    private WalletService $walletService;
    private TransactionRepository $transactionRepository;
    private CurrencyConverter $currencyConverter;

    public function __construct(
        Connection $connection,
        WalletService $walletService,
        TransactionRepository $transactionRepository,
        CurrencyConverter $currencyConverter
    ) {
        $this->connection = $connection;
        $this->walletService = $walletService;
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
        $this->walletService->addToWallet($wallet, $moneyToGet);
        $this->walletService->subtractFromWallet($wallet, $moneyToSpend);
        $transaction = new Transaction
        (
            $wallet->userId(),
            $moneyToSpend,
            Transaction::TYPE_BUY,
            $moneyToGet
        );
        $this->transactionRepository->add($transaction);
        $this->connection->commit();
        return $transaction;
    }
}