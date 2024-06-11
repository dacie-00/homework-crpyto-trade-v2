<?php
declare(strict_types=1);

namespace App;

use Brick\Money\Money;
use JsonSerializable;
use OutOfBoundsException;
use Ramsey\Uuid\Uuid;
use UnexpectedValueException;

class Wallet implements JsonSerializable
{
    /**
     * @var Money[]
     */
    private array $contents;
    private string $id;

    public function __construct(?string $id = null, ?array $contents = null, ?CurrencyRepository $currencies = null)
    {
        $this->id = $id ?: Uuid::uuid4()->toString();
        if (!$contents) {
            return;
        }
        if (!$currencies) {
            throw new UnexpectedValueException("If wallet contents is provided then currencies must be provided too");
        }
        foreach ($contents as $currencyCode => $money) {
            $this->contents[$currencyCode] = Money::of($money, $currencies->getCurrencyByCode($currencyCode));
        }
    }

    public function add(Money $money): void
    {
        if (!isset($this->contents[$money->getcurrency()->getcurrencycode()])) {
            $this->contents[$money->getcurrency()->getcurrencycode()] = $money;
            return;
        }
        $currentMoney = $this->contents[$money->getcurrency()->getcurrencycode()];
        $this->contents[$money->getcurrency()->getcurrencycode()] = $currentMoney->plus($money);
    }

    public function subtract(Money $money): void
    {
        $currentMoney = $this->contents[$money->getcurrency()->getcurrencycode()];
        $this->contents[$money->getcurrency()->getcurrencycode()] = $currentMoney->minus($money);
        if ($this->contents[$money->getcurrency()->getcurrencycode()]->isNegativeOrZero()) {
            unset($this->contents[$money->getcurrency()->getcurrencycode()]);
        }
    }

    public function id(): string
    {
        return $this->id;
    }

    public function contents(): array
    {
        return $this->contents;
    }

    public function getMoney(string $currencyCode): Money
    {
        if (!isset($this->contents[$currencyCode])) {
            throw new OutOfBoundsException("CryptoCurrency {$currencyCode} does not exist");
        }
        return $this->contents[$currencyCode];
    }

    public function jsonSerialize(): array
    {
        $serializedContents = [];
        foreach ($this->contents as $content) {
            $serializedContents[$content->getCurrency()->getCurrencyCode()] = $content->getAmount();
        }
        return [
            $this->id,
            $serializedContents,
        ];
    }
}