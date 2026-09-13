<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Coin;
use App\Services\MarketData\CoinChartSyncService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncCoinCharts implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 180;

    public int $uniqueFor = 300;

    /**
     * @param  list<string>|null  $seriesKeys
     */
    public function __construct(
        public int $coinId,
        public ?array $seriesKeys = null,
    ) {}

    public function uniqueId(): string
    {
        $series = $this->seriesKeys === null
            ? 'all'
            : implode('-', $this->seriesKeys);

        return $this->coinId . ':' . $series;
    }

    public function handle(CoinChartSyncService $sync): void
    {
        $coin = Coin::query()->find($this->coinId);

        if (! $coin) {
            return;
        }

        $sync->syncCoin($coin, $this->seriesKeys);
    }
}
