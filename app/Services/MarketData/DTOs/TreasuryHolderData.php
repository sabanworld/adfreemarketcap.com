<?php

declare(strict_types=1);

namespace App\Services\MarketData\DTOs;

final readonly class TreasuryHolderData
{
    public function __construct(
        public string $name,
        public ?string $symbol,
        public ?string $country,
        public float $totalHoldings,
        public ?float $totalEntryValueUsd,
        public ?float $totalCurrentValueUsd,
        public ?float $percentageOfTotalSupply,
        public int $rank,
    ) {}
}
