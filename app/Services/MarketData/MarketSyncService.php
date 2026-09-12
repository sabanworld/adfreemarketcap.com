<?php

declare(strict_types=1);

namespace App\Services\MarketData;

use App\Models\Coin;
use App\Models\CoinProviderId;
use App\Models\MarketGlobal;
use App\Models\SyncRun;
use App\Services\MarketData\DTOs\MarketCoinData;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class MarketSyncService
{
    public function __construct(
        private readonly MarketDataAggregator $aggregator,
    ) {}

    public function syncMarkets(): SyncRun
    {
        $run = SyncRun::query()->create([
            'type' => 'markets',
            'status' => SyncRun::STATUS_RUNNING,
            'started_at' => now(),
        ]);

        try {
            $pages = max(1, (int) config('marketdata.sync.markets_pages', 2));
            $perPage = max(1, min(250, (int) config('marketdata.sync.per_page', 100)));
            $processed = 0;
            $providerUsed = null;

            for ($page = 1; $page <= $pages; $page++) {
                $result = $this->aggregator->fetchMarketsWithFailover($page, $perPage);
                $providerUsed = $result['provider'];

                foreach ($result['coins'] as $coinData) {
                    $this->upsertMarketCoin($coinData, $providerUsed);
                    $processed++;
                }
            }

            $run->update(['provider' => $providerUsed]);
            $run->markSucceeded($processed, "Synced {$processed} coins.");

            return $run->fresh();
        } catch (Throwable $exception) {
            $run->markFailed($exception->getMessage());

            throw $exception;
        }
    }

    public function syncGlobal(): SyncRun
    {
        $run = SyncRun::query()->create([
            'type' => 'global',
            'status' => SyncRun::STATUS_RUNNING,
            'started_at' => now(),
        ]);

        try {
            $result = $this->aggregator->fetchGlobalWithFailover();
            $global = $result['global'];

            MarketGlobal::query()->create([
                'total_market_cap' => $global->totalMarketCap,
                'total_volume_24h' => $global->totalVolume24h,
                'btc_dominance' => $global->btcDominance,
                'active_cryptocurrencies' => $global->activeCryptocurrencies,
                'provider' => $result['provider'],
                'synced_at' => now(),
            ]);

            $run->update(['provider' => $result['provider']]);
            $run->markSucceeded(1, 'Synced global market stats.');

            return $run->fresh();
        } catch (Throwable $exception) {
            $run->markFailed($exception->getMessage());

            throw $exception;
        }
    }

    public function syncCoinDetail(Coin $coin): SyncRun
    {
        $run = SyncRun::query()->create([
            'type' => 'coin_detail',
            'status' => SyncRun::STATUS_RUNNING,
            'started_at' => now(),
            'message' => $coin->slug,
        ]);

        try {
            $providerId = $coin->providerIds()
                ->where('provider', $coin->last_provider ?? config('marketdata.primary'))
                ->first()
                ?? $coin->providerIds()->first();

            throw_unless($providerId, new RuntimeException("No provider id mapped for coin [{$coin->slug}]."));

            $detail = $this->aggregator->fetchCoinDetail($providerId->provider, $providerId->external_id);

            $coin->update([
                'description' => $detail->description ?? $coin->description,
                'chart_7d' => $detail->chart7d ?? $coin->chart_7d,
                'detail_synced_at' => now(),
            ]);

            $run->update(['provider' => $providerId->provider]);
            $run->markSucceeded(1, "Synced detail for {$coin->slug}.");

            return $run->fresh();
        } catch (Throwable $exception) {
            $run->markFailed($exception->getMessage());

            throw $exception;
        }
    }

    private function upsertMarketCoin(MarketCoinData $data, string $provider): void
    {
        DB::transaction(function () use ($data, $provider): void {
            $existingMap = CoinProviderId::query()
                ->where('provider', $provider)
                ->where('external_id', $data->externalId)
                ->first();

            $slug = $this->slugFor($data, $provider);

            $coin = $existingMap?->coin
                ?? Coin::query()->where('slug', $slug)->first()
                ?? new Coin(['slug' => $slug]);

            if (! $coin->exists) {
                $coin->slug = $slug;
            }

            $coin->fill([
                'symbol' => $data->symbol,
                'name' => $data->name,
                'image_url' => $data->imageUrl ?? $coin->image_url,
                'rank' => $data->rank,
                'price' => $data->price,
                'percent_change_1h' => $data->percentChange1h,
                'percent_change_24h' => $data->percentChange24h,
                'percent_change_7d' => $data->percentChange7d,
                'market_cap' => $data->marketCap,
                'volume_24h' => $data->volume24h,
                'circulating_supply' => $data->circulatingSupply,
                'sparkline_7d' => $data->sparkline7d ?? $coin->sparkline_7d,
                'last_provider' => $provider,
                'market_synced_at' => now(),
            ]);
            $coin->save();

            CoinProviderId::query()->updateOrCreate(
                [
                    'coin_id' => $coin->id,
                    'provider' => $provider,
                ],
                [
                    'external_id' => $data->externalId,
                ],
            );
        });
    }

    private function slugFor(MarketCoinData $data, string $provider): string
    {
        if ($provider === 'coingecko') {
            return Str::slug($data->externalId);
        }

        return Str::slug($data->name . '-' . $data->symbol);
    }
}
