<?php

declare(strict_types=1);

namespace App\Services\MarketData;

use App\Models\Coin;
use App\Models\CoinChartSeries;
use App\Models\SyncRun;
use App\Services\MarketData\Exceptions\ProviderCoinNotFoundException;
use Throwable;

class CoinChartSyncService
{
    public function __construct(
        private readonly CoinGeckoProvider $coinGecko,
        private readonly CoinChartService $charts,
        private readonly UnknownProviderCoinCleaner $cleaner,
    ) {}

    /**
     * @param  list<string>|null  $seriesKeys
     */
    public function syncCoin(Coin $coin, ?array $seriesKeys = null): SyncRun
    {
        $run = SyncRun::query()->create([
            'type' => 'coin_charts',
            'status' => SyncRun::STATUS_RUNNING,
            'started_at' => now(),
            'message' => $coin->slug,
            'provider' => $this->coinGecko->name(),
        ]);

        try {
            $externalId = $this->charts->coingeckoExternalId($coin);

            if (! filled($externalId)) {
                $run->markSucceeded(0, "No CoinGecko id mapped for coin [{$coin->slug}].");

                return $run->fresh();
            }

            $coin->loadMissing('chartSeries');

            $keys = $seriesKeys ?? $this->charts->staleSeriesKeys($coin);

            if ($keys === []) {
                $run->markSucceeded(0, "Charts already fresh for {$coin->slug}.");

                return $run->fresh();
            }

            $processed = 0;

            foreach ($keys as $series) {
                if (! in_array($series, CoinChartSeries::SERIES_KEYS, true)) {
                    continue;
                }

                $fetch = CoinChartService::SERIES_FETCH[$series];
                $points = $this->coinGecko->fetchMarketChart(
                    $externalId,
                    $fetch['days'],
                    $fetch['interval'],
                );

                $this->charts->upsertSeries($coin, $series, $points);
                $processed++;
            }

            $run->markSucceeded($processed, "Synced {$processed} chart series for {$coin->slug}.");

            return $run->fresh();
        } catch (ProviderCoinNotFoundException $exception) {
            $message = $this->cleaner->clean($coin, $exception->provider, $exception->externalId);
            $run->markSucceeded(0, $message);

            return $run->fresh();
        } catch (Throwable $exception) {
            $run->markFailed($exception->getMessage());

            throw $exception;
        }
    }

    public function syncHotCoins(): SyncRun
    {
        $run = SyncRun::query()->create([
            'type' => 'hot_coin_charts',
            'status' => SyncRun::STATUS_RUNNING,
            'started_at' => now(),
            'provider' => $this->coinGecko->name(),
        ]);

        try {
            $slugs = (array) config('marketdata.sync.hot_coins', []);

            if ($slugs === []) {
                $run->markSucceeded(0, 'Skipped hot chart sync (MARKETDATA_HOT_COINS is empty).');

                return $run->fresh();
            }

            $coins = Coin::query()
                ->with([
                    'providerIds' => fn ($query) => $query->where('provider', 'coingecko'),
                    'chartSeries',
                ])
                ->whereIn('slug', $slugs)
                ->get();
            $processed = 0;

            foreach ($coins as $coin) {
                $child = $this->syncCoin($coin);
                $processed += (int) $child->records_processed;
            }

            $run->markSucceeded($processed, 'Synced chart series for hot majors.');

            return $run->fresh();
        } catch (Throwable $exception) {
            $run->markFailed($exception->getMessage());

            throw $exception;
        }
    }
}
