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
                "sent_amount" => ":sent_amount",
                "sent_currency_code" => ":sent_currency_code",
                "sent_currency_name" => ":sent_currency_name",
                "type" => ":type",
                "received_amount" => ":received_amount",
                "received_currency_code" => ":received_currency_code",
                "received_currency_name" => ":received_currency_name",
                "created_at" => ":created_at",
            ])
            ->setParameters([
                "sent_amount" => $sentMoney->getAmount(),
                "sent_currency_code" => $sentMoney->getCurrency()->getCurrencyCode(),
                "sent_currency_name" => $sentMoney->getCurrency()->getName(),
                "type" => $transaction->type(),
                "received_amount" => $receivedMoney->getAmount(),
                "received_currency_code" => $receivedMoney->getCurrency()->getCurrencyCode(),
                "received_currency_name" => $receivedMoney->getCurrency()->getName(),
                "created_at" => $transaction
                    ->createdAt()
                    ->timezone("UTC")
                    ->format(DateTimeInterface::ATOM),
            ])
            ->executeStatement();
    }
}