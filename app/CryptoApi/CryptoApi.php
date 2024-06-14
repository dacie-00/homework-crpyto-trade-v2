<?php

namespace App\CryptoApi;

use App\Currency;

interface CryptoApi
{
    /**
     * @return Currency[]
     */
    public function getTop(int $page, int $currenciesPerPage): array;

    /**
     * @param string[] $currencyCodes
     * @return Currency[]
     */
    public function search(array $currencyCodes): array;
}