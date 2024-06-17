<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\Wallet;
use App\Repositories\WalletRepository;
use Brick\Math\BigDecimal;
use Brick\Money\Money;

class WalletService
{
    private WalletRepository $walletRepository;

    public function __construct(WalletRepository $walletRepository)
    {
        $this->walletRepository = $walletRepository;
    }

    public function addToWallet(string $id, Money $money): void
    {
        $ticker = $money->getCurrency()->getCurrencyCode();
        if (!$this->walletRepository->exists($id, $ticker)) {
            $this->walletRepository->insert($id, $ticker, $money->getAmount());
            return;
        }
        $initialMoney = $this->walletRepository->getMoney($id, $ticker);

        $newAmount = $initialMoney->getAmount()->plus($money->getAmount());
        $this->walletRepository->update($id, $ticker, $newAmount);
    }

    public function subtractFromWallet(string $id, Money $money): void
    {
        $ticker = $money->getCurrency()->getCurrencyCode();
        if (!$this->walletRepository->exists($id, $ticker)) {
            return; // TODO: maybe log something here
        }
        $initialMoney = $this->walletRepository->getMoney($id, $ticker);

        $newAmount = $initialMoney->getAmount()->minus($money->getAmount());
        if ($newAmount->isNegativeOrZero()) {
            $this->walletRepository->delete($id, $ticker);
            return;
        }
        $this->walletRepository->update($id, $ticker, $newAmount);
    }

    public function getMoney(string $id, string $ticker): ?Money
    {
        return $this->walletRepository->getMoney($id, $ticker);
    }

    public function getWalletById(string $id): Wallet
    {
        return $this->walletRepository->getWalletById($id);
    }
}