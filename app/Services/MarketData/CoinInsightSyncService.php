<?php

declare(strict_types=1);

namespace App\Services\MarketData;

use App\Models\Coin;
use App\Models\CoinMarketCycleSnapshot;
use App\Models\CoinTreasuryHolder;
use App\Models\CoinTreasurySnapshot;
use App\Models\SyncRun;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class CoinInsightSyncService
{
    public function __construct(
        private readonly CoinInsightProvider $provider,
    ) {}

    public function syncBitcoinInsights(): SyncRun
    {
        $run = SyncRun::query()->create([
            'type' => 'coin_insights',
            'status' => SyncRun::STATUS_RUNNING,
            'provider' => 'coingecko+bitcoin_com',
            'started_at' => now(),
        ]);

        try {
            $coin = Coin::query()->where('slug', 'bitcoin')->first();
            throw_unless($coin, new RuntimeException('Bitcoin coin row is missing; sync markets first.'));

            $processed = 0;
            $processed += $this->syncTreasury($coin, 'bitcoin');
            $processed += $this->syncMarketCycle($coin);

            $run->markSucceeded($processed, 'Synced Bitcoin treasury holdings and market cycles.');

            return $run->fresh();
        } catch (Throwable $throwable) {
            $run->markFailed($throwable->getMessage());

            throw $throwable;
        }
    }

    private function syncTreasury(Coin $coin, string $externalId): int
    {
        $data = $this->provider->fetchTreasuryHoldings($externalId);

        return (int) DB::transaction(function () use ($coin, $data): int {
            CoinTreasurySnapshot::query()->updateOrCreate(
                ['coin_id' => $coin->id],
                [
                    'total_holdings' => $data->totalHoldings,
                    'total_value_usd' => $data->totalValueUsd,
                    'market_cap_dominance' => $data->marketCapDominance,
                    'companies_count' => count($data->companies),
                    'provider' => 'coingecko',
                    'synced_at' => now(),
                ],
            );

            CoinTreasuryHolder::query()->where('coin_id', $coin->id)->delete();

            $rows = [];
            foreach ($data->companies as $holder) {
                $rows[] = [
                    'coin_id' => $coin->id,
                    'name' => $holder->name,
                    'symbol' => $holder->symbol,
                    'country' => $holder->country,
                    'total_holdings' => $holder->totalHoldings,
                    'total_entry_value_usd' => $holder->totalEntryValueUsd,
                    'total_current_value_usd' => $holder->totalCurrentValueUsd,
                    'percentage_of_total_supply' => $holder->percentageOfTotalSupply,
                    'rank' => $holder->rank,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            foreach (array_chunk($rows, 100) as $chunk) {
                CoinTreasuryHolder::query()->insert($chunk);
            }

            return 1 + count($rows);
        });
    }

    private function syncMarketCycle(Coin $coin): int
    {
        $data = $this->provider->fetchBitcoinMarketCycle();

        CoinMarketCycleSnapshot::query()->updateOrCreate(
            ['coin_id' => $coin->id],
            [
                'price' => $data->price,
                'ma111' => $data->ma111,
                'ma350x2' => $data->ma350x2,
                'ma_gap_percent' => $data->maGapPercent,
                'pi_cycle_status' => $data->piCycleStatus,
                'last_cross_at' => $data->lastCrossAt,
                'last_halving_at' => $data->lastHalvingAt,
                'next_halving_at' => $data->nextHalvingAt,
                'days_since_halving' => $data->daysSinceHalving,
                'days_until_halving' => $data->daysUntilHalving,
                'cycle_progress_percent' => $data->cycleProgressPercent,
                'halving_epoch' => $data->halvingEpoch,
                'chart_price' => $data->chartPrice,
                'chart_ma111' => $data->chartMa111,
                'chart_ma350x2' => $data->chartMa350x2,
                'provider' => 'bitcoin_com_charts',
                'synced_at' => now(),
            ],
        );

        return 1;
    }
}
