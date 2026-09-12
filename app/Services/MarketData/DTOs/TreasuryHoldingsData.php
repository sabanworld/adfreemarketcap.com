<?php

declare(strict_types=1);

namespace App\Services\MarketData\DTOs;

final readonly class TreasuryHoldingsData
{
    /**
     * @param  list<TreasuryHolderData>  $companies
     */
    public function __construct(
        public string $coinExternalId,
        public float $totalHoldings,
        public ?float $totalValueUsd,
        public ?float $marketCapDominance,
        public array $companies,
    ) {}
}
