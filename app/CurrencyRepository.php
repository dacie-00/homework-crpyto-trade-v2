<?php

namespace App;

use OutOfBoundsException;

class CurrencyRepository
{
    /**
     * @var Currency[]
     */
    private array $currencies = [];

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
}