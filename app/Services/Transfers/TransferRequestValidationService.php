<?php
declare(strict_types=1);

namespace App\Services\Transfers;

use App\Services\Transfers\Exceptions\InvalidTransferAmountException;
use App\Services\Transfers\Exceptions\InvalidTransferCurrencyTickerException;
use App\Services\Transfers\Exceptions\InvalidTransferTypeException;

class TransferRequestValidationService
{
    public function validate(array $params): void
    {
        if (!isset($params["type"]) || !in_array($params["type"], ["buy", "sell"])) {
            throw new InvalidTransferTypeException("Invalid transfer type");
        }
        if (!isset($params["amount"])) {
            throw new InvalidTransferAmountException("Missing amount");
        }
        if (!is_numeric($params["amount"])) {
            throw new InvalidTransferAmountException("Amount can only contain numbers");
        }
        if (!isset($params["currency"])) {
            throw new InvalidTransferCurrencyTickerException("Missing currency ticker");
        }
        if (!ctype_alpha($params["currency"])) {
            throw new InvalidTransferCurrencyTickerException("Currency can only contain letters");
        }
    }
}