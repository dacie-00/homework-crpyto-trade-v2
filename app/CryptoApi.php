<?php

namespace App;

interface CryptoApi
{
    /**
     * @return Currency[]
     */
    public function getTop(int $range, int $listingCount): array;

    /**
     * @param string[] $currencyCodes
     * @return Currency[]
     */
    public function search(array $currencyCodes): array;
}