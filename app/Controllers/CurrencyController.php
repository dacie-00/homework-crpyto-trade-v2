<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\Currency\CoinMarketCapApiCurrencyRepository;
use App\Repositories\Currency\CurrencyRepositoryInterface;

class CurrencyController
{
    private CoinMarketCapApiCurrencyRepository $currencyRepository;

    public function __construct()
    {
        $this->currencyRepository = new CoinMarketCapApiCurrencyRepository($_ENV["COIN_MARKET_CAP_API_KEY"]);
    }

    public function index()
    {
        if (isset($_GET["tickers"])) {
            $tickers = explode(",", $_GET["tickers"]);
            $tickers = array_map(static fn($value) => trim($value), $tickers);
            $currencies = $this->currencyRepository->search($tickers);
        } else {
            $currencies = $this->currencyRepository->getTop();
        }
        $currencyData = [];
        foreach ($currencies as $currency) {
            $currencyData[] = [
                "ticker" => $currency->definition()->getCurrencyCode(),
                "exchangeRate" => (string)$currency->exchangeRate(),
            ];
        }
        return ["currencies/index.html.twig", ["currencies" => $currencyData]];
    }

    public function show(string $ticker)
    {
        $codes = explode(",", $ticker);
        $codes = array_map(static fn($value) => trim($value), $codes);
        $currencies = $this->currencyRepository->search($codes);
        $currencyData = [];
        if (!empty($currencies)) {
            $currencyData = [
                "ticker" => $currencies[0]->definition()->getCurrencyCode(),
                "exchangeRate" => (string)$currencies[0]->exchangeRate(),
            ];
        }
        return ["currencies/show.html.twig", ["currency" => $currencyData]];
    }
}