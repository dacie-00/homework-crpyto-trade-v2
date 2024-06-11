<?php
declare(strict_types=1);

namespace App;

use Brick\Math\BigDecimal;
use JsonSerializable;
use stdClass;

class TransactionRepository implements JsonSerializable
{
    /**
     * @var Transaction[]
     */
    private array $transactions;

    /**
     * @param stdClass[]|null $transactions
     */
    public function __construct(?array $transactions = null)
    {
        if (!$transactions) {
            $this->transactions = [];
            return;
        }
        foreach ($transactions as $transaction) {
            $this->add(new Transaction(
                BigDecimal::of($transaction->amountIn),
                $transaction->currencyIn,
                BigDecimal::of($transaction->amountOut),
                $transaction->currencyOut,
                $transaction->createdAt
            ));
        }
    }

    public function getAll(): array
    {
        return $this->transactions;
    }

    public function add(Transaction $transaction): void
    {
        $this->transactions[] = $transaction;
    }

    public function jsonSerialize(): array
    {
        return $this->transactions;
    }
}