<?php
declare(strict_types=1);

namespace App\Repositories\Currency;

use App\Models\ExtendedCurrency;

interface CurrencyRepositoryInterface
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