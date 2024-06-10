<?php

namespace App;

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
            $this->currencies[$currency->id] = new Currency(
                $currency->id,
                $currency->name,
                $currency->ticker,
                $currency->exchangeRate
            );
        }
    }

    public function add(Currency $currency): void
    {
        $this->currencies[$currency->id()] = $currency;
    }

    public function getCurrencyById(int $id): Currency
    {
        if (!isset($this->currencies[$id])) {
            throw new OutOfBoundsException("Currency not found");
        }
        return $this->currencies[$id];
    }

    public function getAll(): array
    {
        return $this->currencies;
    }

    public function getCurrencyByName(string $name): Currency
    {
        foreach ($this->currencies as $currency) {
            if ($currency->name() === $name) {
                return $currency;
            }
        }
        throw new OutOfBoundsException("Currency not found");
    }

    public function getCurrencyByTicker(string $ticker): Currency
    {
        foreach ($this->currencies as $currency) {
            if ($currency->ticker() === $ticker) {
                return $currency;
            }
        }
        throw new OutOfBoundsException("Currency not found");
    }

    public function jsonSerialize(): array
    {
        return array_values($this->currencies);
    }
}