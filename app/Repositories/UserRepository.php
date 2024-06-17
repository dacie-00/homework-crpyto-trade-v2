<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Models\User;
use Doctrine\DBAL\Connection;

class UserRepository
{
    private Connection $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function insert(User $user): void
    {
        $this->connection->createQueryBuilder()
            ->insert("users")
            ->values(
                [
                    "user_id" => ":user_id",
                    "username" => ":username",
                    "password" => ":password",
                ]
            )
            ->setParameters([
                "user_id" => $user->id(),
                "username" => $user->username(),
                "password" => $user->password(),
            ])
            ->executeQuery();
    }
}