<?php

declare(strict_types=1);

namespace App\Services\MarketData\DTOs;

use Illuminate\Support\Carbon;

final readonly class CoinTickerData
{
    public function __construct(
        public string $exchangeId,
        public string $exchangeName,
        public string $baseSymbol,
        public string $targetSymbol,
        public string $pair,
        public ?float $priceUsd,
        public ?float $lastPrice,
        public ?float $volume24hUsd,
        public ?float $bidAskSpreadPercent,
        public ?string $trustScore,
        public bool $isAnomaly,
        public bool $isStale,
        public ?string $tradeUrl,
        public ?Carbon $lastTradedAt,
    ) {}
}
