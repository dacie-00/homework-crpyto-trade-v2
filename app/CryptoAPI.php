<?php

namespace App;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;

class CryptoAPI
{
    private string $key;

    public function __construct(string $key)
    {
        $this->key = $key;
    }

    public function run(){
        $url = 'https://pro-api.coinmarketcap.com/v1/cryptocurrency/listings/latest';
        $parameters = [
            "start" => "1",
            "limit" => "10",
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

        print_r(json_decode($response->getBody(), false, JSON_THROW_ON_ERROR));
    }

}