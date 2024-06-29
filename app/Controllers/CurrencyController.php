<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\Currency\CoinMarketCapApiCurrencyRepository;
use App\Repositories\Currency\Exceptions\CurrencyNotFoundException;

class CurrencyController
{
    private CoinMarketCapApiCurrencyRepository $currencyRepository;

    public function __construct()
    {
        $this->currencyRepository = new CoinMarketCapApiCurrencyRepository($_ENV["COIN_MARKET_CAP_API_KEY"]);
    }

    public function index(): array
    {
        if (isset($_GET["tickers"])) {
            $tickers = explode(",", $_GET["tickers"]);
            $tickers = array_map(static fn($value) => trim($value), $tickers);
            try {
                $currencies = $this->currencyRepository->search($tickers);
            } catch (CurrencyNotFoundException $e) {
                return ["currencies/index", ["query" => $_GET["tickers"]]];
            }
        } else {
            $currencies = $this->currencyRepository->getTop();
        }
        return ["currencies/index", ["currencies" => $currencies]];
    }

    public function show(string $ticker): array
    {
        $codes = explode(",", $ticker);
        $codes = array_map(static fn($value) => trim($value), $codes);
        try {
            $currencies = $this->currencyRepository->search($codes);
        } catch (CurrencyNotFoundException $e) {
            return ["currencies/show", ["query" => $ticker]];
        }

        return ["currencies/show", ["query" => $ticker, "currency" => $currencies]];
    }
}