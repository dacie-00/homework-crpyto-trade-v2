<?php
declare(strict_types=1);

namespace App\Models;

use Brick\Money\Currency;
use Brick\Money\Money;
use Carbon\Carbon;
use Ramsey\Uuid\Uuid;

class Transaction
{
    public const TYPE_BUY = "buy";
    public const TYPE_SELL = "sell";

    private string $id;
    private Money $sentMoney;
    private string $type;
    private Money $receivedMoney;
    private Carbon $createdAt;

    public function __construct(
        Money $sentMoney,
        string $type,
        Money $receivedMoney,
        ?string $createdAt = null,
        ?string $id = null
    ) {
        $this->sentMoney = $sentMoney;
        $this->type = $type;
        $this->receivedMoney = $receivedMoney;
        $this->createdAt = $createdAt ? Carbon::parse($createdAt) : Carbon::now("UTC");
        $this->id = $id ?: Uuid::uuid4()->toString();
    }

    public static function fromArray(array $transaction): Transaction
    {
        return new self(
            Money::of($transaction["sent_amount"], new Currency(
                    $transaction["sent_ticker"],
                    0,
                    "",
                    9
                )
            ),
            $transaction["type"],
            Money::of($transaction["received_amount"], new Currency(
                    $transaction["received_ticker"],
                    0,
                    "",
                    9
                )
            ),
            $transaction["created_at"],
            $transaction["id"]
        );
    }

    public function sentMoney(): Money
    {
        return $this->sentMoney;
    }

    public function receivedMoney(): Money
    {
        return $this->receivedMoney;
    }

    public function type(): string
    {
        return $this->type;
    }

    public function createdAt(): Carbon
    {
        return $this->createdAt;
    }

    public function id(): string
    {
        return $this->id;
    }
}