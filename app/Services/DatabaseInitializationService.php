<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Repositories\User\DoctrineDbalUserRepository;
use App\Repositories\User\UserRepositoryInterface;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Table;

class DatabaseInitializationService
{
    private Connection $connection;
    private AbstractSchemaManager $schemaManager;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
        $this->schemaManager = $connection->createSchemaManager();
    }

    public function initializeUsersTable(): void
    {
        if (!$this->schemaManager->tablesExist(["users"])) {
            $table = new Table("users");
            $table->addColumn("user_id", "string");
            $table->setPrimaryKey(["user_id"]);

            $table->addColumn("username", "string");
            $table->addColumn("password", "string");
            $this->schemaManager->createTable($table);

            (new DoctrineDbalUserRepository($this->connection))->insert(
                new User("JaneDoe", md5("password123"), "JaneDoe")
            );
            (new DoctrineDbalUserRepository($this->connection))->insert(
                new User("foobar", md5("foobar"), "foobar")
            );
            (new DoctrineDbalUserRepository($this->connection))->insert(
                new User("sillyGoose", md5("quack"), "sillyGoose")
            );
        }
    }

    public function initializeTransactionsTable(): void
    {
        if (!$this->schemaManager->tablesExist(["transactions"])) {
            $table = new Table("transactions");
            $table->addColumn("transaction_id", "string");
            $table->setPrimaryKey(["transaction_id"]);
            $table->addColumn("user_id", "string");
            $table->addForeignKeyConstraint("users", ["user_id"], ["user_id"]);

            $table->addColumn("sent_amount", "decimal");
            $table->addColumn("sent_ticker", "string");
            $table->addColumn("type", "string");
            $table->addColumn("received_amount", "decimal");
            $table->addColumn("received_ticker", "string");
            $table->addColumn("created_at", "string");
            $this->schemaManager->createTable($table);
        }
    }

    public function initializeWalletsTable(UserRepositoryInterface $userRepository): void
    {
        if (!$this->schemaManager->tablesExist(["wallet"])) {
            $table = new Table("wallet");
            $table->addColumn("wallet_id", "string");
            $table->addColumn("user_id", "string");
            $table->addForeignKeyConstraint("users", ["user_id"], ["user_id"]);

            $table->addColumn("ticker", "string");
            $table->addColumn("amount", "decimal");
            $this->schemaManager->createTable($table);

            foreach ($userRepository->getAll() as $user) {
                $this->connection->insert("wallet", [
                    "wallet_id" => $user->username() . "Wallet",
                    "user_id" => $user->id(),
                    "ticker" => "EUR",
                    "amount" => 1000,
                ]);
            }
        }
    }

}