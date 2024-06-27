<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\Currency\CoinMarketCapApiCurrencyRepository;
use App\Repositories\TransactionRepository;
use App\Repositories\Wallet\Exceptions\WalletNotFoundException;
use App\Repositories\Wallet\WalletRepository;
use App\Services\BuyService;
use App\Services\SellService;
use App\Services\Transfers\Exceptions\InvalidTransferAmountException;
use App\Services\Transfers\Exceptions\InvalidTransferCurrencyTickerException;
use App\Services\Transfers\Exceptions\InvalidTransferTypeException;
use App\Services\Transfers\TransferRequestValidationService;
use App\Services\WalletService;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;

class WalletController
{
    private TransactionRepository $transactionRepository;
    private WalletRepository $walletRepository;
    private Connection $connection;

    public function __construct()
    {
        $connectionParams = [
            "driver" => "pdo_sqlite",
            "path" => "storage/database.sqlite",
        ];
        $this->connection = DriverManager::getConnection($connectionParams);

        $this->transactionRepository = new TransactionRepository($this->connection);
        $this->walletRepository = new WalletRepository($this->connection);
    }

    public function show(string $id): array
    {
        try {
            $wallet = $this->walletRepository->getWalletById($id);
        } catch (WalletNotFoundException $e) {
            return ["wallets/show.html.twig", ["wallet" => []]];
        }
        $walletData = [];
        foreach ($wallet->contents() as $entry) {
            $walletData[] = [
                "ticker" => $entry->getCurrency(),
                "amount" => $entry->getAmount(),
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
            var_dump($_POST);
            return ["wallets/transfer.html.twig", ["error" => $e->getMessage()]];
        }
        if ($_POST["type"] === "buy") {
            (new BuyService(
                $this->connection,
                (new WalletService($this->walletRepository)),
                $this->transactionRepository,
                (new CoinMarketCapApiCurrencyRepository($_ENV["COIN_MARKET_CAP_API_KEY"]))))
                ->execute(
                    $this->walletRepository->getWalletById($walletId),
                    (float)$_POST["amount"],
                    $_POST["currency"],
                );
        } elseif ($_POST["type"] === "sell") {
            (new SellService(
                $this->connection,
                (new WalletService($this->walletRepository)),
                $this->transactionRepository,
                (new CoinMarketCapApiCurrencyRepository($_ENV["COIN_MARKET_CAP_API_KEY"]))))
                ->execute(
                    $this->walletRepository->getWalletById($walletId),
                    (float)$_POST["amount"],
                    $_POST["currency"],
                );
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