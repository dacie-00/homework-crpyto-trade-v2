<?php
declare(strict_types=1);

namespace App\Repositories\Currency;

use App\Exceptions\FailedHttpRequestException;
use App\Models\Currency;
use App\Repositories\Currency\Exceptions\CurrencyNotFoundException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;

class CoinGeckoApiCurrencyRepository implements CurrencyRepositoryInterface
{
    private string $apiKey;
    private Client $client;

    public function __construct(string $apiKey)
    {
        $this->apiKey = $apiKey;
        $this->client = new Client([
            "base_uri" => "https://api.coingecko.com/api/v3/",
        ]);
    }

    /**
     * @return Currency[]
     */
    public function getTop(int $page = 1, int $currenciesPerPage = 10): array
    {
        $url = "coins/markets";

        $parameters = [
            "page" => $page,
            "per_page" => $currenciesPerPage,
            "vs_currency" => "eur",
        ];

        $queryString = http_build_query($parameters);

        try {
            $response = $this->client->request("GET", "$url?$queryString", [
                "headers" => [
                    "Accepts" => "application/json",
                    "x-cg-demo-api-key" => $this->apiKey,
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
            throw new FailedHttpRequestException("CoinGecko Error - {$responseBody->error}\n");
        }

        $currencyResponse = json_decode(
            $response->getBody()->getContents(),
            false,
            512,
            JSON_THROW_ON_ERROR
        );

        $currencies = [];
        foreach ($currencyResponse as $currency) {
            $currencies[] = new Currency(
                $currency->symbol,
                $currency->current_price
            );
        }
        return $currencies;
    }

    private function getIdFromticker(string $ticker): ?string
    {
        $url = "search";

        $parameters = [
            "query" => $ticker,
            "vs_currency" => "eur",
        ];

        $queryString = http_build_query($parameters);

        try {
            $response = $this->client->request("GET", "$url?$queryString", [
                "headers" => [
                    "Accepts" => "application/json",
                    "x-cg-demo-api-key" => $this->apiKey,
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
            throw new FailedHttpRequestException("CoinGecko Error - {$responseBody->error}\n");
        }

        $response = json_decode(
            $response->getBody()->getContents(),
            false,
            512,
            JSON_THROW_ON_ERROR
        );
        if (!isset($response->coins)) {
            return null;
        }
        return ($response->coins[0]->id);
    }

    /**
     * @return Currency[]
     */
    public function search(array $tickers): array
    {
        $url = "coins/markets";

        $names = [];
        foreach ($tickers as $ticker) {
            if ($name = $this->getIdFromticker($ticker)) {
                $names[] = $name;
            }
        }

        $parameters = [
            "per_page" => 10,
            "vs_currency" => "eur",
            "ids" => implode(",", $names),
        ];

        $queryString = http_build_query($parameters);

        try {
            $response = $this->client->request("GET", "$url?$queryString", [
                "headers" => [
                    "Accepts" => "application/json",
                    "x-cg-demo-api-key" => $this->apiKey,
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
            throw new FailedHttpRequestException("CoinGecko Error - {$responseBody->error}\n");
        }

        $currencyResponse = json_decode(
            $response->getBody()->getContents(),
            false,
            512,
            JSON_THROW_ON_ERROR
        );
        if (empty($currencyResponse)) {
            $codes = implode(",", $tickers);
            throw new CurrencyNotFoundException("No data found for currency(-ies) $codes.\n");
        }

        $currencies = [];
        foreach ($tickers as $ticker) {
            foreach ($currencyResponse as $currency) {
                if (strtoupper($currency->symbol) === $ticker) {
                    $currencies[] = new Currency(
                        strtoupper($currency->symbol),
                        $currency->current_price
                    );
                }
            }
        }
        return $currencies;
    }
}