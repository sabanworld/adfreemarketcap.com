<?php

declare(strict_types=1);

namespace App\Services\MarketData\DTOs;

final readonly class CoinDetailData
{
    /**
     * @param  list<array{0: int|float, 1: float}>|null  $chart7d
     */
    public function __construct(
        public string $externalId,
        public ?string $description,
        public ?array $chart7d = null,
    ) {}
}
