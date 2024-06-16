<?php
declare(strict_types=1);

namespace App\Models;

use App\Exceptions\NoMoneyException;
use Brick\Math\BigDecimal;
use Brick\Money\Currency;
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
        $currentAmount = $this->connection->createQueryBuilder()
            ->from("wallet")
            ->select("amount")
            ->where("ticker = :ticker")
            ->setParameter("ticker", $money->getCurrency()->getCurrencyCode())
            ->executeQuery()
            ->fetchOne();

        if ($currentAmount === false) {
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
            return;
        }

        $this->connection->createQueryBuilder()->update("wallet")
            ->where("ticker = :ticker")
            ->set("amount", ":amount")
            ->setParameter("ticker", $money->getCurrency())
            ->setParameter("amount", $money->getAmount()->plus($currentAmount))
            ->executeQuery()
            ->rowCount();
    }

    public function subtract(Money $money): void
    {
        $currentAmount = $this->connection->createQueryBuilder()
            ->from("wallet")
            ->select("amount")
            ->where("ticker = :ticker")
            ->setParameter("ticker", $money->getCurrency()->getCurrencyCode())
            ->executeQuery()
            ->fetchOne();

        $this->connection->createQueryBuilder()->update("wallet")
            ->where("ticker = :ticker")
            ->set("amount", ":amount")
            ->setParameter("ticker", $money->getCurrency())
            ->setParameter("amount", BigDecimal::of($currentAmount)->minus($money->getAmount()))
            ->executeQuery();

        $this->connection->createQueryBuilder()->delete("wallet")
            ->where("ticker = :ticker")
            ->andWhere("amount <= 0")
            ->setParameter("ticker", $money->getCurrency())
            ->executeQuery();
    }

    /**
     * @return Money[]
     */
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
                new Currency(
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
            throw new NoMoneyException("There is now {$ticker} in the wallet");
        }
        return Money::of($money["amount"],
            new Currency(
                $money["ticker"],
                0,
                $money["name"],
                9
            )
        );
    }

    public function isEmpty(): bool
    {
        return $this->connection->createQueryBuilder()
                ->select("*")
                ->from("wallet")
                ->executeQuery()
                ->fetchOne() === false;
    }
}