<?php
declare(strict_types=1);

namespace App\Repositories\Wallet;

use App\Models\Currency;
use App\Models\Money;
use App\Models\Wallet;
use App\Repositories\Wallet\Exceptions\WalletNotFoundException;

interface WalletRepositoryInterface
{
    public function insert(string $walletId, string $userId, Money $money): void;

    public function delete(string $walletId, Money $money): void;

    public function update(string $walletId, Money $money): void;

    public function exists(string $walletId, Money $money): bool;

    /**
     * @throws WalletNotFoundException
     */
    public function getWalletById(string $walletId): Wallet;

    public function getMoneyInWallet(string $walletId, Currency $currency): Money;

    public function getWalletByUserId(string $userId): ?Wallet;

    public function getOwner(string $walletId): string;

    public function addToWallet(string $walletId, Money $money): void;

    public function subtractFromWallet(string $walletId, Money $money): void;
}