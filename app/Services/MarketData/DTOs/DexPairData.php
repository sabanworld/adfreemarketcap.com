<?php

declare(strict_types=1);

namespace App\Services\MarketData\DTOs;

use Illuminate\Support\Carbon;

final readonly class DexPairData
{
    public function __construct(
        public string $externalId,
        public string $pair,
        public string $baseSymbol,
        public string $quoteSymbol,
        public string $dex,
        public string $chain,
        public ?string $contractAddress,
        public string $auditStatus,
        public ?float $price,
        public ?float $percentChange24h,
        public ?float $liquidityUsd,
        public ?float $volume24h,
        public ?int $txns24h,
        public ?Carbon $pairedAt,
        public bool $isTrending,
        public ?int $rank,
    ) {}
}
