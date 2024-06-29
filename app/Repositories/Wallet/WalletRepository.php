<?php
declare(strict_types=1);

namespace App\Repositories\Wallet;

use App\Models\Currency;
use App\Models\Money;
use App\Models\Wallet;
use App\Repositories\Wallet\Exceptions\WalletNotFoundException;
use Doctrine\DBAL\Connection;

class WalletRepository
{
    private Connection $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function insert(string $walletId, string $userId, Money $money): void
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
                    "ticker" => $money->ticker(),
                    "amount" => $money->amount(),
                ]
            )
            ->executeQuery();
    }

    public function delete(string $walletId, Money $money): void
    {
        $this->connection->createQueryBuilder()
            ->delete("wallet")
            ->where("wallet_id = :wallet_id and ticker = :ticker")
            ->setParameters(
                [
                    "wallet_id" => $walletId,
                    "ticker" => $money->ticker(),
                ]
            )
            ->executeQuery();
    }

    public function update(string $walletId, Money $money): void
    {
        $this->connection->createQueryBuilder()
            ->update("wallet")
            ->where("wallet_id = :wallet_id and ticker = :ticker")
            ->set("amount", ":amount")
            ->setParameters(
                [
                    "wallet_id" => $walletId,
                    "ticker" => $money->ticker(),
                    "amount" => $money->amount(),
                ]
            )
            ->executeQuery();
    }

    public function exists(string $walletId, Money $money): bool
    {
        return $this->connection->createQueryBuilder()
                ->select("wallet_id")
                ->from("wallet")
                ->where("wallet_id = :wallet_id and ticker = :ticker")
                ->setParameters(
                    [
                        "wallet_id" => $walletId,
                        "ticker" => $money->ticker(),
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
            $content[] = new Money(
                (float)$row["amount"],
                new Currency(
                    $row["ticker"],
                )
            );
        }
        return new Wallet($userId, $walletId, $content);
    }

    public function getMoney(string $walletId, Money $money): Money
    {
        $amount = $this->connection->createQueryBuilder()
            ->from("wallet")
            ->select("amount")
            ->where("wallet_id = :wallet_id and ticker = :ticker")
            ->setParameters(
                [
                    "wallet_id" => $walletId,
                    "ticker" => $money->ticker(),
                ]
            )
            ->executeQuery()
            ->fetchOne();
        return new Money(
            (float)$amount ?: 0,
            new Currency(
                $money->ticker(),
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
        if (!$this->exists($walletId, $money)) {
            $this->insert($walletId, $this->getOwner($walletId), $money);
            return;
        }
        $moneyInWallet = $this->getMoney($walletId, $money);

        $moneyInWallet->setAmount($money->amount() + $money->amount());
        $this->update($walletId, $money);
    }

    public function subtractFromWallet(string $walletId, Money $money): void
    {
        if (!$this->exists($walletId, $money)) {
            return;
        }
        $moneyInWallet = $this->getMoney($walletId, $money);

        $moneyInWallet->setAmount($money->amount() - $money->amount());
        if ($money->amount() <= 0) {
            $this->delete($walletId, $money);
            return;
        }
        $this->update($walletId, $money);
    }
}