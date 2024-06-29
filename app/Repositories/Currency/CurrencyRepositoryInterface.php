<?php
declare(strict_types=1);

namespace App\Repositories\Currency;

use App\Models\Currency;
use App\Repositories\Currency\Exceptions\CurrencyNotFoundException;

interface CurrencyRepositoryInterface
{
    /**
     * @return Currency[]
     */
    public function getTop(int $page, int $currenciesPerPage): array;

    /**
     * @param string[] $currencyCodes
     * @return Currency[]
     * @throws CurrencyNotFoundException
     */
    public function search(array $currencyCodes): array;
}