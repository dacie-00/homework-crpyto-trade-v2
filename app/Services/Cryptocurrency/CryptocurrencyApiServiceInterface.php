<?php
declare(strict_types=1);

namespace App\Services\Cryptocurrency;

use App\Models\ExtendedCurrency;

interface CryptocurrencyApiServiceInterface
{
    /**
     * @return ExtendedCurrency[]
     */
    public function getTop(int $page, int $currenciesPerPage): array;

    /**
     * @param string[] $currencyCodes
     * @return ExtendedCurrency[]
     */
    public function search(array $currencyCodes): array;
}