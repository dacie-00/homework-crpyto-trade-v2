<?php
declare(strict_types=1);

namespace App\Transaction;

use Brick\Math\BigDecimal;
use Carbon\Carbon;
use DateTimeInterface;
use JsonSerializable;

class Transaction implements JsonSerializable
{
    private BigDecimal $amountIn;
    private string $currencyIn;
    private BigDecimal $amountOut;
    private string $currencyOut;
    private Carbon $createdAt;

    public function __construct(
        BigDecimal $amountIn,
        string $currencyIn,
        BigDecimal $amountOut,
        string $currencyOut,
        ?string $createdAt = null
    ) {
        $this->amountIn = $amountIn;
        $this->currencyIn = $currencyIn;
        $this->amountOut = $amountOut;
        $this->currencyOut = $currencyOut;
        $this->createdAt = $createdAt ? Carbon::parse($createdAt) : Carbon::now("UTC");
    }

    public static function fromArray(array $transaction): Transaction
    {
        return new self(
            BigDecimal::of($transaction['amount_in']),
            $transaction['currency_in'],
            BigDecimal::of($transaction['amount_out']),
            $transaction['currency_out'],
            $transaction['created_at']
        );
    }

    public function amountIn(): BigDecimal
    {
        return $this->amountIn;
    }

    public function currencyIn(): string
    {
        return $this->currencyIn;
    }

    public function amountOut(): BigDecimal
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