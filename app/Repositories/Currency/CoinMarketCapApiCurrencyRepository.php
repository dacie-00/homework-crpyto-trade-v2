<?php
declare(strict_types=1);

namespace App\Repositories\Currency;

use App\Exceptions\FailedHttpRequestException;
use App\Models\Currency;
use App\Repositories\Currency\Exceptions\CurrencyNotFoundException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;

class CoinMarketCapApiCurrencyRepository implements CurrencyRepositoryInterface
{
    private string $key;
    private Client $client;

    public function __construct(string $key)
    {
        $this->key = $key;
        $this->client = new Client([
            "base_uri" => "https://pro-api.coinmarketcap.com/v1/",
        ]);
    }

    /**
     * @return Currency[]
     */
    public function getTop(int $page = 1, int $currenciesPerPage = 10): array
    {
        $url = "cryptocurrency/listings/latest";
        $parameters = [
            "start" => 1 + $page * $currenciesPerPage - $currenciesPerPage,
            "limit" => $currenciesPerPage,
            "convert" => "EUR",
        ];

        $queryString = http_build_query($parameters);

        try {
            $response = $this->client->request("GET", "$url?$queryString", [
                "headers" => [
                    "Accepts" => "application/json",
                    "X-CMC_PRO_API_KEY" => $this->key,
                ],
            ]);
        } catch (ClientException $e) {
            $response = $e->getResponse();
            $responseBody = json_decode(
                $response->getBody()->getContents(),
                false,
                512,
                JSON_THROW_ON_ERROR
            );
            throw new FailedHttpRequestException("CoinMarketCap Error - {$responseBody->status->error_message}\n");
        }

        $currencyResponse = json_decode(
            $response->getBody()->getContents(),
            false,
            512,
            JSON_THROW_ON_ERROR
        );

        $currencies = [];
        foreach ($currencyResponse->data as $currency) {
            $currencies[] = new Currency(
                $currency->symbol,
                $currency->quote->EUR->price
            );
        }
        return $currencies;
    }

    /**
     * @return Currency[]
     */
    public function search(array $tickers): array
    {
        $tickers = array_map(fn($code) => strtoupper($code), $tickers);
        $url = "cryptocurrency/quotes/latest";
        $parameters = [
            "symbol" => implode(",", $tickers),
            "convert" => "EUR",
        ];

        $queryString = http_build_query($parameters);

        try {
            $response = $this->client->request("GET", "$url?$queryString", [
                "headers" => [
                    "Accepts" => "application/json",
                    "X-CMC_PRO_API_KEY" => $this->key,
                ],
            ]);
        } catch (ClientException $e) {
            $response = $e->getResponse();
            $responseBody = json_decode(
                $response->getBody()->getContents(),
                false,
                512,
                JSON_THROW_ON_ERROR
            );
            throw new FailedHttpRequestException("CoinMarketCap Error - {$responseBody->status->error_message}\n");
        }

        $response = json_decode(
            $response->getBody()->getContents(),
            false,
            512,
            JSON_THROW_ON_ERROR
        );
        if (!get_object_vars($response->data)) {
            $codes = implode(",", $tickers);
            throw new CurrencyNotFoundException("No data found for currency(-ies) $codes.\n");
        }
        $currencies = [];
        foreach ($tickers as $ticker) {
            if (isset($response->data->$ticker)) {
                $currency = $response->data->$ticker;
                if (!$currency->is_active) {
                    continue;
                }
                $currencies[] = new Currency(
                    $currency->symbol,
                    $currency->quote->EUR->price
                );
            }
        }
        return $currencies;
    }
}