<?php

declare(strict_types=1);

namespace App\Services\MarketData\DTOs;

final readonly class ProviderCallSummary
{
    public function __construct(
        public string $provider,
        public int $hour,
        public int $day,
        public int $month,
    ) {}
}
