<?php
declare(strict_types=1);

namespace App\Repositories\Transaction;

use App\Models\Currency;
use App\Models\Money;
use App\Models\Transaction;
use App\Models\User;
use App\Repositories\Transaction\Exceptions\CurrencyNotInTransactionsException;
use DateTimeInterface;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\PDO\Exception;

class DoctrineDbalTransactionRepository implements TransactionRepositoryInterface
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
                    "received_ticker" => $currency->ticker(),
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
                "sent_amount" => $sentMoney->amount(),
                "sent_ticker" => $sentMoney->ticker(),
                "type" => $transaction->type(),
                "received_amount" => $receivedMoney->amount(),
                "received_ticker" => $receivedMoney->ticker(),
                "created_at" => $transaction
                    ->createdAt()
                    ->timezone("UTC")
                    ->format(DateTimeInterface::ATOM),
            ])
            ->executeStatement();
    }

    public function getAveragePrice(string $userId, Money $money): float
    {
        $amounts = $this->getByUserAndCurrency($userId, $money->currency());
        $amounts = array_reverse($amounts);

        $received = 0;
        $spent = 0;
        foreach ($amounts as $amount) {
            $received += $amount["received_amount"];
            $spent += $amount["sent_amount"];

            if ($received >= $money->amount()) { // TODO: maybe account for floating point imprecision here
                return $spent / $received;
            }
        }
        return 0;
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
}