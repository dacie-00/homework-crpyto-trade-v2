<?php
declare(strict_types=1);

namespace App\Repositories\Currency;

use App\Models\ExtendedCurrency;
use App\Repositories\Currency\Exceptions\CurrencyNotFoundException;

interface CurrencyRepositoryInterface
{
    /**
     * @return ExtendedCurrency[]
     */
    public function getTop(int $page, int $currenciesPerPage): array;

    /**
     * @param string[] $currencyCodes
     * @return ExtendedCurrency[]
     * @throws CurrencyNotFoundException
     */
    public function search(array $currencyCodes): array;
}