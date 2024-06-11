<?php
declare(strict_types=1);

namespace App;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use stdClass;

class CoinMarketCapAPI
{
    private string $key;

    public function __construct(string $key)
    {
        $this->key = $key;
    }

    public function getTop(int $range): stdClass
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

        return json_decode($response->getBody()->getContents(), false, 512, JSON_THROW_ON_ERROR);
    }
}