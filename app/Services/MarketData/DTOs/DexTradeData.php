<?php

declare(strict_types=1);

namespace App\Services\MarketData\DTOs;

final readonly class DexTradeData
{
    public function __construct(
        public ?string $txHash,
        public ?string $kind,
        public ?float $priceUsd,
        public ?float $volumeUsd,
        public ?string $fromTokenAmount,
        public ?string $toTokenAmount,
        public ?string $traderAddress,
        public ?string $tradedAt,
    ) {}
}
