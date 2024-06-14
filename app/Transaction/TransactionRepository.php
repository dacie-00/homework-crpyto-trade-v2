<?php
declare(strict_types=1);

namespace App\Transaction;

use Doctrine\DBAL\Connection;

class TransactionRepository
{
    private Connection $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * @return Transaction[]
     */
    public function getAll(): array
    {
        $transactions = [];
        $transactionData = $this->connection->createQueryBuilder()
            ->select("*")
            ->from("transactions")
            ->executeQuery();
        foreach ($transactionData->fetchAllAssociative() as $transaction) {
            $transactions[] = Transaction::fromArray($transaction);
        }
        return $transactions;
    }

    public function add(Transaction $transaction): void
    {
        $this->connection->createQueryBuilder()
            ->insert("transactions")
            ->values([
                "amount_in" => ":amount_in",
                "currency_in" => ":currency_in",
                "amount_out" => ":amount_out",
                "currency_out" => ":currency_out",
                "created_at" => ":created_at",
            ])
            ->setParameters([
                "amount_in" => $transaction->amountIn(),
                "currency_in" => $transaction->currencyIn(),
                "amount_out" => $transaction->amountOut(),
                "currency_out" => $transaction->currencyOut(),
                "created_at" => $transaction->createdAt(),
            ])
            ->executeStatement();
    }
}