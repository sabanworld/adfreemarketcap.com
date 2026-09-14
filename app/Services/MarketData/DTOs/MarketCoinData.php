<?php

declare(strict_types=1);

namespace App\Services\MarketData\DTOs;

final readonly class MarketCoinData
{
    /**
     * @param  list<float|int>|null  $sparkline7d
     */
    public function __construct(
        public string $externalId,
        public string $symbol,
        public string $name,
        public ?string $imageUrl,
        public ?int $rank,
        public ?float $price,
        public ?float $percentChange1h,
        public ?float $percentChange24h,
        public ?float $percentChange7d,
        public ?float $marketCap,
        public ?float $volume24h,
        public ?float $circulatingSupply,
        public ?array $sparkline7d = null,
        public ?float $percentChange30d = null,
        public ?float $percentChange200d = null,
        public ?float $percentChange1y = null,
    ) {}
}
