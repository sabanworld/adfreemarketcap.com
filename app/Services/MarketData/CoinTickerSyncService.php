<?php

declare(strict_types=1);

namespace App\Services\MarketData;

use App\Models\Coin;
use App\Models\CoinTicker;
use App\Models\SyncRun;
use App\Services\MarketData\DTOs\CoinTickerData;
use App\Services\MarketData\Exceptions\ProviderCoinNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

class CoinTickerSyncService
{
    public function __construct(
        private readonly CoinGeckoProvider $coingecko,
        private readonly UnknownProviderCoinCleaner $cleaner,
    ) {}

    public function syncCoin(Coin $coin): SyncRun
    {
        $run = SyncRun::query()->create([
            'type' => 'coin_tickers',
            'status' => SyncRun::STATUS_RUNNING,
            'provider' => 'coingecko',
            'message' => $coin->slug,
            'started_at' => now(),
        ]);

        try {
            $result = $this->persistTickers($coin);
            $run->markSucceeded($result['count'], $result['message']);

            return $run->fresh();
        } catch (Throwable $exception) {
            $run->markFailed($exception->getMessage());

            throw $exception;
        }
    }

    public function syncTopCoins(): SyncRun
    {
        $limit = max(0, (int) config('marketdata.sync.tickers_top_coins', 0));

        $run = SyncRun::query()->create([
            'type' => 'coin_tickers_batch',
            'status' => SyncRun::STATUS_RUNNING,
            'provider' => 'coingecko',
            'started_at' => now(),
        ]);

        if ($limit < 1) {
            $run->markSucceeded(0, 'Skipped top-coin ticker sync (MARKETDATA_TICKERS_TOP_COINS is 0).');

            return $run->fresh();
        }

        $coins = Coin::query()->whereNotNull('rank')->orderBy('rank')->limit($limit)->get();

        try {
            $processed = 0;
            $removed = 0;
            $failed = 0;

            foreach ($coins as $coin) {
                try {
                    $result = $this->persistTickers($coin);
                    if ($result['removed']) {
                        $removed++;
                    } else {
                        $processed++;
                    }
                } catch (Throwable $exception) {
                    $failed++;
                    report($exception);
                }

                usleep(200_000);
            }

            $message = "Synced tickers for {$processed} top coins.";
            if ($removed > 0) {
                $message .= " Cleaned up {$removed} unknown or delisted ids.";
            }
            if ($failed > 0) {
                $message .= " Failed {$failed} after provider errors.";
            }

            $run->markSucceeded($processed, $message);

            return $run->fresh();
        } catch (Throwable $exception) {
            $run->markFailed($exception->getMessage());

            throw $exception;
        }
    }

    public function syncHotCoins(): SyncRun
    {
        /** @var list<string> $slugs */
        $slugs = array_values(array_filter(
            array_map('strval', config('marketdata.sync.hot_coins', [])),
        ));

        $run = SyncRun::query()->create([
            'type' => 'coin_tickers_hot',
            'status' => SyncRun::STATUS_RUNNING,
            'provider' => 'coingecko',
            'started_at' => now(),
            'message' => implode(',', $slugs),
        ]);

        if ($slugs === []) {
            $run->markSucceeded(0, 'Skipped hot ticker sync (MARKETDATA_HOT_COINS is empty).');

            return $run->fresh();
        }

        $coins = Coin::query()
            ->where(function ($query) use ($slugs): void {
                $query->whereIn('slug', $slugs)
                    ->orWhereHas('providerIds', function ($providerQuery) use ($slugs): void {
                        $providerQuery->where('provider', 'coingecko')->whereIn('external_id', $slugs);
                    });
            })
            ->get()
            ->unique('id')
            ->values();

        try {
            $processed = 0;
            $removed = 0;
            $failed = 0;
            $missing = count($slugs) - $coins->count();

            foreach ($coins as $coin) {
                try {
                    $result = $this->persistTickers($coin);
                    if ($result['removed']) {
                        $removed++;
                    } else {
                        $processed++;
                    }
                } catch (Throwable $exception) {
                    $failed++;
                    report($exception);
                }

                usleep(200_000);
            }

            $message = "Synced tickers for {$processed} hot coins.";
            if ($removed > 0) {
                $message .= " Cleaned up {$removed} unknown or delisted ids.";
            }
            if ($failed > 0) {
                $message .= " Failed {$failed} after provider errors.";
            }
            if ($missing > 0) {
                $message .= " {$missing} configured hot slug(s) not in the database yet.";
            }

            $run->markSucceeded($processed, $message);

            return $run->fresh();
        } catch (Throwable $exception) {
            $run->markFailed($exception->getMessage());

            throw $exception;
        }
    }

