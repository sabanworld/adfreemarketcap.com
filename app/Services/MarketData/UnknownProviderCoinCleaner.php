<?php

declare(strict_types=1);

namespace App\Services\MarketData;

use App\Models\Coin;
use App\Models\CoinProviderId;
use App\Models\CoinTicker;
use Illuminate\Support\Facades\DB;

class UnknownProviderCoinCleaner
{
    /**
     * Drop a provider id the upstream no longer recognizes, and remove ticker
     * rows for that coin. When the unknown id belongs to the primary provider
     * (or the coin has no mappings left), delete the coin. Never leave
     * rank-null zombies: those float to the top of ASC rank sorts.
     */
    public function clean(Coin $coin, string $provider, string $externalId): string
    {
        $slug = $coin->slug;
        $primary = (string) config('marketdata.primary', 'coingecko');
        $deleted = false;

        DB::transaction(function () use ($coin, $provider, $externalId, $primary, &$deleted): void {
            CoinProviderId::query()
                ->where('coin_id', $coin->id)
                ->where('provider', $provider)
                ->where('external_id', $externalId)
                ->delete();

            CoinTicker::query()->where('coin_id', $coin->id)->delete();

            $coin->unsetRelation('providerIds');

            $shouldDelete = $provider === $primary || $coin->providerIds()->doesntExist();

            if ($shouldDelete) {
                $coin->delete();
                $deleted = true;

                return;
            }

            $coin->update([
                'tickers_synced_at' => now(),
            ]);
        });

        if ($deleted) {
            return "Removed delisted coin {$slug} ({$provider} id [{$externalId}]).";
        }

        return "Cleared unknown {$provider} id [{$externalId}] for {$slug}.";
    }
}
