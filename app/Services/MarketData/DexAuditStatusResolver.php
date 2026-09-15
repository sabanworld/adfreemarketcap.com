<?php

declare(strict_types=1);

namespace App\Services\MarketData;

use App\Models\CoinPlatform;
use App\Services\MarketData\DTOs\DexPairData;

/**
 * Decides dex_pairs.audit_status from facts we already store.
 *
 * verified  = base token contract matches a Markets coin platform (ranked coin)
 * partial   = GeckoTerminal linked a CoinGecko coin id, but no Markets platform hit
 * unverified = neither
 */
final class DexAuditStatusResolver
{
    public const STATUS_VERIFIED = 'verified';

    public const STATUS_PARTIAL = 'partial';

    public const STATUS_UNVERIFIED = 'unverified';

    /**
     * GeckoTerminal network ids → CoinGecko asset_platform ids on coin_platforms.
     *
     * @var array<string, string>
     */
    public const NETWORK_TO_PLATFORM = [
        'eth' => 'ethereum',
        'bsc' => 'binance-smart-chain',
        'solana' => 'solana',
        'base' => 'base',
        'arbitrum' => 'arbitrum-one',
        'polygon_pos' => 'polygon-pos',
        'optimism' => 'optimistic-ethereum',
        'avalanche' => 'avalanche',
        'ton' => 'the-open-network',
        'sui-network' => 'sui',
    ];

    /**
     * Chains whose contract addresses are case-sensitive (not hex).
     *
     * @var list<string>
     */
    private const CASE_SENSITIVE_NETWORKS = [
        'solana',
        'sui-network',
        'ton',
    ];

    public function resolve(?string $networkId, ?string $baseTokenAddress, ?string $coingeckoCoinId): string
    {
        if ($this->listedOnMarkets($networkId, $baseTokenAddress)) {
            return self::STATUS_VERIFIED;
        }

        if (filled($coingeckoCoinId)) {
            return self::STATUS_PARTIAL;
        }

        return self::STATUS_UNVERIFIED;
    }

    public function resolveFromPairData(DexPairData $pair): string
    {
        return $this->resolve($pair->networkId, $pair->baseTokenAddress, $pair->coingeckoCoinId);
    }

    public function listedOnMarkets(?string $networkId, ?string $baseTokenAddress): bool
    {
        if (! filled($networkId) || ! filled($baseTokenAddress)) {
            return false;
        }

        $platformId = self::NETWORK_TO_PLATFORM[$networkId] ?? null;
        if (! is_string($platformId) || $platformId === '') {
            return false;
        }

        $query = CoinPlatform::query()
            ->where('platform_id', $platformId)
            ->whereNotNull('contract_address')
            ->whereHas('coin', function ($coinQuery): void {
                $coinQuery->whereNotNull('rank');
            });

        if (in_array($networkId, self::CASE_SENSITIVE_NETWORKS, true)) {
            $query->whereRaw('BINARY `contract_address` = ?', [$baseTokenAddress]);
        } else {
            $query->whereRaw('LOWER(`contract_address`) = ?', [strtolower($baseTokenAddress)]);
        }

        return $query->exists();
    }
}
