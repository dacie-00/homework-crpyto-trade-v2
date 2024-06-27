<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Models\Wallet;
use App\Repositories\Wallet\WalletRepository;
use Brick\Money\Money;

class WalletService
{
    private WalletRepository $walletRepository;

    public function __construct(WalletRepository $walletRepository)
    {
        $this->walletRepository = $walletRepository;
    }

    public function addToWallet(Wallet $wallet, Money $money): void
    {
        $ticker = $money->getCurrency()->getCurrencyCode();
        if (!$this->walletRepository->exists($wallet->id(), $ticker)) {
            $this->walletRepository->insert($wallet->id(), $wallet->userId(), $ticker, $money->getAmount());
            return;
        }
        $initialMoney = $this->walletRepository->getMoney($wallet->id(), $ticker);

        $newAmount = $initialMoney->getAmount()->plus($money->getAmount());
        $this->walletRepository->update($wallet->id(), $ticker, $newAmount);
    }

    public function subtractFromWallet(Wallet $wallet, Money $money): void
    {
        $ticker = $money->getCurrency()->getCurrencyCode();
        if (!$this->walletRepository->exists($wallet->id(), $ticker)) {
            return;
        }
        $initialMoney = $this->walletRepository->getMoney($wallet->id(), $ticker);

        $newAmount = $initialMoney->getAmount()->minus($money->getAmount());
        if ($newAmount->isNegativeOrZero()) {
            $this->walletRepository->delete($wallet->id(), $ticker);
            return;
        }
        $this->walletRepository->update($wallet->id(), $ticker, $newAmount);
    }

    public function getMoney(Wallet $wallet, string $ticker): ?Money
    {
        return $this->walletRepository->getMoney($wallet->id(), $ticker);
    }

    public function getWalletById(string $id): Wallet
    {
        return $this->walletRepository->getWalletById($id);
    }

    public function getUserWallet(User $user): ?Wallet
    {
        return $this->walletRepository->getWalletByUserId($user->id());
    }
}