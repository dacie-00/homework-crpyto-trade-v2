<?php
declare(strict_types=1);

namespace App\Repositories\User;

use App\Models\User;

interface UserRepositoryInterface
{
    public function insert(User $user): void;

    public function findByUsername(string $username): ?User;

    /**
     * @return User[]
     */
    public function getAll(): array;

    public function findById(string $id): ?User;
}