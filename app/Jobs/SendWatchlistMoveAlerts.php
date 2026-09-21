<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Watchlist\WatchlistPriceAlertService;
use App\Support\QueueName;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendWatchlistMoveAlerts implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 300;

    public int $uniqueFor = 600;

    public function __construct()
    {
        $this->onQueue(QueueName::MAIL);
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [30, 60, 120];
    }

    public function handle(WatchlistPriceAlertService $alerts): void
    {
        $alerts->sendMoveAlerts();
    }
}
