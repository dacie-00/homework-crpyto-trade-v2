<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Models\Currency;
use Doctrine\DBAL\Connection;
use OutOfBoundsException;

class CurrencyRepository
{
    private Connection $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * @param Currency|Currency[] $currency
     */
    public function add($currencies): void
    {
        if (!is_array($currencies)) {
            $currencies = [$currencies];
        }
        foreach ($currencies as $currency) {
            $result = $this->connection->createQueryBuilder()
                ->select("*")
                ->from("currencies")
                ->where("code = ?")
                ->setParameter(0, $currency->code())
                ->executeQuery()
                ->fetchOne();

            if ($result) {
                $this->update($currency);
                continue;
            }

            $this->connection->createQueryBuilder()
                ->insert("currencies")
                ->values([
                    "name" => ":name",
                    "code" => ":code",
                    "numeric_code" => ":numeric_code",
                    "exchange_rate" => ":exchange_rate",
                ])
                ->setParameters([
                    "name" => $currency->name(),
                    "code" => $currency->code(),
                    "numeric_code" => $currency->numericCode(),
                    "exchange_rate" => $currency->exchangeRate(),
                ])
                ->executeStatement();
        }
    }

    private function update(Currency $currency): void
    {
        $this->connection->createQueryBuilder()
            ->update("currencies")
            ->set("name", ":name")
            ->set("numeric_code", ":numeric_code")
            ->set("exchange_rate", ":exchange_rate")
            ->setParameters(
                [
                    "name" => $currency->name(),
                    "numeric_code" => $currency->numericCode(),
                    "exchange_rate" => $currency->exchangeRate(),
                ]
            )
            ->where("code = ?")
            ->setParameter(0, $currency->code())
            ->executeQuery();
    }

    public function getAll(): array
    {
        $currencyData = $this->connection->createQueryBuilder()
            ->select("*")
            ->from("currencies")
            ->executeQuery()
            ->fetchAllAssociative();
        $currencies = [];
        foreach ($currencyData as $currency) {
            $currencies[] = Currency::fromArray($currency);

        }
        return $currencies;
    }

    public function getCurrencyByName(string $name): Currency
    {
        $currency = $this->connection->createQueryBuilder()
            ->select("*")
            ->from("currencies")
            ->where("name = :name")
            ->setParameter("name", $name)
            ->executeQuery()
            ->fetchAssociative();
        if (!$currency) {
            throw new OutOfBoundsException("Currency not found ($name)");
        }
        return Currency::fromArray($currency);
    }

    public function getCurrencyByCode(string $currencyCode): Currency
    {
        $currency = $this->connection->createQueryBuilder()
            ->select("*")
            ->from("currencies")
            ->where("code = :code")
            ->setParameter("code", $currencyCode)
            ->executeQuery()
            ->fetchAssociative();
        if (!$currency) {
            throw new OutOfBoundsException("Currency not found ($currencyCode)");
        }
        return Currency::fromArray($currency);
    }

    public function exists(string $code): bool
    {
        $currency = $this->connection->createQueryBuilder()
            ->select("*")
            ->where("code = :code")
            ->setParameter("code", $code)
            ->executeQuery()
            ->fetchOne();
        if (!$currency) {
            return false;
        }
        return true;
    }

    public function isEmpty()
    {
        return $this->connection->createQueryBuilder()
            ->select("*")
            ->from("currencies")
            ->executeQuery()
            ->fetchOne() === false;
    }
}