<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\Transaction;
use App\Repositories\Currency\CurrencyRepositoryInterface;
use App\Repositories\Currency\Exceptions\CurrencyNotFoundException;
use App\Repositories\TransactionRepository;
use App\Repositories\Wallet\WalletRepository;
use App\Services\Exceptions\InsufficientMoneyException;
use App\Services\Exceptions\TransactionFailedException;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Brick\Money\Money;
use Doctrine\DBAL\Connection;

class BuyService
{
    private Connection $connection;
    private TransactionRepository $transactionRepository;
    private WalletRepository $walletRepository;
    private CurrencyRepositoryInterface $currencyRepository;

    public function __construct(
        Connection $connection,
        TransactionRepository $transactionRepository,
        WalletRepository $walletRepository,
        CurrencyRepositoryInterface $currencyRepository
    ) {
        $this->connection = $connection;
        $this->transactionRepository = $transactionRepository;
        $this->walletRepository = $walletRepository;
        $this->currencyRepository = $currencyRepository;
    }

    public function execute(
        string $walletId,
        float $amount,
        string $ticker
    ): Transaction {
        $moneyToSpend = Money::of($amount, "EUR");

        $moneyInWallet = $this->walletRepository->getMoney($walletId, "EUR")->getAmount();
        if ($moneyToSpend->isGreaterThan($moneyInWallet)) {
            throw new InsufficientMoneyException("Not enough EUR in wallet");
        }

        try {
            $extendedCurrencies = $this->currencyRepository->search([$ticker]);
        } catch (CurrencyNotFoundException $e) {
            throw new TransactionFailedException("Unknown currency - $ticker");
        }
        $extendedCurrency = $extendedCurrencies[0];

        $money = BigDecimal::of($amount)->multipliedBy($extendedCurrency->exchangeRate());
        $moneyToGet = Money::of($money, $extendedCurrency->definition(), null, RoundingMode::DOWN);

        $this->connection->beginTransaction();
        $this->walletRepository->addToWallet($walletId, $moneyToGet);
        $this->walletRepository->subtractFromWallet($walletId, $moneyToSpend);
        $transaction = new Transaction
        (
            $this->walletRepository->getOwner($walletId),
            $moneyToSpend,
            Transaction::TYPE_BUY,
            $moneyToGet
        );
        $this->transactionRepository->add($transaction);
        $this->connection->commit();
        return $transaction;
    }
}