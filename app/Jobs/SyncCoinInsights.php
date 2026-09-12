<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\MarketData\CoinInsightSyncService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncCoinInsights implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    public int $uniqueFor = 600;

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [30, 60, 120];
    }

    public function handle(CoinInsightSyncService $sync): void
    {
        $sync->syncBitcoinInsights();
    }
}
