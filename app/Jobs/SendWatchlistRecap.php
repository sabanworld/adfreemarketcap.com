<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Watchlist\WatchlistPriceAlertService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendWatchlistRecap implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 300;

    public int $uniqueFor = 3600;

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [60, 120, 300];
    }

    public function handle(WatchlistPriceAlertService $alerts): void
    {
        $alerts->sendDailyRecaps();
    }
}
