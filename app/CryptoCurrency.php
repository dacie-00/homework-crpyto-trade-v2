<?php
declare(strict_types=1);

namespace App;

use Brick\Money\Currency;
use Brick\Money\ExchangeRateProvider\ConfigurableProvider;
use JsonSerializable;

class CryptoCurrency implements JsonSerializable
{
    private Currency $currency;
    private ConfigurableProvider $exchangeRate;

    public function __construct(Currency $currency, ConfigurableProvider $exchangeRate)
    {
        $this->currency = $currency;
        $this->exchangeRate = $exchangeRate;
    }

    public function id(): int
    {
        return $this->currency->getNumericCode();
    }

    public function name(): string
    {
        return $this->currency->getName();
    }

    public function ticker(): string
    {
        return $this->currency->getCurrencyCode();
    }

    public function exchangeRate(): Currency
    {
        return $this->exchangeRate;
    }

    public function jsonSerialize(): array
    {
        return [
            "currency" => $this->currency,
//            "id" => $this->id,
//            "name" => $this->name,
//            "ticker" => $this->ticker,
            "exchangeRate" => $this->exchangeRate,
        ];
    }
}