<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Models\Transaction;
use App\Models\User;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Brick\Money\Currency;
use DateTimeInterface;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

class TransactionRepository
{
    private Connection $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function getByUserAndCurrency(string $userId, Currency $currency): array
    {
        return $this->connection->createQueryBuilder()
            ->select("sent_amount, received_amount")
            ->from("transactions")
            ->where("user_id = :user_id and received_ticker = :received_ticker")
            ->setParameters(
                [
                    "user_id" => $userId,
                    "received_ticker" => $currency->getCurrencyCode(),
                ]
            )
            ->executeQuery()
            ->fetchAllAssociative();
    }

    public function add(Transaction $transaction): void
    {
        $sentMoney = $transaction->sentMoney();
        $receivedMoney = $transaction->receivedMoney();
        $this->connection->createQueryBuilder()
            ->insert("transactions")
            ->values([
                "transaction_id" => ":transaction_id",
                "user_id" => ":user_id",
                "sent_amount" => ":sent_amount",
                "sent_ticker" => ":sent_ticker",
                "type" => ":type",
                "received_amount" => ":received_amount",
                "received_ticker" => ":received_ticker",
                "created_at" => ":created_at",
            ])
            ->setParameters([
                "transaction_id" => $transaction->id(),
                "user_id" => $transaction->userId(),
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

    public function getAveragePrice(string $userId, Currency $currency, BigDecimal $until): ?BigDecimal
    {
        $amounts = $this->getByUserAndCurrency($userId, $currency);
        $amounts = array_reverse($amounts);

        $spent = BigDecimal::zero();
        $received = BigDecimal::zero();

        foreach ($amounts as $amount) {
            $received = $received->plus($amount["received_amount"]);
            $spent = $spent->plus($amount["sent_amount"]);

            if ($received->isGreaterThanOrEqualTo($until)) {
                return $spent->dividedBy($received, 9, RoundingMode::UP);
            }
        }
        return null;
    }

    /**
     * @return Transaction[]
     */
    public function getByUser(User $user): array
    {
        $transactions = [];
        $transactionData = $this->connection->createQueryBuilder()
            ->select("*")
            ->from("transactions")
            ->where("user_id = :user_id")
            ->setParameter("user_id", $user->id())
            ->executeQuery();
        foreach ($transactionData->fetchAllAssociative() as $transaction) {
            $transactions[] = Transaction::fromArray($transaction);
        }
        return $transactions;
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
}