<?php

namespace App;

use JsonSerializable;
use Ramsey\Uuid\Uuid;

class User implements JsonSerializable
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

    public function jsonSerialize(): array
    {
        return [
            "name" => $this->name,
            "cash" => $this->cash,
            "id" => $this->id
        ];
    }
}