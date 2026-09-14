<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\MarketData\CoinPlatformSyncService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncCoinPlatforms implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 180;

    public int $uniqueFor = 3600;

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [60, 120, 300];
    }

    public function handle(CoinPlatformSyncService $sync): void
    {
        $sync->sync();
    }
}
