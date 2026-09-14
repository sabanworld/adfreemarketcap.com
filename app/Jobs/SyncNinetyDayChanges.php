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
     * Deliberately under the Redis connection's `retry_after` (130s in `config/queue.php`). A job
     * allowed to outrun that window gets re-reserved and processed twice, which here would mean
     * two workers making the same fifty chart requests. The service works to a shorter budget and
     * leaves whatever it did not reach to the next run.
     */
    public int $timeout = 110;

    public int $uniqueFor = 600;

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [60, 300];
    }

    public function handle(NinetyDayChangeSyncService $sync): void
    {
        $sync->sync();
    }
}
