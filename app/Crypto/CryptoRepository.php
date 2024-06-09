<?php

namespace App\Crypto;

use OutOfBoundsException;

class CryptoRepository
{
    /**
     * @var Crypto[]
     */
    private array $currencies = [];

    public function add(Crypto $currency): void
    {
        $this->currencies[$currency->id()] = $currency;
    }

    public function getCurrencyById(int $id): Crypto
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

    public function getCurrencyByName(string $name): Crypto
    {
        foreach ($this->currencies as $currency) {
            if ($currency->name() === $name) {
                return $currency;
            }
        }
        throw new OutOfBoundsException("Currency not found");
    }
}