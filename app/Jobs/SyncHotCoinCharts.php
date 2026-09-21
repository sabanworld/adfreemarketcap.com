<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\MarketData\CoinChartSyncService;
use App\Support\QueueName;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncHotCoinCharts implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 600;

    public int $uniqueFor = 480;

    public function __construct()
    {
        $this->onQueue(QueueName::HEAVY);
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [30, 60];
    }

    public function handle(CoinChartSyncService $sync): void
    {
        $sync->syncHotCoins();
    }
}
