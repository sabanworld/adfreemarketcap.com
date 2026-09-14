<?php

declare(strict_types=1);

namespace App\Services\MarketData\DTOs;

final readonly class GlobalMarketData
{
    public function __construct(
        public ?float $totalMarketCap,
        public ?float $totalVolume24h,
        public ?float $btcDominance,
        public ?int $activeCryptocurrencies,
        public ?float $marketCapChangePercentage24h = null,
    ) {}
}
