<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\MarketData\CoinTickerSyncService;
use App\Support\QueueName;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncTopCoinTickers implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 300;

    public int $uniqueFor = 240;

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

    public function handle(CoinTickerSyncService $sync): void
    {
        $sync->syncTopCoins();
    }
}
