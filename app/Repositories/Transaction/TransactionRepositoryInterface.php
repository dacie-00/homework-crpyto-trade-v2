<?php
declare(strict_types=1);

namespace App\Repositories\Transaction;

use App\Models\Currency;
use App\Models\Money;
use App\Models\Transaction;
use App\Models\User;

interface TransactionRepositoryInterface
{
    public function getByUserAndCurrency(string $userId, Currency $currency): array;

    public function add(Transaction $transaction): void;

    public function getAveragePrice(string $userId, Money $money): float;

    /**
     * @return Transaction[]
     */
    public function getByUser(User $user): array;
}