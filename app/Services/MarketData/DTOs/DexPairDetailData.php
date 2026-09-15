<?php

declare(strict_types=1);

namespace App\Services\MarketData\DTOs;

final readonly class DexPairDetailData
{
    /**
     * @param  list<DexTradeData>  $trades
     * @param  array<string, list<array{0: int, 1: float}>>  $chartSeries
     */
    public function __construct(
        public DexPairData $pair,
        public array $trades,
        public array $chartSeries,
    ) {}
}
