<?php
declare(strict_types=1);

namespace App\Models;

use Brick\Money\Money;
use Doctrine\DBAL\Connection;
use OutOfBoundsException;

class Wallet
{
    private Connection $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function add(Money $money): void
    {
        $affectedRows = $this->connection->createQueryBuilder()->update("wallet")
            ->where("ticker = :ticker")
            ->set("amount", "amount + :amount")
            ->setParameter("ticker", $money->getCurrency())
            ->setParameter("amount", $money->getAmount())
            ->executeQuery()
            ->rowCount();

        if ($affectedRows === 0) {
            $this->connection->createQueryBuilder()->insert("wallet")
                ->values(
                    [
                        "ticker" => ":ticker",
                        "name" => ":name",
                        "amount" => ":amount",
                    ]
                )
                ->setParameter("ticker", $money->getCurrency()->getCurrencyCode())
                ->setParameter("name", $money->getCurrency()->getName())
                ->setParameter("amount", $money->getAmount())
                ->executeQuery();
        }
    }

    public function subtract(Money $money): void
    {
        $this->connection->createQueryBuilder()->update("wallet")
            ->set("amount", "amount - :amount")
            ->where("ticker = :ticker")
            ->setParameter("amount", $money->getAmount())
            ->setParameter("ticker", $money->getCurrency())
            ->executeQuery();

        $this->connection->createQueryBuilder()->delete("wallet")
            ->where("ticker = :ticker")
            ->andWhere("amount <= 0")
            ->setParameter("ticker", $money->getCurrency())
            ->executeQuery();
    }

    public function contents(): array
    {
        $moneyData = $this->connection->createQueryBuilder()
            ->select("*")
            ->from("wallet")
            ->executeQuery()
            ->fetchAllAssociative();

        if (!$moneyData) {
            return [];
        }

        return array_map(
            fn($money) => Money::of(
                $money["amount"],
                new \Brick\Money\Currency(
                    $money["ticker"],
                    0,
                    $money["name"],
                    9
                )
            ),
            $moneyData
        );
    }

    public function getMoney(string $ticker): Money
    {
        $money = $this->connection->createQueryBuilder()
            ->select("*")
            ->from("wallet")
            ->where("ticker = :ticker")
            ->setParameter("ticker", $ticker)
            ->executeQuery()
            ->fetchAssociative();
        if (!$money) {
            throw new OutOfBoundsException("CryptoCurrency {$ticker} does not exist");
        }
        return Money::of($money["amount"],
            new \Brick\Money\Currency(
                $money["ticker"],
                0,
                $money["name"],
                9
            )
        );
    }

    public function isEmpty()
    {
        return $this->connection->createQueryBuilder()
                ->select("*")
                ->from("wallet")
                ->executeQuery()
                ->fetchOne() === false;
    }
}