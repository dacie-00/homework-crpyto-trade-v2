<?php
declare(strict_types=1);

namespace App;

use Carbon\Carbon;
use DateTimeInterface;
use JsonSerializable;

class Transaction implements JsonSerializable
{
    private int $amountIn;
    private string $currencyIn;
    private string $type;
    private int $amountOut;
    private string $currencyOut;
    private Carbon $createdAt;

    public function __construct(
        int $amountIn,
        string $currencyIn,
        int $amountOut,
        string $currencyOut,
        ?string $createdAt = null
    ) {
        $this->amountIn = $amountIn;
        $this->currencyIn = $currencyIn;
        $this->amountOut = $amountOut;
        $this->currencyOut = $currencyOut;
        $this->createdAt = $createdAt ? Carbon::parse($createdAt) : Carbon::now("UTC");
    }

    public function amountIn(): int
    {
        return $this->amountIn;
    }

    public function currencyIn(): string
    {
        return $this->currencyIn;
    }

    public function amountOut(): int
    {
        return $this->amountOut;
    }

    public function currencyOut(): string
    {
        return $this->currencyOut;
    }

    public function createdAt(): Carbon
    {
        return $this->createdAt;
    }

    public function jsonSerialize(): array
    {
        return [
            "amountIn" => $this->amountIn,
            "currencyIn" => $this->currencyIn,
            "amountOut" => $this->amountOut,
            "currencyOut" => $this->currencyOut,
            "createdAt" => $this->createdAt->timezone("UTC")->format(DateTimeInterface::ATOM),
        ];
    }
}