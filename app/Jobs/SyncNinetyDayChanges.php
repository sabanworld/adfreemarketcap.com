<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\MarketData\NinetyDayChangeSyncService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncNinetyDayChanges implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    /**
     * One chart request per sampled coin, so the timeout covers roughly fifty sequential calls.
     */
    public int $timeout = 600;

    public int $uniqueFor = 3600;

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [120, 600];
    }

    public function handle(NinetyDayChangeSyncService $sync): void
    {
        $sync->sync();
    }
}
