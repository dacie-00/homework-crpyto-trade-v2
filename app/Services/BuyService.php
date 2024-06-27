<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\ExtendedCurrency;
use App\Models\Transaction;
use App\Models\Wallet;
use App\Repositories\Currency\CurrencyRepositoryInterface;
use App\Repositories\TransactionRepository;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Brick\Money\Currency;
use Brick\Money\CurrencyConverter;
use Brick\Money\ExchangeRateProvider\BaseCurrencyProvider;
use Brick\Money\ExchangeRateProvider\ConfigurableProvider;
use Brick\Money\Money;
use Doctrine\DBAL\Connection;

class BuyService
{
    private Connection $connection;
    private WalletService $walletService;
    private TransactionRepository $transactionRepository;
    private CurrencyRepositoryInterface $currencyRepository;

    public function __construct(
        Connection $connection,
        WalletService $walletService,
        TransactionRepository $transactionRepository,
        CurrencyRepositoryInterface $currencyRepository
    ) {
        $this->connection = $connection;
        $this->walletService = $walletService;
        $this->transactionRepository = $transactionRepository;
        $this->currencyRepository = $currencyRepository;
    }

    public function execute(
        Wallet $wallet,
        float $amount,
        string $ticker
    ): Transaction {
        $extendedCurrencies = $this->currencyRepository->search([$ticker]);
        if (empty($extendedCurrencies)) {
            echo "currency not found"; // TODO: throw exception within repository class
        }
        $extendedCurrency = $extendedCurrencies[0];

        $money = BigDecimal::of($amount)->multipliedBy($extendedCurrency->exchangeRate());
        $moneyToGet = Money::of($money, $extendedCurrency->definition(), null, RoundingMode::DOWN);

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