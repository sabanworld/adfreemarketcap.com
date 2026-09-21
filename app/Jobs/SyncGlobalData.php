<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\MarketData\MarketSyncService;
use App\Support\QueueName;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncGlobalData implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    public int $uniqueFor = 240;

    public function __construct()
    {
        $this->onQueue(QueueName::SYNC);
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [30, 60, 120];
    }

    public function handle(MarketSyncService $sync): void
    {
        $sync->syncGlobal();
    }
}
