<?php
declare(strict_types=1);

namespace App\Models;

use Ramsey\Uuid\Uuid;

class Wallet
{
    private string $userId;
    private string $id;
    /**
     * @var Money[]
     */
    private array $contents;

    /**
     * @param Money[] $contents
     */
    public function __construct(string $userId, string $id = null, array $contents = [])
    {
        $this->userId = $userId;
        $this->id = $id ?: Uuid::uuid4()->toString();
        $this->contents = $contents;
    }

    public function userId(): string
    {
        return $this->userId;
    }

    public function id(): string
    {
        return $this->id;
    }

    /**
     * @return Money[]
     */
    public function contents(): array
    {
        return $this->contents;
    }
}