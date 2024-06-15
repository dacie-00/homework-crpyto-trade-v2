<?php

namespace App\Models;

use Brick\Math\BigDecimal;
use JsonSerializable;

class ExtendedCurrency implements JsonSerializable
{
    private \Brick\Money\Currency $definition;
    private BigDecimal $exchangeRate;

    public function __construct(\Brick\Money\Currency $definition, BigDecimal $exchangeRate)
    {
        $this->definition = $definition;
        $this->exchangeRate = $exchangeRate;
    }

    public static function fromArray(array $currency): ExtendedCurrency
    {
        return new self(
            new \Brick\Money\Currency(
                $currency["code"],
                0,
                $currency["name"],
                9
            ),
            BigDecimal::of($currency["exchange_rate"])
        );
    }

    public function definition(): \Brick\Money\Currency
    {
        return $this->definition;
    }

    public function code(): string
    {
        return $this->definition->getCurrencyCode();
    }

    public function numericCode(): string
    {
        return $this->definition->getNumericCode();
    }

    public function name(): string
    {
        return $this->definition->getName();
    }

    public function exchangeRate(): BigDecimal
    {
        return $this->exchangeRate;
    }

    public function jsonSerialize(): array
    {
        return [
            "name" => $this->name(),
            "code" => $this->code(),
            "numericCode" => $this->numericCode(),
            "exchangeRate" => $this->exchangeRate()
        ];
    }
}