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
        return "index page";
    }

    public function show()
    {
        return "show single currency page";
    }
}