<?php

namespace App\Models;

class Currency
{
    private string $ticker;
    private float $exchangeRate;

    public function __construct(string $ticker, float $exchangeRate = 1)
    {
        $this->ticker = $ticker;
        $this->exchangeRate = $exchangeRate;
    }

    public function ticker(): string
    {
        return $this->ticker;
    }

    public function exchangeRate(): float
    {
        return $this->exchangeRate;
    }
}