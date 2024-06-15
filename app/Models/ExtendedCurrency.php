<?php

namespace App\Models;

use Brick\Math\BigDecimal;
use Brick\Money\Currency;

class ExtendedCurrency
{
    private Currency $definition;
    private BigDecimal $exchangeRate;

    public function __construct(Currency $definition, BigDecimal $exchangeRate)
    {
        $this->definition = $definition;
        $this->exchangeRate = $exchangeRate;
    }

    public function definition(): Currency
    {
        return $this->definition;
    }

    public function code(): string
    {
        return $this->definition->getCurrencyCode();
    }

    public function name(): string
    {
        return $this->definition->getName();
    }

    public function exchangeRate(): BigDecimal
    {
        return $this->exchangeRate;
    }
}