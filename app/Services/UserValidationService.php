<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Repositories\UserRepository;

class UserValidationService
{
    private UserRepository $userRepository;

    public function __construct(UserRepository $userRepository)
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