<?php
declare(strict_types=1);

namespace App\Models;

use App\Repositories\CurrencyRepository;
use Brick\Money\Money;
use Doctrine\DBAL\Connection;
use OutOfBoundsException;

class Wallet
{

    private Connection $connection;
    private CurrencyRepository $currencyRepository;

    public function __construct(Connection $connection, CurrencyRepository $currencyRepository)
    {
        $this->connection = $connection;
        $this->currencyRepository = $currencyRepository;
    }

    public function add(Money $money): void
    {
        $affectedRows = $this->connection->createQueryBuilder()->update("wallet")
            ->where("currency = :currency")
            ->set("amount", "amount + :amount")
            ->setParameter("currency", $money->getCurrency())
            ->setParameter("amount", $money->getAmount())
            ->executeQuery()
            ->rowCount();

        if ($affectedRows === 0) {
            $this->connection->createQueryBuilder()->insert("wallet")
                ->values(
                    [
                        "currency" => ":currency",
                        "amount" => ":amount",
                    ]
                )
                ->setParameter("currency", $money->getCurrency())
                ->setParameter("amount", $money->getAmount())
                ->executeQuery();
        }
    }

    public function subtract(Money $money): void
    {
        $this->connection->createQueryBuilder()->update("wallet")
            ->set("amount", "amount - :amount")
            ->where("currency = :currency")
            ->setParameter("amount", $money->getAmount())
            ->setParameter("currency", $money->getCurrency())
            ->executeQuery();

        $this->connection->createQueryBuilder()->delete("wallet")
            ->where("currency = :currency")
            ->andWhere("amount <= 0")
            ->setParameter("currency", $money->getCurrency())
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
                $this->currencyRepository->getCurrencyByCode($money["currency"])->definition()
            ),
            $moneyData
        );
    }

    public function getMoney(string $currencyCode): Money
    {
        $money = $this->connection->createQueryBuilder()
            ->select("*")
            ->from("wallet")
            ->where("currency = :currency")
            ->setParameter("currency", $currencyCode)
            ->executeQuery()
            ->fetchAssociative();
        if (!$money) {
            throw new OutOfBoundsException("CryptoCurrency {$currencyCode} does not exist");
        }
        return Money::of($money["amount"], $this->currencyRepository->getCurrencyByCode($currencyCode)->definition());
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