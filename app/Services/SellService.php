<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\Currency;
use App\Models\Money;
use App\Models\Transaction;
use App\Repositories\Currency\CurrencyRepositoryInterface;
use App\Repositories\Currency\Exceptions\CurrencyNotFoundException;
use App\Repositories\TransactionRepository;
use App\Repositories\Wallet\WalletRepository;
use App\Services\Exceptions\InsufficientMoneyException;
use App\Services\Exceptions\TransactionFailedException;
use Doctrine\DBAL\Connection;

class SellService
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
        Money $moneyToSpend
    ): Transaction {
        $moneyInWallet = $this->walletRepository->getMoney($walletId, $moneyToSpend);
        if ($moneyInWallet->amount() < $moneyToSpend->amount()) {
            throw new InsufficientMoneyException(
                "Not enough {$moneyToSpend->ticker()} in wallet ({$moneyInWallet->amount()}/{$moneyToSpend->amount()})"
            );
        }

        try {
            $newestCurrency = $this->currencyRepository->search([$moneyToSpend->ticker()]);
        } catch (CurrencyNotFoundException $e) {
            throw new TransactionFailedException("Unknown currency - {$moneyToSpend->ticker()}");
        }
        $newestCurrency = $newestCurrency[0];

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