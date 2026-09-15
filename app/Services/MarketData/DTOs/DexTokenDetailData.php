<?php

declare(strict_types=1);

namespace App\Services\MarketData\DTOs;

final readonly class DexTokenDetailData
{
    /**
     * @param  list<DexPairData>  $pools
     * @param  list<DexTradeData>  $trades
     * @param  list<DexHolderData>  $holders
     * @param  array<string, list<array{0: int, 1: float}>>  $chartSeries
     */
    public function __construct(
        public string $networkId,
        public string $address,
        public string $symbol,
        public ?string $name,
        public ?string $coingeckoCoinId,
        public ?float $price,
        public ?float $percentChange24h,
        public ?float $fdvUsd,
        public ?float $marketCapUsd,
        public ?float $liquidityUsd,
        public ?float $volume24h,
        public ?int $holdersCount,
        public array $pools,
        public array $trades,
        public array $holders,
        public array $chartSeries,
        public bool $holdersUnavailable = false,
    ) {}
}
