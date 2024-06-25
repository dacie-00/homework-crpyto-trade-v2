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
        $currencies = $this->currencyRepository->getTop();
        $currencyData = [];
        foreach ($currencies as $currency) {
            $currencyData[] = [
                "ticker" => $currency->definition()->getCurrencyCode(),
                "exchangeRate" => (string)$currency->exchangeRate(),
            ];
        }
        return $currencyData;
    }

    public function show()
    {
        return "show single currency page";
    }
}