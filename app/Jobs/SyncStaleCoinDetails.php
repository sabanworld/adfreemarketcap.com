<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Coin;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;

/**
 * Coin detail (description + 7 day chart) is normally refreshed when a visitor
 * opens a stale coin page. Highly ranked coins should not wait for that visit,
 * so this walks the top of the ranking a few coins at a time.
 */
class SyncStaleCoinDetails implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 60;

    public int $uniqueFor = 300;

    public function handle(): int
    {
        $scope = max(1, (int) config('marketdata.sync.detail_backfill_coins', 20));
        $batch = max(1, (int) config('marketdata.sync.detail_backfill_batch', 5));
        $hours = max(1, (int) config('marketdata.sync.coin_detail_stale_hours', 6));

        $ranked = Coin::query()
            ->whereNotNull('rank')
            ->orderBy('rank')
            ->limit($scope)
            ->pluck('id');

        if ($ranked->isEmpty()) {
            return 0;
        }

        $cutoff = Carbon::now()->subHours($hours);

        $stale = Coin::query()
            ->whereIn('id', $ranked)
            ->where(function ($query) use ($cutoff): void {
                $query->whereNull('detail_synced_at')
                    ->orWhere('detail_synced_at', '<=', $cutoff);
            })
            ->orderBy('detail_synced_at')
            ->orderBy('rank')
            ->limit($batch)
            ->pluck('id');

        foreach ($stale as $coinId) {
            SyncCoinDetail::dispatch((int) $coinId);
        }

        return $stale->count();
    }
}
