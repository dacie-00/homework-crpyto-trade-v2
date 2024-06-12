<?php

namespace App;

use Brick\Math\BigDecimal;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;

class CoinGeckoAPI implements CryptoApi
{
    private string $apiKey;

    public function __construct(string $apiKey)
    {
        $this->apiKey = $apiKey;
    }


    /**
     * @return Currency[]
     */
    public function getTop(int $page = 1, int $currenciesPerPage = 10): array
    {
        $url = "https://api.coingecko.com/api/v3/coins/markets";

        $parameters = [
            "page" => $page,
            "per_page" => $currenciesPerPage,
            "vs_currency" => "eur",
        ];

        $queryString = http_build_query($parameters);

        $guzzle = new Client();
        try {
            $response = $guzzle->request("GET", "$url?$queryString", [
                "headers" => [
                    "Accepts" => "application/json",
                    "x-cg-demo-api-key" => $this->apiKey,
                ],
            ]);
        } catch (ClientException $e) {
            $response = $e->getResponse();
            $responseBody = json_decode($response->getBody()->getContents(), false, 512, JSON_THROW_ON_ERROR);
            exit("CoinGecko Error - {$responseBody->error}\n");
        }

        $currencyResponse = json_decode($response->getBody()->getContents(), false, 512, JSON_THROW_ON_ERROR);

        $currencies = [];
        foreach ($currencyResponse as $currency) {
            $currencies[] = new Currency(
                new \Brick\Money\Currency(
                    $currency->symbol,
                    -1, // TODO: Is there a way to fix this? Good enough?
                    $currency->name,
                    9
                ),
                BigDecimal::of(1 / $currency->current_price)
            );
        }
        return $currencies;
    }

    private function getIdFromCurrencyCode(string $currencyCode): ?string
    {
        $url = "https://api.coingecko.com/api/v3/search";

        $parameters = [
            "query" => $currencyCode,
            "vs_currency" => "eur",
        ];

        $queryString = http_build_query($parameters);

        $guzzle = new Client();
        try {
            $response = $guzzle->request("GET", "$url?$queryString", [
                "headers" => [
                    "Accepts" => "application/json",
                    "x-cg-demo-api-key" => $this->apiKey,
                ],
            ]);
        } catch (ClientException $e) {
            $response = $e->getResponse();
            $responseBody = json_decode($response->getBody()->getContents(), false, 512, JSON_THROW_ON_ERROR);
            exit("CoinGecko Error - {$responseBody->error}\n");
        }

        $response = json_decode($response->getBody()->getContents(), false, 512, JSON_THROW_ON_ERROR);
        if (!isset($response->coins)) {
            return null;
        }
        return($response->coins[0]->id);
    }

    public function search(array $currencyCodes): array
    {
        $url = "https://api.coingecko.com/api/v3/coins/markets";

        $names = [];
        foreach($currencyCodes as $currencyCode) {
            if ($name = $this->getIdFromCurrencyCode($currencyCode)) {
                $names[] = $name;
            };
        }

        $parameters = [
            "per_page" => 10,
            "vs_currency" => "eur",
            "ids" => implode(",", $names),
        ];

        $queryString = http_build_query($parameters);

        $guzzle = new Client();
        try {
            $response = $guzzle->request("GET", "$url?$queryString", [
                "headers" => [
                    "Accepts" => "application/json",
                    "x-cg-demo-api-key" => $this->apiKey,
                ],
            ]);
        } catch (ClientException $e) {
            $response = $e->getResponse();
            $responseBody = json_decode($response->getBody()->getContents(), false, 512, JSON_THROW_ON_ERROR);
            exit("CoinGecko Error - {$responseBody->error}\n");
        }

        $currencyResponse = json_decode($response->getBody()->getContents(), false, 512, JSON_THROW_ON_ERROR);
        $currencies = [];
        foreach ($currencyCodes as $currencyCode) {
            foreach($currencyResponse as $currency) {
                if (strtoupper($currency->symbol) === $currencyCode) {
                    $currencies[] = new Currency(
                        new \Brick\Money\Currency(
                            strtoupper($currency->symbol),
                            -1,
                            $currency->name,
                            9
                        ),
                        BigDecimal::of(1 / $currency->current_price)
                    );
                }
            }
        }
        return $currencies;
    }
}