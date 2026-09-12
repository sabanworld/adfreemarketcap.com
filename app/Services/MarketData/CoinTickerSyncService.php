<?php

declare(strict_types=1);

namespace App\Services\MarketData;

use App\Models\Coin;
use App\Models\CoinTicker;
use App\Models\SyncRun;
use App\Services\MarketData\DTOs\CoinTickerData;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

class CoinTickerSyncService
{
    public function __construct(
        private readonly CoinGeckoProvider $coingecko,
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
            $count = $this->persistTickers($coin);
            $run->markSucceeded($count, "Synced {$count} tickers for {$coin->slug}.");

            return $run->fresh();
        } catch (Throwable $exception) {
            $run->markFailed($exception->getMessage());

            throw $exception;
        }
    }

    public function syncTopCoins(): SyncRun
    {
        $limit = max(1, (int) config('marketdata.sync.tickers_top_coins', 25));
        $coins = Coin::query()->whereNotNull('rank')->orderBy('rank')->limit($limit)->get();

        $run = SyncRun::query()->create([
            'type' => 'coin_tickers_batch',
            'status' => SyncRun::STATUS_RUNNING,
            'provider' => 'coingecko',
            'started_at' => now(),
        ]);

        try {
            $processed = 0;

            foreach ($coins as $coin) {
                $this->persistTickers($coin);
                $processed++;
                usleep(200_000);
            }

            $run->markSucceeded($processed, "Synced tickers for {$processed} top coins.");

            return $run->fresh();
        } catch (Throwable $exception) {
            $run->markFailed($exception->getMessage());

            throw $exception;
        }
    }

    private function persistTickers(Coin $coin): int
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

        for ($page = 1; $page <= $pages; $page++) {
            $tickers = $tickers->concat($this->coingecko->fetchCoinTickers($externalId, $page));
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

        return $tickers->count();
    }
}
