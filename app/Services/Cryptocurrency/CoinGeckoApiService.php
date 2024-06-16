<?php
declare(strict_types=1);

namespace App\Services\Cryptocurrency;

use App\Models\ExtendedCurrency;
use Brick\Math\BigDecimal;
use Brick\Money\Currency;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;

class CoinGeckoApiService implements CryptocurrencyApiServiceInterface
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
     * @return ExtendedCurrency[]
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
            $responseBody = json_decode($response->getBody()->getContents(), false, 512, JSON_THROW_ON_ERROR);
            exit("CoinGecko Error - {$responseBody->error}\n");
        }

        $currencyResponse = json_decode($response->getBody()->getContents(), false, 512, JSON_THROW_ON_ERROR);

        $currencies = [];
        foreach ($currencyResponse as $currency) {
            $currencies[] = new ExtendedCurrency(
                $currency->symbol,
                BigDecimal::of(1 / $currency->current_price)
            );
        }
        return $currencies;
    }

    private function getIdFromCurrencyCode(string $currencyCode): ?string
    {
        $url = "search";

        $parameters = [
            "query" => $currencyCode,
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
            $responseBody = json_decode($response->getBody()->getContents(), false, 512, JSON_THROW_ON_ERROR);
            exit("CoinGecko Error - {$responseBody->error}\n");
        }

        $response = json_decode($response->getBody()->getContents(), false, 512, JSON_THROW_ON_ERROR);
        if (!isset($response->coins)) {
            return null;
        }
        return ($response->coins[0]->id);
    }

    /**
     * @return ExtendedCurrency[]
     */
    public function search(array $currencyCodes): array
    {
        $url = "coins/markets";

        $names = [];
        foreach ($currencyCodes as $currencyCode) {
            if ($name = $this->getIdFromCurrencyCode($currencyCode)) {
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
            $responseBody = json_decode($response->getBody()->getContents(), false, 512, JSON_THROW_ON_ERROR);
            exit("CoinGecko Error - {$responseBody->error}\n");
        }

        $currencyResponse = json_decode($response->getBody()->getContents(), false, 512, JSON_THROW_ON_ERROR);
        $currencies = [];
        foreach ($currencyCodes as $currencyCode) {
            foreach ($currencyResponse as $currency) {
                if (strtoupper($currency->symbol) === $currencyCode) {
                    $currencies[] = new ExtendedCurrency(
                        strtoupper($currency->symbol),
                        BigDecimal::of(1 / $currency->current_price)
                    );
                }
            }
        }
        return $currencies;
    }
}