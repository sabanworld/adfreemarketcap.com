<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Coin;
use App\Services\MarketData\CoinTickerSyncService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncCoinTickers implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 90;

    public int $uniqueFor = 120;

    public function __construct(
        public int $coinId,
    ) {}

    public function uniqueId(): string
    {
        return 'coin-tickers-' . $this->coinId;
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [15, 30, 60];
    }

    public function handle(CoinTickerSyncService $sync): void
    {
        $coin = Coin::query()->find($this->coinId);

        if (! $coin) {
            return;
        }

        $sync->syncCoin($coin);
    }
}
