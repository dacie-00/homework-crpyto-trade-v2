<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\Currency\CurrencyRepositoryInterface;
use App\Repositories\Transaction\TransactionRepositoryInterface;
use App\Repositories\Wallet\Exceptions\WalletNotFoundException;
use App\Repositories\Wallet\WalletRepositoryInterface;
use App\TemplateResponse;

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

        // TODO: move this percentage calculation to new service?
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

        return new TemplateResponse("wallets/show", [
            "wallet" => $wallet->contents(), "percentages" => $percentages,
        ]);
    }
}