<?php

declare(strict_types=1);

namespace App\Services\MarketData;

use App\Http\Controllers\SitemapController;
use App\Models\Coin;
use App\Models\CoinProviderId;
use App\Models\MarketGlobal;
use App\Models\SyncRun;
use App\Services\MarketData\DTOs\MarketCoinData;
use App\Services\MarketData\DTOs\PercentChangeData;
use App\Services\MarketData\Exceptions\ProviderCoinNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class MarketSyncService
{
    public function __construct(
        private readonly MarketDataAggregator $aggregator,
        private readonly UnknownProviderCoinCleaner $cleaner,
        private readonly CryptoApisProvider $cryptoApis,
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

            $fetched = [];

            for ($page = 1; $page <= $pages; $page++) {
                $result = $this->aggregator->fetchMarketsWithFailover($page, $perPage);
                $providerUsed = $result['provider'];
                $fetched[] = $result;
            }

            $precise = $this->precisePercentChanges($fetched);

            foreach ($fetched as $result) {
                $processed += $this->upsertMarketCoinsPage(
                    $result['coins'],
                    $result['provider'],
                    $result['provider'] === 'coingecko' ? $precise : collect(),
                );
            }

            $run->update(['provider' => $providerUsed]);
            $run->markSucceeded($processed, "Synced {$processed} coins.");

            Coin::forgetRankedCountCache();
            Cache::forget(SitemapController::CACHE_KEY);

            return $run->fresh();
        } catch (Throwable $throwable) {
            $run->markFailed($throwable->getMessage());

            throw $throwable;
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
                'market_cap_change_percentage_24h' => $global->marketCapChangePercentage24h,
                'active_cryptocurrencies' => $global->activeCryptocurrencies,
                'provider' => $result['provider'],
                'synced_at' => now(),
            ]);

            MarketGlobal::forgetLatestSnapshotCache();

            $run->update(['provider' => $result['provider']]);
            $run->markSucceeded(1, 'Synced global market stats.');

            return $run->fresh();
        } catch (Throwable $throwable) {
            $run->markFailed($throwable->getMessage());

            throw $throwable;
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
                'detail_synced_at' => now(),
            ]);

            $run->update(['provider' => $providerId->provider]);
            $run->markSucceeded(1, "Synced detail for {$coin->slug}.");

            return $run->fresh();
        } catch (ProviderCoinNotFoundException $exception) {
            $message = $this->cleaner->clean($coin, $exception->provider, $exception->externalId);
            $run->update(['provider' => $exception->provider]);
            $run->markSucceeded(0, $message);

            return $run->fresh();
        } catch (Throwable $exception) {
            $run->markFailed($exception->getMessage());

            throw $exception;
        }
    }

    /**
     * CoinGecko rounds the 1h and 7d percentages on /coins/markets to 0.1, so a
     * quiet hour reads as 0.00% next to CoinMarketCap. Crypto APIs carries the
     * same figures at full precision. A ticker that more than one coin in the
     * ranking uses is left alone rather than guessed at, and a Crypto APIs
     * outage keeps the CoinGecko values instead of failing the run.
     *
     * @param  array<int, array{provider: string, coins: Collection<int, MarketCoinData>}>  $fetched
     * @return Collection<string, PercentChangeData>
     */
    private function precisePercentChanges(array $fetched): Collection
    {
        $symbols = collect($fetched)
            ->filter(fn (array $result): bool => $result['provider'] === 'coingecko')
            ->flatMap(fn (array $result): Collection => $result['coins'])
            ->map(fn (MarketCoinData $coin): string => Str::upper($coin->symbol));

        if ($symbols->isEmpty() || ! $this->cryptoApis->isConfigured()) {
            return collect();
        }

        $counts = $symbols->countBy();

        try {
            return $this->cryptoApis
                ->fetchPercentChangesBySymbol($symbols->count())
                ->filter(fn (PercentChangeData $changes, string $symbol): bool => $counts->get($symbol) === 1);
        } catch (Throwable $throwable) {
            report($throwable);

            return collect();
        }
    }

    /**
     * Resolve every coin on a page with a handful of lookups, then write inside one
     * transaction. The old path ran provider-id + coin + updateOrCreate SELECTs per row.
     *
     * @param  Collection<int, MarketCoinData>  $coins
     * @param  Collection<string, PercentChangeData>  $precise
     */
    private function upsertMarketCoinsPage(Collection $coins, string $provider, Collection $precise): int
    {
        if ($coins->isEmpty()) {
            return 0;
        }

        $externalIds = $coins->map(fn (MarketCoinData $coin): string => $coin->externalId)->all();
        $slugsByExternalId = [];
        foreach ($coins as $coinData) {
            $slugsByExternalId[$coinData->externalId] = $this->slugFor($coinData, $provider);
        }

        /** @var Collection<string, CoinProviderId> $mapsByExternalId */
        $mapsByExternalId = CoinProviderId::query()
            ->with('coin')
            ->where('provider', $provider)
            ->whereIn('external_id', $externalIds)
            ->get()
            ->keyBy('external_id');

        $missingSlugs = [];
        foreach ($slugsByExternalId as $externalId => $slug) {
            $map = $mapsByExternalId->get($externalId);
            if ($map instanceof CoinProviderId && $map->coin instanceof Coin) {
                continue;
            }

            $missingSlugs[] = $slug;
        }

        /** @var Collection<string, Coin> $coinsBySlug */
        $coinsBySlug = $missingSlugs === []
            ? collect()
            : Coin::query()->whereIn('slug', array_values(array_unique($missingSlugs)))->get()->keyBy('slug');

        $knownCoinIds = $mapsByExternalId
            ->map(fn (CoinProviderId $map): int => (int) $map->coin_id)
            ->merge($coinsBySlug->map(fn (Coin $coin): int => $coin->id))
            ->unique()
            ->values()
            ->all();

        /** @var Collection<int, CoinProviderId> $providerIdsByCoinId */
        $providerIdsByCoinId = $knownCoinIds === []
            ? collect()
            : CoinProviderId::query()
                ->where('provider', $provider)
                ->whereIn('coin_id', $knownCoinIds)
                ->get()
                ->keyBy('coin_id');

        DB::transaction(function () use (
            $coins,
            $provider,
            $precise,
            $slugsByExternalId,
            $mapsByExternalId,
            $coinsBySlug,
            $providerIdsByCoinId,
        ): void {
            foreach ($coins as $coinData) {
                $slug = $slugsByExternalId[$coinData->externalId];
                $map = $mapsByExternalId->get($coinData->externalId);

                $coin = ($map instanceof CoinProviderId ? $map->coin : null)
                    ?? $coinsBySlug->get($slug)
                    ?? new Coin(['slug' => $slug]);

                if (! $coin->exists) {
                    $coin->slug = $slug;
                }

                $coin->fill([
                    'symbol' => $coinData->symbol,
                    'name' => $coinData->name,
                    'image_url' => $coinData->imageUrl ?? $coin->image_url,
                    'rank' => $coinData->rank,
                    'price' => $coinData->price,
                    'percent_change_1h' => $precise->get(Str::upper($coinData->symbol))?->percentChange1h
                        ?? $coinData->percentChange1h,
                    'percent_change_24h' => $coinData->percentChange24h,
                    'percent_change_7d' => $precise->get(Str::upper($coinData->symbol))?->percentChange7d
                        ?? $coinData->percentChange7d,
                    // percent_change_90d is deliberately absent: no ranking provider reports a
                    // 90-day window, so NinetyDayChangeSyncService derives it from chart history.
                    // Listing it here would overwrite that with null on every ranking sync.
                    'percent_change_30d' => $coinData->percentChange30d,
                    'percent_change_200d' => $coinData->percentChange200d,
                    'percent_change_1y' => $coinData->percentChange1y,
                    'market_cap' => $coinData->marketCap,
                    'volume_24h' => $coinData->volume24h,
                    'circulating_supply' => $coinData->circulatingSupply,
                    'sparkline_7d' => $coinData->sparkline7d ?? $coin->sparkline_7d,
                    'last_provider' => $provider,
                    'market_synced_at' => now(),
                ]);
                $coin->save();

                if (! $coinsBySlug->has($slug)) {
                    $coinsBySlug->put($slug, $coin);
                }

                $providerMap = $providerIdsByCoinId->get($coin->id);
                if ($providerMap instanceof CoinProviderId) {
                    $providerMap->external_id = $coinData->externalId;
                    $providerMap->save();
                } else {
                    $providerMap = CoinProviderId::query()->create([
                        'coin_id' => $coin->id,
                        'provider' => $provider,
                        'external_id' => $coinData->externalId,
                    ]);
                    $providerIdsByCoinId->put($coin->id, $providerMap);
                }

                $mapsByExternalId->put($coinData->externalId, $providerMap);
            }
        });

        return $coins->count();
    }

    private function slugFor(MarketCoinData $data, string $provider): string
    {
        if ($provider === 'coingecko') {
            return Str::slug($data->externalId);
        }

        return Str::slug($data->name . '-' . $data->symbol);
    }
}
