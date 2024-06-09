<?php
declare(strict_types=1);

namespace App;

use OutOfBoundsException;
use Ramsey\Uuid\Uuid;
use RuntimeException;

class Wallet
{
    private array $contents;
    private string $id;

    public function __construct(?string $id = null, ?array $contents = null)
    {
        $this->id = $id ?: Uuid::uuid4()->toString();
        $this->contents = $contents ?: [];
    }

    public function add(Currency $currency, int $amount): void
    {
        if (!isset($this->contents[$currency->ticker()])) {
            $this->contents[$currency->ticker()] = $amount;
            return;
        }
        $this->contents[$currency->ticker()] += $amount;
    }

    public function subtract(Currency $currency, int $amount): void
    {
        if (!isset($this->contents[$currency->ticker()])) {
            throw new OutOfBoundsException("Currency {$currency->ticker()} does not exist in wallet");
        }
        if ($this->contents[$currency->ticker()] - $amount < 0) {
            throw new RuntimeException(
                "Not enough {$currency->ticker()} in wallet. 
                Requested - $amount, In wallet - {$this->contents[$currency->ticker()]}"
            );
        }
        $this->contents[$currency->ticker()] -= $amount;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function contents(): array
    {
        return $this->contents;
    }

    public function getCurrencyAmount(string $ticker)
    {
        if (!isset($this->contents[$ticker])) {
            throw new OutOfBoundsException("Currency {$ticker} does not exist");
        }
        return $this->contents[$ticker];
    }
}