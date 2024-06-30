<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Currency;
use App\Models\Money;
use App\Repositories\Currency\CoinMarketCapApiCurrencyRepository;
use App\Repositories\Currency\CurrencyRepositoryInterface;
use App\Repositories\Transaction\DoctrineDbalTransactionRepository;
use App\Repositories\Transaction\TransactionRepositoryInterface;
use App\Repositories\Wallet\Exceptions\WalletNotFoundException;
use App\Repositories\Wallet\DoctrineDbalWalletRepository;
use App\Repositories\Wallet\WalletRepositoryInterface;
use App\Services\BuyService;
use App\Services\Exceptions\InsufficientMoneyException;
use App\Services\Exceptions\TransactionFailedException;
use App\Services\SellService;
use App\Services\Transfers\Exceptions\InvalidTransferAmountException;
use App\Services\Transfers\Exceptions\InvalidTransferCurrencyTickerException;
use App\Services\Transfers\Exceptions\InvalidTransferTypeException;
use App\Services\Transfers\TransferRequestValidationService;
use App\TemplateResponse;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;

class WalletController
{
    private TransactionRepositoryInterface $transactionRepository;
    private WalletRepositoryInterface $walletRepository;
    private CurrencyRepositoryInterface $currencyRepository;

    public function __construct(
        TransactionRepositoryInterface $transactionRepository,
        WalletRepositoryInterface $walletRepository,
        CurrencyRepositoryInterface $currencyRepository

    )
    {
        $this->transactionRepository = $transactionRepository;
        $this->walletRepository = $walletRepository;
        $this->currencyRepository = $currencyRepository;
    }

    public function show(string $id): TemplateResponse
    {
        try {
            $wallet = $this->walletRepository->getWalletById($id);
        } catch (WalletNotFoundException $e) {
            return new TemplateResponse("wallets/show", ["wallet" => []]);
        }

        // This entire block until wallet data is for getting the percentage change in profit
        $tickers = [];
        foreach ($wallet->contents() as $money) {
            if ($money->ticker() !== "EUR") {
                $tickers[] = $money->ticker();
            }
        }
        $marketCurrencies = [];
        if (!empty($tickers)) {
            $currencyData = $this->currencyRepository->search($tickers);
            foreach ($currencyData as $currency) {
                $marketCurrencies[$currency->ticker()] = $currency;
            }
        }
        $percentages = [];
        foreach ($wallet->contents() as $money) {
            if ($money->ticker() === "EUR") {
                $percentages[] = "Not available";
                continue;
            }
            $average = $this->transactionRepository->getAveragePrice($wallet->userId(), $money);
            $marketRate = $marketCurrencies[$money->ticker()]->exchangeRate();
            $percentages[] = 100 * ($marketRate / $average) - 100;
        }

        $walletData = []; // TODO: add percentages as a setter for money?
        foreach ($wallet->contents() as $index => $money) {
            $walletData[] = [
                "ticker" => $money->ticker(),
                "amount" => $money->amount(),
                "profit" => $percentages[$index],
            ];
        }
        return new TemplateResponse("wallets/show", ["wallet" => $walletData]);
    }
}