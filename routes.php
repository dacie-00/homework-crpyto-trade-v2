<?php
declare(strict_types=1);

use App\Controllers\CurrencyController;
use App\Controllers\ErrorController;
use App\Controllers\TransactionController;
use App\Controllers\WalletController;

return [
    ["GET", "/", [CurrencyController::class, "index"]],
    ["GET", "/404", [ErrorController::class, "index"]],
    ["GET", "/currencies", [CurrencyController::class, "index"]],
    ["GET", "/currencies/{ticker}", [CurrencyController::class, "show"]],
    ["POST", "/currencies/{ticker}/buy", [CurrencyController::class, "buy"]],
    ["POST", "/currencies/{ticker}/sell", [CurrencyController::class, "sell"]],
    ["GET", "/transactions", [TransactionController::class, "index"]],
    ["GET", "/wallets/{id}", [WalletController::class, "show"]],
];