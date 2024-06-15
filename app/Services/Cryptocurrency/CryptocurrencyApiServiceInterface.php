<?php

namespace App\Services\Cryptocurrency;

use App\Models\Currency;

interface CryptocurrencyApiServiceInterface
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