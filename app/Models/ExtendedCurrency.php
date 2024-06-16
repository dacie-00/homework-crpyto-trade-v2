<?php

namespace App\Models;

use Brick\Math\BigDecimal;
use Brick\Money\Currency;

class ExtendedCurrency
{
    private Currency $definition;
    private BigDecimal $exchangeRate;

    public function __construct(string $ticker, BigDecimal $exchangeRate)
    {
        $this->definition = new Currency(
            $ticker,
            0,
            "",
            9);
        $this->exchangeRate = $exchangeRate;
    }

    public function definition(): Currency
    {
        return $this->definition;
    }

    public function ticker(): string
    {
        return $this->definition->getCurrencyCode();
    }

    public function exchangeRate(): BigDecimal
    {
        return $this->exchangeRate;
    }
}