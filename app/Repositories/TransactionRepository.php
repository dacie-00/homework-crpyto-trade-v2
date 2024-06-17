<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Models\Transaction;
use DateTimeInterface;
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
        $sentMoney = $transaction->sentMoney();
        $receivedMoney = $transaction->receivedMoney();
        $this->connection->createQueryBuilder()
            ->insert("transactions")
            ->values([
                "id" => ":id",
                "sent_amount" => ":sent_amount",
                "sent_ticker" => ":sent_ticker",
                "type" => ":type",
                "received_amount" => ":received_amount",
                "received_ticker" => ":received_ticker",
                "created_at" => ":created_at",
            ])
            ->setParameters([
                "id" =>$transaction->id(),
                "sent_amount" => $sentMoney->getAmount(),
                "sent_ticker" => $sentMoney->getCurrency()->getCurrencyCode(),
                "type" => $transaction->type(),
                "received_amount" => $receivedMoney->getAmount(),
                "received_ticker" => $receivedMoney->getCurrency()->getCurrencyCode(),
                "created_at" => $transaction
                    ->createdAt()
                    ->timezone("UTC")
                    ->format(DateTimeInterface::ATOM),
            ])
            ->executeStatement();
    }
}