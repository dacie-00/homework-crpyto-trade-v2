<?php
declare(strict_types=1);

namespace App\Crypto;

class Crypto
{
    private int $id;
    private string $name;
    private string $ticker;
    private int $price;

    public function __construct(int $id, string $name, string $ticker, int $price)
    {
        $this->id = $id;
        $this->name = $name;
        $this->ticker = $ticker;
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

    public function ticker(): string
    {
        return $this->ticker;
    }

    public function price(): int
    {
        return $this->price;
    }
}