    /**
     * @return array{count: int, removed: bool, message: string}
     */
    private function persistTickers(Coin $coin): array
    {
        $externalId = $coin->providerIds()
            ->where('provider', 'coingecko')
            ->value('external_id');

        if (! is_string($externalId) || $externalId === '') {
            $externalId = $coin->slug;
        }

        $pages = max(1, min(5, (int) config('marketdata.sync.tickers_pages', 1)));
        /** @var Collection<int, CoinTickerData> $tickers */
        $tickers = collect();

        try {
            for ($page = 1; $page <= $pages; $page++) {
                $tickers = $tickers->concat($this->coingecko->fetchCoinTickers($externalId, $page));
            }
        } catch (ProviderCoinNotFoundException $exception) {
            $message = $this->cleaner->clean($coin, $exception->provider, $exception->externalId);

            return [
                'count' => 0,
                'removed' => true,
                'message' => $message,
            ];
        }

        $tickers = $tickers
            ->unique(fn (CoinTickerData $ticker): string => $ticker->exchangeId . '|' . $ticker->baseSymbol . '|' . $ticker->targetSymbol)
            ->sortByDesc(fn (CoinTickerData $ticker): float => $ticker->volume24hUsd ?? 0.0)
            ->values();

        $totalVolume = $tickers->sum(fn (CoinTickerData $ticker): float => $ticker->volume24hUsd ?? 0.0);

        DB::transaction(function () use ($coin, $tickers, $totalVolume): void {
            CoinTicker::query()->where('coin_id', $coin->id)->delete();

            $rows = [];
            foreach ($tickers as $index => $ticker) {
                $share = null;
                if ($totalVolume > 0 && $ticker->volume24hUsd !== null) {
                    $share = ($ticker->volume24hUsd / $totalVolume) * 100;
                }

                $rows[] = [
                    'coin_id' => $coin->id,
                    'provider' => 'coingecko',
                    'exchange_id' => $ticker->exchangeId,
                    'exchange_name' => $ticker->exchangeName,
                    'base_symbol' => $ticker->baseSymbol,
                    'target_symbol' => $ticker->targetSymbol,
                    'pair' => $ticker->pair,
                    'price_usd' => $ticker->priceUsd,
                    'last_price' => $ticker->lastPrice,
                    'volume_24h_usd' => $ticker->volume24hUsd,
                    'volume_share_percent' => $share,
                    'bid_ask_spread_percent' => $ticker->bidAskSpreadPercent,
                    'trust_score' => $ticker->trustScore,
                    'is_anomaly' => $ticker->isAnomaly,
                    'is_stale' => $ticker->isStale,
                    'trade_url' => $ticker->tradeUrl,
                    'rank' => $index + 1,
                    'last_traded_at' => $ticker->lastTradedAt,
                    'synced_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            foreach (array_chunk($rows, 100) as $chunk) {
                CoinTicker::query()->insert($chunk);
            }

            $coin->update(['tickers_synced_at' => now()]);
        });

        return [
            'count' => $tickers->count(),
            'removed' => false,
            'message' => "Synced {$tickers->count()} tickers for {$coin->slug}.",
        ];
    }
}
