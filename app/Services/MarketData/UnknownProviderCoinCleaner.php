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
     * Drop a provider id CoinGecko (or another source) no longer recognizes.
     * When the coin has no provider mappings left, delete it and cascade
     * related rows. Otherwise clear markets data and demote it from rankings.
     */
    public function clean(Coin $coin, string $provider, string $externalId): string
    {
        $slug = $coin->slug;
        $deleted = false;

        DB::transaction(function () use ($coin, $provider, $externalId, &$deleted): void {
            CoinProviderId::query()
                ->where('coin_id', $coin->id)
                ->where('provider', $provider)
                ->where('external_id', $externalId)
                ->delete();

            CoinTicker::query()->where('coin_id', $coin->id)->delete();

            $coin->unsetRelation('providerIds');

            if ($coin->providerIds()->doesntExist()) {
                $coin->delete();
                $deleted = true;

                return;
            }

            $coin->update([
                'rank' => null,
                'tickers_synced_at' => now(),
            ]);
        });

        if ($deleted) {
            return "Removed delisted coin {$slug} ({$provider} id [{$externalId}]).";
        }

        return "Cleared unknown {$provider} id [{$externalId}] for {$slug}.";
    }
}
