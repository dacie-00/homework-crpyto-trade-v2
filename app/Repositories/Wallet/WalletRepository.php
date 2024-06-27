<?php
declare(strict_types=1);

namespace App\Repositories\Wallet;

use App\Models\Wallet;
use App\Repositories\Wallet\Exceptions\WalletNotFoundException;
use Brick\Math\BigDecimal;
use Brick\Money\Currency;
use Brick\Money\Money;
use Doctrine\DBAL\Connection;

class WalletRepository
{
    private Connection $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function insert(string $walletId, string $userId, string $ticker, BigDecimal $amount): void
    {
        $this->connection->createQueryBuilder()
            ->insert("wallet")
            ->values(
                [
                    "wallet_id" => ":wallet_id",
                    "user_id" => ":user_id",
                    "ticker" => ":ticker",
                    "amount" => ":amount",
                ]
            )
            ->setParameters(
                [
                    "wallet_id" => $walletId,
                    "user_id" => $userId,
                    "ticker" => $ticker,
                    "amount" => $amount,
                ]
            )
            ->executeQuery();
    }

    public function delete(string $walletId, string $ticker): void
    {
        $this->connection->createQueryBuilder()
            ->delete("wallet")
            ->where("wallet_id = :wallet_id and ticker = :ticker")
            ->setParameters(
                [
                    "wallet_id" => $walletId,
                    "ticker" => $ticker,
                ]
            )
            ->executeQuery();
    }

    public function update(string $walletId, string $ticker, BigDecimal $amount): void
    {
        $this->connection->createQueryBuilder()
            ->update("wallet")
            ->where("wallet_id = :wallet_id and ticker = :ticker")
            ->set("amount", ":amount")
            ->setParameters(
                [
                    "wallet_id" => $walletId,
                    "ticker" => $ticker,
                    "amount" => $amount,
                ]
            )
            ->executeQuery();
    }

    public function exists(string $walletId, string $ticker): bool
    {
        return $this->connection->createQueryBuilder()
                ->select("wallet_id")
                ->from("wallet")
                ->where("wallet_id = :wallet_id and ticker = :ticker")
                ->setParameters(
                    [
                        "wallet_id" => $walletId,
                        "ticker" => $ticker,
                    ]
                )
                ->executeQuery()
                ->fetchOne() !== false;
    }

    public function getWalletById(string $walletId): Wallet
    {
        $rows = $this->connection->createQueryBuilder()
            ->from("wallet")
            ->select("user_id, ticker, amount")
            ->where("wallet_id = :wallet_id")
            ->setParameter("wallet_id", $walletId)
            ->executeQuery()
            ->fetchAllAssociative();
        if (empty($rows)) {
            throw new WalletNotFoundException("Wallet ($walletId) not found");
        }
        $content = [];
        $userId = $rows[0]["user_id"];
        foreach ($rows as $row) {
            $content[] = Money::of($row["amount"],
                new Currency(
                    $row["ticker"],
                    0,
                    "",
                    9
                )
            );
        }
        return new Wallet($userId, $walletId, $content);
    }

    public function getMoney(string $walletId, string $ticker): Money
    {
        $amount = $this->connection->createQueryBuilder()
            ->from("wallet")
            ->select("amount")
            ->where("wallet_id = :wallet_id and ticker = :ticker")
            ->setParameters(
                [
                    "wallet_id" => $walletId,
                    "ticker" => $ticker,
                ]
            )
            ->executeQuery()
            ->fetchOne();
        return Money::of(
            $amount ?: 0,
                new Currency(
                    $ticker,
                    0,
                    "",
                    9
                )
        );
    }

    public function getWalletByUserId(string $userId): ?Wallet
    {
        $wallet = $this->connection->createQueryBuilder()
            ->select("*")
            ->from("wallet")
            ->where("user_id = :user_id")
            ->setParameter("user_id", $userId)
            ->executeQuery()
            ->fetchAssociative();

        return new Wallet($wallet["user_id"], $wallet["wallet_id"]);
    }

    public function getOwner(string $walletId): string
    {
        $userId = $this->connection->createQueryBuilder()
            ->select("user_id")
            ->from("wallet")
            ->where("wallet_id = :wallet_id")
            ->setParameter("wallet_id", $walletId)
            ->executeQuery()
            ->fetchOne();
        return $userId;
    }

    public function addToWallet(string $walletId, Money $money): void
    {
        $ticker = $money->getCurrency()->getCurrencyCode();
        if (!$this->exists($walletId, $ticker)) {
            $this->insert($walletId, $this->getOwner($walletId), $ticker, $money->getAmount());
            return;
        }
        $initialMoney = $this->getMoney($walletId, $ticker);

        $newAmount = $initialMoney->getAmount()->plus($money->getAmount());
        $this->update($walletId, $ticker, $newAmount);
    }

    public function subtractFromWallet(string $walletId, Money $money): void
    {
        $ticker = $money->getCurrency()->getCurrencyCode();
        if (!$this->exists($walletId, $ticker)) {
            return;
        }
        $initialMoney = $this->getMoney($walletId, $ticker);

        $newAmount = $initialMoney->getAmount()->minus($money->getAmount());
        if ($newAmount->isNegativeOrZero()) {
            $this->delete($walletId, $ticker);
            return;
        }
        $this->update($walletId, $ticker, $newAmount);
    }
}