<?php

declare(strict_types=1);

namespace App\Services\MarketData;

use Illuminate\Support\Str;

final class DexNetwork
{
    /**
     * @var array<string, string>
     */
    public const LABELS = [
        'eth' => 'Ethereum',
        'solana' => 'Solana',
        'bsc' => 'BNB Chain',
        'base' => 'Base',
        'arbitrum' => 'Arbitrum',
        'polygon_pos' => 'Polygon',
        'avalanche' => 'Avalanche',
        'optimism' => 'Optimism',
        'ton' => 'TON',
        'sui-network' => 'Sui',
        'robinhood' => 'Robinhood Chain',
    ];

    public static function label(string $networkId): string
    {
        if ($networkId === '') {
            return 'Unknown';
        }

        return self::LABELS[$networkId]
            ?? Str::title(str_replace(['-', '_'], ' ', $networkId));
    }

    public static function idFromChainLabel(?string $chain): ?string
    {
        if (! filled($chain)) {
            return null;
        }

        $needle = Str::lower(trim($chain));

        foreach (self::LABELS as $id => $label) {
            if (Str::lower($label) === $needle) {
                return $id;
            }
        }

        return match ($needle) {
            'bnb', 'bnb chain', 'binance smart chain' => 'bsc',
            'ethereum', 'ether' => 'eth',
            'polygon' => 'polygon_pos',
            'robinhood chain', 'robinhood' => 'robinhood',
            default => null,
        };
    }

    public static function idFromExternalId(?string $externalId): ?string
    {
        if (! filled($externalId)) {
            return null;
        }

        $pos = strpos($externalId, '_');
        if ($pos === false || $pos === 0) {
            return null;
        }

        return substr($externalId, 0, $pos);
    }

    public static function resolve(?string $networkId, ?string $chain, ?string $externalId): ?string
    {
        if (filled($networkId)) {
            return $networkId;
        }

        return self::idFromExternalId($externalId) ?? self::idFromChainLabel($chain);
    }
}
