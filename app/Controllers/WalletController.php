<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\Currency\CoinMarketCapApiCurrencyRepository;
use App\Repositories\TransactionRepository;
use App\Repositories\Wallet\Exceptions\WalletNotFoundException;
use App\Repositories\Wallet\WalletRepository;
use App\Services\BuyService;
use App\Services\Exceptions\InsufficientMoneyException;
use App\Services\SellService;
use App\Services\Transfers\Exceptions\InvalidTransferAmountException;
use App\Services\Transfers\Exceptions\InvalidTransferCurrencyTickerException;
use App\Services\Transfers\Exceptions\InvalidTransferTypeException;
use App\Services\Transfers\TransferRequestValidationService;
use App\Services\WalletService;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
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
        $percentages = [];
        $currencyCodes = array_map(fn($money) => $money->getCurrency()->getCurrencyCode(), $wallet->contents());
        $currencyData = $this->currencyRepository->search($currencyCodes);
        $marketCurrencies = [];
        foreach ($currencyData as $currency) {
            $marketCurrencies[$currency->definition()->getCurrencyCode()] = $currency;
        }

        foreach ($wallet->contents() as $money) {
            if ($money->getCurrency()->getCurrencyCode() === "EUR") {
                $percentages[] = "Not available";
                continue;
            }
            $average = $this->transactionRepository->getAveragePrice($wallet->userId(), $money->getCurrency(), $money->getAmount());
            $marketRate = BigDecimal::one()
                ->dividedBy(
                    $marketCurrencies[$money->getCurrency()->getCurrencyCode()]->exchangeRate(),
                    9,
                    RoundingMode::DOWN
                );
            $percentages[] =
                Bigdecimal::of(100)
                    ->multipliedBy(
                        $marketRate->dividedBy($average, 9, RoundingMode::DOWN)
                            ->minus(BigDecimal::one()))->toFloat();
        }

        $walletData = [];
        foreach ($wallet->contents() as $index => $entry) {
            $walletData[] = [
                "ticker" => $entry->getCurrency(),
                "amount" => $entry->getAmount(),
                "profit" => $percentages[$index],
            ];
        }
        return ["wallets/show.html.twig", ["wallet" => $walletData]];
    }

    public function transfer(string $walletId): array
    {
        $error = "";
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
                        (float)$_POST["amount"],
                        $_POST["currency"],
                    );
            } elseif ($_POST["type"] === "sell") {
                (new SellService(
                    $this->connection,
                    $this->transactionRepository,
                    $this->walletRepository,
                    (new CoinMarketCapApiCurrencyRepository($_ENV["COIN_MARKET_CAP_API_KEY"]))))
                    ->execute(
                        $walletId,
                        (float)$_POST["amount"],
                        $_POST["currency"],
                    );
            }
        } catch (InsufficientMoneyException $e) {
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