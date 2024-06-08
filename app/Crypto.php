<?php

namespace App;

class Crypto
{
    private int $id;
    private string $name;
    private string $symbol;
    private string $price;

    public function __construct(int $id, string $name, string $symbol, string $price)
    {
        $this->id = $id;
        $this->name = $name;
        $this->symbol = $symbol;
        $this->price = $price;
    }
}