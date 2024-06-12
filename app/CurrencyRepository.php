<?php
declare(strict_types=1);

namespace App;

use JsonSerializable;
use OutOfBoundsException;

class CurrencyRepository implements JsonSerializable
{
    /**
     * @var Currency[]
     */
    private array $currencies = [];

    /**
     * @param Currency[] $currencies
     */
    public function __construct(?array $currencies = null)
    {
        if (!$currencies) {
            return;
        }
        foreach ($currencies as $currency) {
            $this->currencies[] = $currency;
        }
    }

    public function add(Currency $currency): void
    {
        $this->currencies[$currency->definition()->getCurrencyCode()] = $currency;
    }

    public function getAll(): array
    {
        return $this->currencies;
    }

    public function getCurrencyByName(string $name): Currency
    {
        foreach ($this->currencies as $currency) {
            if ($currency->definition()->getName() === $name) {
                return $currency;
            }
        }
        throw new OutOfBoundsException("Currency not found ($name)");
    }

    public function getCurrencyByCode(string $currencyCode): Currency
    {
        foreach ($this->currencies as $currency) {
            if ($currency->definition()->getCurrencyCode() === $currencyCode) {
                return $currency;
            }
        }
        throw new OutOfBoundsException("Currency not found ($currencyCode)");
    }

    public function jsonSerialize(): array
    {
        return $this->currencies;
    }

    public function exists(string $symbol): bool
    {
        foreach ($this->currencies as $currency) {
            if ($currency->getCurrencyCode() === $symbol) {
                return true;
            }
        }
        return false;
    }
}