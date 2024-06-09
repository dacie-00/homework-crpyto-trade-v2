<?php
declare(strict_types=1);

namespace App;

use App\Crypto\Crypto;
use LogicException;
use OutOfBoundsException;
use RuntimeException;

class Wallet
{
    private array $contents;
    private int $id;

    public function add(Crypto $currency, int $amount): void
    {
        if (!isset($this->contents[$currency->symbol()])) {
            $this->contents[$currency->symbol()] = $amount;
            return;
        }
        $this->contents[$currency->symbol()] += $amount;
    }

    public function subtract(Crypto $currency, int $amount): void
    {
        if (!isset($this->contents[$currency->symbol()])) {
            throw new OutOfBoundsException("Currency {$currency->symbol()} does not exist in wallet");
        }
        if ($this->contents[$currency->symbol()] - $amount < 0) {
            throw new RuntimeException(
                "Not enough {$currency->symbol()} in wallet. 
                Requested - $amount, In wallet - {$this->contents[$currency->symbol()]}"
            );
        }
        $this->contents[$currency->symbol()] -= $amount;
    }

    public function contents(): array
    {
        return $this->contents;
    }
}