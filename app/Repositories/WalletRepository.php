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

    public function insert(string $id, string $ticker, BigDecimal $amount): void
    {
        $this->connection->createQueryBuilder()
            ->insert("wallet")
            ->values(
                [
                    "id" => ":id",
                    "ticker" => ":ticker",
                    "amount" => ":amount",
                ]
            )
            ->setParameters(
                [
                    "id" => $id,
                    "ticker" => $ticker,
                    "amount" => $amount,
                ]
            )
            ->executeQuery();
    }

    public function delete(string $id, string $ticker): void
    {
        $this->connection->createQueryBuilder()
            ->delete("wallet")
            ->where("id = :id and ticker = :ticker")
            ->setParameters(
                [
                    "id" => $id,
                    "ticker" => $ticker,
                ]
            );
    }

    public function update(string $id, string $ticker, BigDecimal $amount): void
    {
        $this->connection->createQueryBuilder()
            ->update("wallet")
            ->where("id = :id and ticker = :ticker")
            ->set("amount", ":amount")
            ->setParameters(
                [
                    "id" => $id,
                    "ticker" => $ticker,
                    "amount" => $amount,
                ]
            )
            ->executeQuery();
    }

    public function exists(string $id, string $ticker): bool
    {
        return $this->connection->createQueryBuilder()
            ->select("id")
            ->from("wallet")
            ->where("id = :id and ticker = :ticker")
            ->setParameters(
                [
                    "id" => $id,
                    "ticker" => $ticker,
                ]
            )
            ->executeQuery()
            ->fetchOne() !== false;
    }

    public function getWalletById(string $id): Wallet
    {
        $rows = $this->connection->createQueryBuilder()
            ->from("wallet")
            ->select("ticker, amount")
            ->where("id = :id")
            ->setParameter("id", $id)
            ->executeQuery()
            ->fetchAllAssociative();
        $content = [];
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
        return new Wallet($id, $content);
    }

    public function getMoney(string $id, string $ticker): ?Money
    {
        $amount = $this->connection->createQueryBuilder()
            ->from("wallet")
            ->select("amount")
            ->where("id = :id and ticker = :ticker")
            ->setParameters(
                [
                    "id" => $id,
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


}