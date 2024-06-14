<?php
declare(strict_types=1);

namespace App\CryptoApi;

use App\Currency;
use Brick\Math\BigDecimal;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;

class CoinMarketCapAPI implements CryptoApi
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
            "start" => $page,
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
            $responseBody = json_decode($response->getBody()->getContents(), false, 512, JSON_THROW_ON_ERROR);
            exit("CoinMarketCap Error - {$responseBody->status->error_message}\n");
        }

        $currencyResponse = json_decode($response->getBody()->getContents(), false, 512, JSON_THROW_ON_ERROR);

        $currencies = [];
        foreach ($currencyResponse->data as $currency) {
            $currencies[] = new Currency(
                new \Brick\Money\Currency(
                    $currency->symbol,
                    $currency->id,
                    $currency->name,
                    9
                ),
                BigDecimal::of(1 /$currency->quote->EUR->price)
            );
        }
        return $currencies;
    }

    public function search(array $currencyCodes): array
    {
        $url = "cryptocurrency/quotes/latest";
        $parameters = [
            "symbol" => implode(",", $currencyCodes),
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
            $responseBody = json_decode($response->getBody()->getContents(), false, 512, JSON_THROW_ON_ERROR);
            exit("CoinMarketCap Error - {$responseBody->status->error_message}\n");
        }

        $response = json_decode($response->getBody()->getContents(), false, 512, JSON_THROW_ON_ERROR);
        $currencies = [];
        foreach ($currencyCodes as $currencyCode) {
            if (isset($response->data->$currencyCode)) {
                $currency = $response->data->$currencyCode;
                $currencies[] = new Currency(
                    new \Brick\Money\Currency(
                        $currency->symbol,
                        $currency->id,
                        $currency->name,
                        9
                    ),
                    BigDecimal::of(1 / $currency->quote->EUR->price)
                );
            }
        }
        return $currencies;
    }
}