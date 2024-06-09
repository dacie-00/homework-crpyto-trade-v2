<?php

namespace App;

class ExchangeService
{
    public function exchange(Wallet $wallet, int $amount, Currency $fromCurrency, Currency $toCurrency): Transaction
    {
        $wallet->subtract($fromCurrency, $amount);
        $amountToAdd = (int)($amount * ($fromCurrency->exchangeRate() / $toCurrency->exchangeRate()));
        $wallet->add($toCurrency, $amountToAdd);
        return new Transaction(
            $amount,
            $fromCurrency->ticker(),
            $amountToAdd,
            $toCurrency->ticker(),
        );
    }
}