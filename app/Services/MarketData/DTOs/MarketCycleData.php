<?php

declare(strict_types=1);

namespace App\Services\MarketData\DTOs;

use Illuminate\Support\Carbon;

final readonly class MarketCycleData
{
    /**
     * @param  list<array{0: int, 1: float}>  $chartPrice
     * @param  list<array{0: int, 1: float}>  $chartMa111
     * @param  list<array{0: int, 1: float}>  $chartMa350x2
     */
    public function __construct(
        public ?float $price,
        public ?float $ma111,
        public ?float $ma350x2,
        public ?float $maGapPercent,
        public string $piCycleStatus,
        public ?Carbon $lastCrossAt,
        public Carbon $lastHalvingAt,
        public Carbon $nextHalvingAt,
        public int $daysSinceHalving,
        public int $daysUntilHalving,
        public float $cycleProgressPercent,
        public int $halvingEpoch,
        public array $chartPrice,
        public array $chartMa111,
        public array $chartMa350x2,
    ) {}
}
