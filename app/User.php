<?php

namespace App;

use Ramsey\Uuid\Uuid;

class User
{
    private string $name;
    private int $cash;
    private ?string $id;

    public function __construct(string $name, int $cash, ?string $id)
    {
        $this->name = $name;
        $this->cash = $cash;
        $this->id = $id ?: Uuid::uuid4()->toString();
    }
}