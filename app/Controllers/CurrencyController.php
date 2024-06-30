<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Currency;
use App\Models\Money;
use App\RedirectResponse;
use App\Repositories\Currency\CurrencyRepositoryInterface;
use App\Repositories\Currency\Exceptions\CurrencyNotFoundException;
use App\Services\BuyService;
use App\Services\Exceptions\InsufficientMoneyException;
use App\Services\Exceptions\TransactionFailedException;
use App\Services\SellService;
use App\TemplateResponse;

class CurrencyController
{
    private CurrencyRepositoryInterface $currencyRepository;
    private BuyService $buyService;
    private SellService $sellService;

    public function __construct(
        CurrencyRepositoryInterface $currencyRepository,
        BuyService $buyService,
        SellService $sellService
    ) {
        $this->currencyRepository = $currencyRepository;
        $this->buyService = $buyService;
        $this->sellService = $sellService;
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
        $amount = (float)$_POST["amount"]; // TODO: validate

        try {
            $this->buyService->execute(
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
            $this->sellService->execute(
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