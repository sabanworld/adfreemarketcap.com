<?php

declare(strict_types=1);

namespace App\Services\MarketData\DTOs;

final readonly class FearGreedData
{
    public function __construct(
        public int $value,
        public string $classification,
    ) {}
}
