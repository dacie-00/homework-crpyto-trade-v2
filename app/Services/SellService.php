<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\Currency;
use App\Models\Money;
use App\Models\Transaction;
use App\Repositories\Currency\CurrencyRepositoryInterface;
use App\Repositories\Currency\Exceptions\CurrencyNotFoundException;
use App\Repositories\Transaction\DoctrineDbalTransactionRepository;
use App\Repositories\Transaction\TransactionRepositoryInterface;
use App\Repositories\Wallet\DoctrineDbalWalletRepository;
use App\Repositories\Wallet\WalletRepositoryInterface;
use App\Services\Exceptions\InsufficientMoneyException;
use App\Services\Exceptions\TransactionFailedException;
use Doctrine\DBAL\Connection;

class SellService
{
    private Connection $connection;
    private DoctrineDbalTransactionRepository $transactionRepository;
    private DoctrineDbalWalletRepository $walletRepository;
    private CurrencyRepositoryInterface $currencyRepository;

    public function __construct(
        Connection $connection,
        TransactionRepositoryInterface $transactionRepository,
        WalletRepositoryInterface $walletRepository,
        CurrencyRepositoryInterface $currencyRepository
    ) {
        $this->connection = $connection;
        $this->transactionRepository = $transactionRepository;
        $this->walletRepository = $walletRepository;
        $this->currencyRepository = $currencyRepository;
    }

    public function execute(
        string $walletId,
        Money $moneyToSpend
    ): Transaction {
        $moneyInWallet = $this->walletRepository->getMoneyInWallet($walletId, $moneyToSpend->currency());
        if ($moneyInWallet->amount() < $moneyToSpend->amount()) {
            throw new InsufficientMoneyException(
                "Not enough {$moneyToSpend->ticker()} in wallet ({$moneyInWallet->amount()}/{$moneyToSpend->amount()})"
            );
        }

        try {
            [$newestCurrency] = $this->currencyRepository->search([$moneyToSpend->ticker()]);
        } catch (CurrencyNotFoundException $e) { // TODO: maybe change this because CurrencyNotFound is more specific than TransactionFailed
            throw new TransactionFailedException("Unknown currency - {$moneyToSpend->ticker()}");
        }

        $moneyToGet = new Money(
            $moneyToSpend->amount() * $newestCurrency->exchangeRate(),
            new Currency("EUR")
        );

        $this->connection->beginTransaction();
        $this->walletRepository->addToWallet($walletId, $moneyToGet);
        $this->walletRepository->subtractFromWallet($walletId, $moneyToSpend);
        $transaction = new Transaction
        (
            $this->walletRepository->getOwner($walletId),
            $moneyToSpend,
            Transaction::TYPE_SELL,
            $moneyToGet
        );
        $this->transactionRepository->add($transaction);
        $this->connection->commit();
        return $transaction;
    }
}