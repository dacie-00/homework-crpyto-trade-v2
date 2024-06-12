<?php
declare(strict_types=1);

namespace App;

use Brick\Math\BigDecimal;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use stdClass;

class CoinMarketCapAPI implements CryptoApi
{
    private string $key;

    public function __construct(string $key)
    {
        $this->key = $key;
    }

    /**
     * @return Currency[]
     */
    public function getTop(int $page = 1, int $currenciesPerPage = 10): array
    {
        $url = "https://pro-api.coinmarketcap.com/v1/cryptocurrency/listings/latest";
        $parameters = [
            "start" => "1",
            "limit" => $range,
            "convert" => "EUR",
        ];

        $queryString = http_build_query($parameters);

        $guzzle = new Client();
        try {
            $response = $guzzle->request("GET", "$url?$queryString", [
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
        $url = "https://pro-api.coinmarketcap.com/v1/cryptocurrency/quotes/latest";
        $parameters = [
            "symbol" => implode(",", $currencyCodes),
            "convert" => "EUR",
        ];

        $queryString = http_build_query($parameters);

        $guzzle = new Client();
        try {
            $response = $guzzle->request("GET", "$url?$queryString", [
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