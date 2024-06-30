<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Currency;
use App\Models\Money;
use App\RedirectResponse;
use App\Repositories\Currency\CoinMarketCapApiCurrencyRepository;
use App\Repositories\Currency\Exceptions\CurrencyNotFoundException;
use App\Repositories\TransactionRepository;
use App\Repositories\UserRepository;
use App\Repositories\Wallet\WalletRepository;
use App\Services\BuyService;
use App\Services\Exceptions\InsufficientMoneyException;
use App\Services\Exceptions\TransactionFailedException;
use App\Services\SellService;
use App\TemplateResponse;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;

class CurrencyController
{
    private CoinMarketCapApiCurrencyRepository $currencyRepository;
    private Connection $connection;
    private TransactionRepository $transactionRepository;
    private WalletRepository $walletRepository;

    public function __construct()
    {
        $connectionParams = [
            "driver" => "pdo_sqlite",
            "path" => "storage/database.sqlite",
        ];
        $this->connection = DriverManager::getConnection($connectionParams);

        $this->transactionRepository = new TransactionRepository($this->connection);
        $this->walletRepository = new walletRepository($this->connection);
        $this->currencyRepository = new CoinMarketCapApiCurrencyRepository($_ENV["COIN_MARKET_CAP_API_KEY"]);
    }

    public function index(): TemplateResponse
    {
        if (isset($_GET["tickers"])) {
            $tickers = explode(",", $_GET["tickers"]);
            $tickers = array_map(static fn($value) => trim($value), $tickers);
            try {
                $currencies = $this->currencyRepository->search($tickers);
            } catch (CurrencyNotFoundException $e) {
                return new TemplateResponse("currencies/index", ["query" => $_GET["tickers"]]);
            }
        } else {
            $currencies = $this->currencyRepository->getTop();
        }
        return new TemplateResponse("currencies/index", ["currencies" => $currencies]);
    }

    public function show(string $ticker): TemplateResponse
    {
        $codes = explode(",", $ticker);
        $codes = array_map(static fn($value) => trim($value), $codes);
        try {
            [$currency] = $this->currencyRepository->search($codes);
        } catch (CurrencyNotFoundException $e) {
            return new TemplateResponse("currencies/show", ["query" => $ticker]);
        }

        return new TemplateResponse("currencies/show", ["query" => $ticker, "currency" => $currency]);
    }

    public function buy(string $ticker): RedirectResponse
    {
        $amount = (float)$_POST["amount"];

        try {
            (new BuyService(
                $this->connection,
                $this->transactionRepository,
                $this->walletRepository,
                (new CoinMarketCapApiCurrencyRepository($_ENV["COIN_MARKET_CAP_API_KEY"]))))
                ->execute(
                    "foobarWallet",
                    new Money(
                        $amount,
                        new Currency($ticker)
                    )
                );
        } catch (InsufficientMoneyException|TransactionFailedException $e) {
            return new RedirectResponse("/wallets/foobarWallet"); // TODO: figure out how to display error
        }
        return new RedirectResponse("/wallets/foobarWallet");
    }


    public function sell(string $ticker): RedirectResponse
    {
        $amount = (float)$_POST["amount"];

        try {
            (new SellService(
                $this->connection,
                $this->transactionRepository,
                $this->walletRepository,
                (new CoinMarketCapApiCurrencyRepository($_ENV["COIN_MARKET_CAP_API_KEY"]))))
                ->execute(
                    "foobarWallet",
                    new Money(
                        $amount,
                        new Currency($ticker)
                    )
                );
        } catch (InsufficientMoneyException|TransactionFailedException $e) {
            return new RedirectResponse("/wallets/foobarWallet"); // TODO: figure out how to display error
        }
        return new RedirectResponse("/wallets/foobarWallet");
    }
}