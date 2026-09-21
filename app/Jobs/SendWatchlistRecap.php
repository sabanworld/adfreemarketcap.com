<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Watchlist\WatchlistPriceAlertService;
use App\Support\QueueName;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendWatchlistRecap implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 300;

    public int $uniqueFor = 3600;

    public function __construct(public string $period = 'daily')
    {
        $this->onQueue(QueueName::MAIL);
    }

    public function uniqueId(): string
    {
        return 'watchlist-recap-' . $this->period;
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [60, 120, 300];
    }

    public function handle(WatchlistPriceAlertService $alerts): void
    {
        if ($this->period === 'weekly') {
            $alerts->sendWeeklyRecaps();

            return;
        }

        $alerts->sendDailyRecaps();
    }
}
