<?php

namespace App;

use Carbon\Carbon;

class Transaction
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
        string $type,
        int $amountOut,
        string $currencyOut,
        ?string $createdAt
    ) {
        $this->amountIn = $amountIn;
        $this->currencyIn = $currencyIn;
        $this->type = $type;
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

    public function type(): string
    {
        return $this->type;
    }

    public function createdAt(): Carbon
    {
        return $this->createdAt;
    }

}