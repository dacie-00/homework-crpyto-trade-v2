<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Models\User;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

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

    public function findByUsername($username): ?User
    {
        $user = $this->connection->createQueryBuilder()
            ->select("*")
            ->from("users")
            ->where("username = :username")
            ->setParameter("username", $username)
            ->executeQuery()
            ->fetchAssociative();
        return $user !== false ?
            new User(
                $user["username"],
                $user["password"],
                $user["user_id"]
            ) : null;
    }

    /**
     * @return User[]
     */
    public function getAll(): array
    {
        $usersData = $this->connection->createQueryBuilder()
            ->select("*")
            ->from("users")
            ->executeQuery()
            ->fetchAllAssociative();
        $users = [];
        foreach($usersData as $userData) {
            $users[] = new User(
                $userData["username"],
                $userData["password"],
                $userData["user_id"]
            );
        }
        return $users;
    }
}