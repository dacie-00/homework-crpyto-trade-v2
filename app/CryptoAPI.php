<?php

namespace App;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use stdClass;

class CryptoAPI
{
    private string $key;

    public function __construct(string $key)
    {
        $this->key = $key;
    }

    public function getTop(int $range): stdClass
    {
        $url = 'https://pro-api.coinmarketcap.com/v1/cryptocurrency/listings/latest';
        $parameters = [
            "start" => "1",
            "limit" => $range,
            "convert" => "EUR"
        ];

        $qs = http_build_query($parameters);
        $request = "{$url}?{$qs}";


        $guzzle = (new Client());
        try {
            $response = $guzzle->request("GET", $request, [
                "headers" => [
                    "Accepts" => "application/json",
                    "X-CMC_PRO_API_KEY" => $this->key,
                ],
            ]);
        } catch (ClientException $e) {
            $response = $e->getResponse();
            $responseBodyAsString = $response->getBody()->getContents();
            exit($responseBodyAsString);
        }

        return json_decode($response->getBody(), false, 512, JSON_THROW_ON_ERROR);
    }

}