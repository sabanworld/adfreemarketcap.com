<?php

declare(strict_types=1);

namespace App\Services\MarketData\DTOs;

final readonly class PercentChangeData
{
    public function __construct(
        public ?float $percentChange1h,
        public ?float $percentChange7d,
    ) {}

    public function isEmpty(): bool
    {
        return $this->percentChange1h === null && $this->percentChange7d === null;
    }
}
