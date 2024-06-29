<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\TransactionRepository;
use App\Repositories\UserRepository;
use Doctrine\DBAL\DriverManager;

class TransactionController
{
    private TransactionRepository $transactionRepository;
    private UserRepository $userRepository;

    public function __construct()
    {
        $connectionParams = [
            "driver" => "pdo_sqlite",
            "path" => "storage/database.sqlite",
        ];
        $connection = DriverManager::getConnection($connectionParams);

        $this->transactionRepository = new TransactionRepository($connection);
        $this->userRepository = new userRepository($connection);
    }

    public function index(): array
    {
        if (!isset($_GET["user"])) {
            header("Location: /404");
            die;
        }
        $transactions = $this->transactionRepository->getByUser($this->userRepository->findByUsername($_GET["user"]));
        return ["transactions/index", ["transactions" => $transactions]];
    }
}