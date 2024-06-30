<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Repositories\User\DoctrineDbalUserRepository;
use App\Repositories\User\UserRepositoryInterface;

class UserValidationService
{
    private DoctrineDbalUserRepository $userRepository;

    public function __construct(UserRepositoryInterface $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    public function login($username, $password): ?User
    {
        $user = $this->userRepository->findByUsername($username);

        if ($user === null || md5($password) !== $user->password()) {
            return null;
        }
        return $user;
    }
}