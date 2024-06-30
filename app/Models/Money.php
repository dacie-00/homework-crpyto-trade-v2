<?php

namespace App\Models;

class Money
{
    private float $amount;
    private Currency $currency;

    public function __construct(float $amount, Currency $currency)
    {
        $this->amount = $amount;
        $this->currency = $currency;
    }

    public function amount(): float
    {
        return $this->amount;
    }

    public function currency(): Currency
    {
        return $this->currency;
    }

    public function ticker(): string
    {
        return $this->currency->ticker();
    }

    public function exchangeRate(): float
    {
        return $this->currency->exchangeRate();
    }
}