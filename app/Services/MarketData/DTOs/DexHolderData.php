<?php

declare(strict_types=1);

namespace App\Services\MarketData\DTOs;

final readonly class DexHolderData
{
    public function __construct(
        public int $rank,
        public string $address,
        public ?string $label,
        public ?string $amount,
        public ?float $percentage,
        public ?float $valueUsd,
        public ?string $explorerUrl,
    ) {}
}
