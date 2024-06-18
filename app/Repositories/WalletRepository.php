<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Models\Wallet;
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

    public function insert(string $wallet_id, string $user_id, string $ticker, BigDecimal $amount): void
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
                    "wallet_id" => $wallet_id,
                    "user_id" => $user_id,
                    "ticker" => $ticker,
                    "amount" => $amount,
                ]
            )
            ->executeQuery();
    }

    public function delete(string $wallet_id, string $ticker): void
    {
        $this->connection->createQueryBuilder()
            ->delete("wallet")
            ->where("wallet_id = :wallet_id and ticker = :ticker")
            ->setParameters(
                [
                    "wallet_id" => $wallet_id,
                    "ticker" => $ticker,
                ]
            );
    }

    public function update(string $wallet_id, string $ticker, BigDecimal $amount): void
    {
        $this->connection->createQueryBuilder()
            ->update("wallet")
            ->where("wallet_id = :wallet_id and ticker = :ticker")
            ->set("amount", ":amount")
            ->setParameters(
                [
                    "wallet_id" => $wallet_id,
                    "ticker" => $ticker,
                    "amount" => $amount,
                ]
            )
            ->executeQuery();
    }

    public function exists(string $wallet_id, string $ticker): bool
    {
        return $this->connection->createQueryBuilder()
                ->select("wallet_id")
                ->from("wallet")
                ->where("wallet_id = :wallet_id and ticker = :ticker")
                ->setParameters(
                    [
                        "wallet_id" => $wallet_id,
                        "ticker" => $ticker,
                    ]
                )
                ->executeQuery()
                ->fetchOne() !== false;
    }

    public function getWalletById(string $wallet_id): Wallet
    {
        $rows = $this->connection->createQueryBuilder()
            ->from("wallet")
            ->select("user_id, ticker, amount")
            ->where("wallet_id = :wallet_id")
            ->setParameter("wallet_id", $wallet_id)
            ->executeQuery()
            ->fetchAllAssociative();
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
        return new Wallet($userId, $wallet_id, $content);
    }

    public function getMoney(string $wallet_id, string $ticker): ?Money
    {
        $amount = $this->connection->createQueryBuilder()
            ->from("wallet")
            ->select("amount")
            ->where("wallet_id = :wallet_id and ticker = :ticker")
            ->setParameters(
                [
                    "wallet_id" => $wallet_id,
                    "ticker" => $ticker,
                ]
            )
            ->executeQuery()
            ->fetchOne();
        return $amount !== false ?
            Money::of(
                $amount,
                new Currency(
                    $ticker,
                    0,
                    "",
                    9
                )
            ) : null;
    }

    public function getWalletByUserId(string $user_id): ?Wallet
    {
        $wallet = $this->connection->createQueryBuilder()
            ->select("*")
            ->from("wallet")
            ->where("user_id = :user_id")
            ->setParameter("user_id", $user_id)
            ->executeQuery()
            ->fetchAssociative();

        return new Wallet($wallet["user_id"], $wallet["wallet_id"]);
    }


}