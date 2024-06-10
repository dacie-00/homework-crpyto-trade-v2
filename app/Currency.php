<?php
declare(strict_types=1);

namespace App;

use JsonSerializable;

class Currency implements JsonSerializable
{
    private int $id;
    private string $name;
    private string $ticker;
    private int $exchangeRate;

    public function __construct(int $id, string $name, string $ticker, int $exchangeRate)
    {
        $this->id = $id;
        $this->name = $name;
        $this->ticker = $ticker;
        $this->exchangeRate = $exchangeRate;
    }

    public function id(): int
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function ticker(): string
    {
        return $this->ticker;
    }

    public function exchangeRate(): int
    {
        return $this->exchangeRate;
    }

    public function jsonSerialize(): array
    {
        return [
            "id" => $this->id,
            "name" => $this->name,
            "ticker" => $this->ticker,
            "exchangeRate" => $this->exchangeRate,
        ];
    }
}