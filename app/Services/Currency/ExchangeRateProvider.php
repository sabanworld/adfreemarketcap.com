<?php

declare(strict_types=1);

namespace App\Services\Currency;

use Illuminate\Support\Collection;

interface ExchangeRateProvider
{
    public function name(): string;

    /**
     * Rates expressed as "how many units of the currency one USD buys",
     * keyed by lowercase currency code (usd, eur, btc, sats, …).
     *
     * @return Collection<string, float>
     */
    public function fetchRatesPerUsd(): Collection;
}
