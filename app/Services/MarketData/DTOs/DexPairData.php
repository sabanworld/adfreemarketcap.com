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
        public ?string $networkId,
        public ?string $contractAddress,
        public ?string $baseTokenAddress,
        public ?string $quoteTokenAddress,
        public ?string $baseTokenName,
        public ?string $coingeckoCoinId,
        public string $auditStatus,
        public ?float $price,
        public ?float $percentChange24h,
        public ?float $liquidityUsd,
        public ?float $volume24h,
        public ?float $volume1h,
        public ?float $volume6h,
        public ?float $fdvUsd,
        public ?float $marketCapUsd,
        public ?int $txns24h,
        public ?int $buys24h,
        public ?int $sells24h,
        public ?Carbon $pairedAt,
        public bool $isTrending,
        public ?int $rank,
    ) {}
}
