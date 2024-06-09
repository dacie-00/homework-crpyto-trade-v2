<?php
declare(strict_types=1);

namespace App\Crypto;

class Crypto
{
    private int $id;
    private string $name;
    private string $symbol;
    private string $price;

    public function __construct(int $id, string $name, string $symbol, string $price)
    {
        $this->id = $id;
        $this->name = $name;
        $this->symbol = $symbol;
        $this->price = $price;
    }

    public function id(): int
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function symbol(): string
    {
        return $this->symbol;
    }

    public function price(): int
    {
        return $this->price;
    }
}