<?php

declare(strict_types=1);

namespace App\Services\MarketData;

use App\Models\Coin;
use App\Models\MarketStatusSnapshot;
use App\Models\SyncRun;
use Throwable;

class MarketStatusSyncService
{
    public function __construct(
        private readonly FearGreedProvider $fearGreed,
        private readonly MarketStatusCalculator $calculator,
    ) {}

    public function sync(): SyncRun
    {
        $run = SyncRun::query()->create([
            'type' => 'market_status',
            'status' => SyncRun::STATUS_RUNNING,
            'provider' => 'alternative.me+local',
            'started_at' => now(),
        ]);

        try {
            $fearGreed = $this->fearGreed->fetchLatest();
            $afmc10 = $this->computeAfmc10();
            $altcoinSeason = $this->computeAltcoinSeason();

            MarketStatusSnapshot::query()->create([
                'fear_greed_value' => $fearGreed->value,
                'fear_greed_classification' => $fearGreed->classification,
                'afmc10_value' => $afmc10['value'],
                'afmc10_change_24h' => $afmc10['change_24h'],
                'afmc10_change_7d' => $afmc10['change_7d'],
                'afmc10_change_30d' => $afmc10['change_30d'],
                'afmc10_change_200d' => $afmc10['change_200d'],
                'afmc10_change_1y' => $afmc10['change_1y'],
                'afmc10_base_sum' => $afmc10['base_sum'],
                'altcoin_season_index' => $altcoinSeason['index'],
                'altcoin_season_sample_size' => $altcoinSeason['sample_size'],
                'provider' => 'alternative.me+local',
                'synced_at' => now(),
            ]);

            MarketStatusSnapshot::forgetLatestSnapshotCache();

            $run->markSucceeded(1, 'Synced fear and greed, AFMC10, and altcoin season.');

            return $run->fresh();
        } catch (Throwable $exception) {
            $run->markFailed($exception->getMessage());

            throw $exception;
        }
    }

    /**
     * @return array{
     *     value: float|null,
     *     change_24h: float|null,
     *     change_7d: float|null,
     *     change_30d: float|null,
     *     change_200d: float|null,
     *     change_1y: float|null,
     *     base_sum: float|null,
     *     basket_sum: float|null
     * }
     */
    private function computeAfmc10(): array
    {
        /** @var list<string> $slugs */
        $slugs = config('marketdata.afmc10', []);
        $coins = Coin::query()
            ->whereIn('slug', $slugs)
            ->get([
                'slug',
                'market_cap',
                'percent_change_24h',
                'percent_change_7d',
                'percent_change_30d',
                'percent_change_200d',
                'percent_change_1y',
            ])
            ->keyBy('slug');

        $constituents = [];
        foreach ($slugs as $slug) {
            $coin = $coins->get($slug);
            $constituents[] = [
                'market_cap' => $coin?->market_cap !== null ? (float) $coin->market_cap : null,
                'percent_change_24h' => $coin?->percent_change_24h !== null ? (float) $coin->percent_change_24h : null,
                'percent_change_7d' => $coin?->percent_change_7d !== null ? (float) $coin->percent_change_7d : null,
                'percent_change_30d' => $coin?->percent_change_30d !== null ? (float) $coin->percent_change_30d : null,
                'percent_change_200d' => $coin?->percent_change_200d !== null ? (float) $coin->percent_change_200d : null,
                'percent_change_1y' => $coin?->percent_change_1y !== null ? (float) $coin->percent_change_1y : null,
            ];
        }

        $existingBase = MarketStatusSnapshot::query()
            ->whereNotNull('afmc10_base_sum')
            ->orderBy('id')
            ->value('afmc10_base_sum');

        return $this->calculator->afmc10(
            $constituents,
            $existingBase !== null ? (float) $existingBase : null,
        );
    }

    /**
     * @return array{index: float|null, sample_size: int}
     */
    private function computeAltcoinSeason(): array
    {
        $topN = max(1, (int) config('marketdata.altcoin_season.top_n', 50));
        /** @var list<string> $excludeSymbols */
        $excludeSymbols = array_map(
            strtoupper(...),
            config('marketdata.altcoin_season.exclude_symbols', []),
        );

        $btc = Coin::query()->where('slug', 'bitcoin')->first(['percent_change_90d']);
        $btcChange = $btc?->percent_change_90d !== null ? (float) $btc->percent_change_90d : null;

        $alts = Coin::query()
            ->whereNotNull('rank')
            ->where('slug', '!=', 'bitcoin')
            ->whereNotIn('symbol', $excludeSymbols)
            ->orderBy('rank')
            ->limit($topN)
            ->get(['percent_change_90d']);

        $changes = $alts
            ->map(fn (Coin $coin): ?float => $coin->percent_change_90d !== null ? (float) $coin->percent_change_90d : null)
            ->all();

        return $this->calculator->altcoinSeason($btcChange, $changes);
    }
}
