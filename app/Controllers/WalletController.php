<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Currency;
use App\Models\Money;
use App\Repositories\Currency\CoinMarketCapApiCurrencyRepository;
use App\Repositories\TransactionRepository;
use App\Repositories\Wallet\Exceptions\WalletNotFoundException;
use App\Repositories\Wallet\WalletRepository;
use App\Services\BuyService;
use App\Services\Exceptions\InsufficientMoneyException;
use App\Services\Exceptions\TransactionFailedException;
use App\Services\SellService;
use App\Services\Transfers\Exceptions\InvalidTransferAmountException;
use App\Services\Transfers\Exceptions\InvalidTransferCurrencyTickerException;
use App\Services\Transfers\Exceptions\InvalidTransferTypeException;
use App\Services\Transfers\TransferRequestValidationService;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;

class WalletController
{
    private TransactionRepository $transactionRepository;
    private WalletRepository $walletRepository;
    private Connection $connection;
    private CoinMarketCapApiCurrencyRepository $currencyRepository;

    public function __construct()
    {
        $connectionParams = [
            "driver" => "pdo_sqlite",
            "path" => "storage/database.sqlite",
        ];
        $this->connection = DriverManager::getConnection($connectionParams);

        $this->transactionRepository = new TransactionRepository($this->connection);
        $this->walletRepository = new WalletRepository($this->connection);
        $this->currencyRepository = new CoinMarketCapApiCurrencyRepository($_ENV["COIN_MARKET_CAP_API_KEY"]);
    }

    public function show(string $id): array
    {
        try {
            $wallet = $this->walletRepository->getWalletById($id);
        } catch (WalletNotFoundException $e) {
            return ["wallets/show.html.twig", ["wallet" => []]];
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
            $percentages[] = 100 * ($marketRate / $average - 1);
        }

        $walletData = []; // TODO: add percentages as a setter for money?
        foreach ($wallet->contents() as $index => $money) {
            $walletData[] = [
                "ticker" => $money->ticker(),
                "amount" => $money->amount(),
                "profit" => $percentages[$index],
            ];
        }
        return ["wallets/show.html.twig", ["wallet" => $walletData]];
    }

    public function transfer(string $walletId): array
    {
        try {
            (new TransferRequestValidationService())->validate($_POST);
        } catch (InvalidTransferTypeException|InvalidTransferAmountException|InvalidTransferCurrencyTickerException $e) {
            return ["wallets/transfer.html.twig", ["error" => $e->getMessage()]];
        }
        try {
            if ($_POST["type"] === "buy") {
                (new BuyService(
                    $this->connection,
                    $this->transactionRepository,
                    $this->walletRepository,
                    (new CoinMarketCapApiCurrencyRepository($_ENV["COIN_MARKET_CAP_API_KEY"]))))
                    ->execute(
                        $walletId,
                        new Money(
                            (float)$_POST["amount"],
                            new Currency($_POST["currency"])
                        )
                    );
            } elseif ($_POST["type"] === "sell") {
                (new SellService(
                    $this->connection,
                    $this->transactionRepository,
                    $this->walletRepository,
                    (new CoinMarketCapApiCurrencyRepository($_ENV["COIN_MARKET_CAP_API_KEY"]))))
                    ->execute(
                        $walletId,
                        new Money(
                            (float)$_POST["amount"],
                            new Currency($_POST["currency"])
                        )
                    );
            }
        } catch (InsufficientMoneyException|TransactionFailedException $e) {
            return ["wallets/transfer.html.twig", ["error" => $e->getMessage()]];
        }

        return ["wallets/transfer.html.twig",
            [
                "id" => $walletId,
                "type" => $_POST["type"],
                "amount" => $_POST["amount"],
                "currency" => $_POST["currency"],
            ],
        ];
    }
}