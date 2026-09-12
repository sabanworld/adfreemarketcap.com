<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Currency\CurrencyRateSyncService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncCurrencyRates implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    public int $uniqueFor = 240;

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [30, 60, 120];
    }

    public function handle(CurrencyRateSyncService $sync): void
    {
        $sync->syncRates();
    }
}
