<?php

namespace App;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;

class CryptoAPI
{
    public function run(){
        $url = 'https://sandbox-api.coinmarketcap.com/v1/cryptocurrency/listings/latest';
        $parameters = [
            "start" => "1",
            "limit" => "5000",
            "convert" => "USD"
        ];

        $qs = http_build_query($parameters);
        $request = "{$url}?{$qs}";


        $guzzle = (new Client());
        try {
            $response = $guzzle->request("GET", $request, [
                "headers" => [
                    "Accepts" => "application/json",
                    "X-CMC_PRO_API_KEY" => "b54bcf4d-1bca-4e8e-9a24-22ff2c3d462c",
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