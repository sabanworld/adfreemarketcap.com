<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Coin;
use App\Services\MarketData\MarketSyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncCoinDetail implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $coinId,
    ) {}

    public function handle(MarketSyncService $sync): void
    {
        $coin = Coin::query()->find($this->coinId);

        if (! $coin) {
            return;
        }

        $sync->syncCoinDetail($coin);
    }
}
