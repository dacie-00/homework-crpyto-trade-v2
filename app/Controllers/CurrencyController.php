<?php
declare(strict_types=1);

namespace App\Controllers;

use App\RedirectResponse;
use App\Repositories\Currency\CoinMarketCapApiCurrencyRepository;
use App\Repositories\Currency\Exceptions\CurrencyNotFoundException;
use App\TemplateResponse;

class CurrencyController
{
    private CoinMarketCapApiCurrencyRepository $currencyRepository;

    public function __construct()
    {
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
        $amount = $_POST["amount"];
        // TODO: implement buy

        return new RedirectResponse("/wallets/foobarWallet");
    }
}