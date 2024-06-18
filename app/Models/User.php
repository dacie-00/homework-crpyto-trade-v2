<?php
declare(strict_types=1);

namespace App\Models;

use Ramsey\Uuid\Uuid;

class User
{
    private string $username;
    private string $password;
    private string $id;

    public function __construct(
        string $username,
        string $password,
        ?string $id = null
    ) {
        $this->username = $username;
        $this->password = $password;
        $this->id = $id ?: Uuid::uuid4()->toString();
    }

    public function username(): string
    {
        return $this->username;
    }

    public function password(): string
    {
        return $this->password;
    }

    public function id(): string
    {
        return $this->id;
    }
}