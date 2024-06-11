<?php

namespace App;

use Brick\Money\Currency;
use JsonSerializable;
use OutOfBoundsException;
use stdClass;

class CurrencyRepository implements JsonSerializable
{
    /**
     * @var Currency[]
     */
    private array $currencies = [];

    /**
     * @param stdClass[] $currencies
     */
    public function __construct(?array $currencies = null)
    {
        if (!$currencies) {
            return;
        }
        foreach ($currencies as $currency) {
            $this->currencies[] = new Currency(
                $currency->currencyCode,
                $currency->numericCode,
                $currency->name,
                $currency->defaultFractionDigits
            );
        }
    }

    public function add(Currency $currency): void
    {
        $this->currencies[$currency->getCurrencyCode()] = $currency;
    }

    public function getAll(): array
    {
        return $this->currencies;
    }

    public function getCurrencyByName(string $name): Currency
    {
        foreach ($this->currencies as $currency) {
            if ($currency->getName() === $name) {
                return $currency;
            }
        }
        throw new OutOfBoundsException("Currency not found ($name)");
    }

    public function getCurrencyByCode(string $currencyCode): Currency
    {
        foreach ($this->currencies as $currency) {
            if ($currency->getCurrencyCode() === $currencyCode) {
                return $currency;
            }
        }
        throw new OutOfBoundsException("Currency not found ($currencyCode)");
    }

    public function jsonSerialize(): array
    {
        $serialized = [];
        foreach ($this->currencies as $currency) {
            $serialized[] = [
                "currencyCode" => $currency->getCurrencyCode(),
                "numericCode" => $currency->getNumericCode(),
                "name" => $currency->getName(),
                "defaultFractionDigits" => $currency->getDefaultFractionDigits(),
            ];
        }
        return $serialized;
    }

    public function exists($symbol): bool
    {
        foreach ($this->currencies as $currency) {
            if ($currency->getCurrencyCode() === $symbol) {
                return true;
            }
        }
        return false;
    }
}