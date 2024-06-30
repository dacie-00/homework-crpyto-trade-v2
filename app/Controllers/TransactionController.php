<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\Transaction\TransactionRepositoryInterface;
use App\Repositories\User\UserRepositoryInterface;
use App\TemplateResponse;

class TransactionController
{
    private TransactionRepositoryInterface $transactionRepository;
    private UserRepositoryInterface $userRepository;

    public function __construct(
        TransactionRepositoryInterface $transactionRepository,
        UserRepositoryInterface $userRepository
    )
    {
        $this->transactionRepository = $transactionRepository;
        $this->userRepository = $userRepository;
    }

    public function index(): TemplateResponse
    {
        if (!isset($_GET["user"])) {
            header("Location: /404");
            die;
        }
        $transactions = $this->transactionRepository->getByUser($this->userRepository->findByUsername($_GET["user"]));
        return new TemplateResponse("transactions/index", ["transactions" => $transactions]);
    }
}