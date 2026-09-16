<?php

declare(strict_types=1);

namespace App\Services\MarketData;

use App\Models\Coin;
use App\Models\SyncRun;
use App\Services\MarketData\Exceptions\ProviderCoinNotFoundException;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Fills `coins.percent_change_90d` for the altcoin season sample.
 *
 * No ranking endpoint reports a 90-day window. CoinGecko's markets call accepts 1h, 24h, 7d, 14d,
 * 30d, 200d and 1y, and silently drops anything else, so the figure has to come from price
 * history: one 90-day chart per coin, first point against last.
 *
 * That is one HTTP call per coin, so a full pass is far too long to hold a queue worker: the Redis
 * connection re-reserves a job after `retry_after` (130s), and a job that outruns that window gets
 * processed twice. So a run works to a time budget under that window, skips coins whose figure is
 * still fresh, and leaves the rest to the next run. The schedule is hourly and almost every run is
 * a no-op, because a 90-day change only needs refreshing once a day.
 */
class NinetyDayChangeSyncService
{
    public function __construct(
        private readonly CoinGeckoProvider $coinGecko,
        private readonly CoinChartService $charts,
        private readonly AltcoinSeasonSampler $sampler,
        private readonly UnknownProviderCoinCleaner $cleaner,
    ) {}

    public function sync(): SyncRun
    {
        $run = SyncRun::query()->create([
            'type' => 'ninety_day_changes',
            'status' => SyncRun::STATUS_RUNNING,
            'provider' => $this->coinGecko->name(),
            'started_at' => now(),
        ]);

        try {
            $processed = 0;
            $skipped = 0;
            $remaining = 0;
            $deadline = now()->addSeconds($this->budgetSeconds());

            foreach ($this->sampler->sample() as $coin) {
                if ($this->isFresh($coin)) {
                    $skipped++;

                    continue;
                }

                // Stop before the budget rather than mid-request. The coins left over are still
                // stale, so the next hourly run picks them up where this one stopped.
                if (now()->greaterThanOrEqualTo($deadline)) {
                    $remaining++;

                    continue;
                }

                if ($this->syncCoin($coin)) {
                    $processed++;
                }
            }

            $message = "Derived {$processed} ninety-day changes, {$skipped} still fresh.";

            if ($remaining > 0) {
                $message .= " Ran out of time with {$remaining} left for the next run.";
            }

            $run->markSucceeded($processed, $message);

            return $run->fresh();
        } catch (Throwable $throwable) {
            $run->markFailed($throwable->getMessage());

            throw $throwable;
        }
    }

    public function syncCoin(Coin $coin): bool
    {
        $externalId = $this->charts->coingeckoExternalId($coin);

        if (! filled($externalId)) {
            return false;
        }

        try {
            $points = $this->coinGecko->fetchMarketChart($externalId, '90', 'daily');
        } catch (ProviderCoinNotFoundException $providerCoinNotFoundException) {
            $this->cleaner->clean($coin, $providerCoinNotFoundException->provider, $providerCoinNotFoundException->externalId);

            return false;
        }

        $change = $this->changeFrom($points);

        if ($change === null) {
            return false;
        }

        $coin->update([
            'percent_change_90d' => $change,
            'percent_change_90d_synced_at' => now(),
        ]);

        return true;
    }

    /**
     * The oldest point in the window against the newest. A short series is refused rather than
     * measured: a coin listed three weeks ago has no 90-day change, and calling its three-week
     * return a 90-day one would quietly hand the index a number it cannot compare.
     *
     * @param  list<array{0: int, 1: float}>  $points
     */
    public function changeFrom(array $points): ?float
    {
        if (count($points) < 2) {
            return null;
        }

        $first = $points[0];
        $last = $points[count($points) - 1];

        if ($first[1] <= 0.0) {
            return null;
        }

        $spanDays = ($last[0] - $first[0]) / 86_400_000;

        if ($spanDays < $this->minimumSpanDays()) {
            return null;
        }

        return 100.0 * (($last[1] / $first[1]) - 1.0);
    }

    private function isFresh(Coin $coin): bool
    {
        $syncedAt = $coin->percent_change_90d_synced_at;

        if (! $syncedAt instanceof Carbon || $coin->percent_change_90d === null) {
            return false;
        }

        $hours = max(1, (int) config('marketdata.sync.ninety_day_stale_hours', 20));

        return $syncedAt->greaterThan(now()->subHours($hours));
    }

    private function minimumSpanDays(): float
    {
        return max(1.0, (float) config('marketdata.altcoin_season.minimum_history_days', 80));
    }

    /**
     * Must stay clear of the queue's `retry_after`, or a long run is handed to a second worker
     * while the first is still making requests. Zero is honoured rather than floored, so the
     * budget doubles as a way to stop the API spend without touching the schedule.
     */
    private function budgetSeconds(): int
    {
        return max(0, (int) config('marketdata.sync.ninety_day_budget_seconds', 90));
    }
}
