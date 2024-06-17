<?php
declare(strict_types=1);

namespace App\Models;

use Brick\Money\Money;

class Wallet
{
    private string $id;
    /**
     * @var Money[]
     */
    private array $contents;

    /**
     * @param string $id
     * @param Money[] $contents
     */
    public function __construct(string $id, array $contents = [])
    {
        $this->id = $id;
        $this->contents = $contents;
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