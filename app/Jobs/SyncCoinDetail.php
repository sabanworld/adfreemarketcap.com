<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Coin;
use App\Services\MarketData\MarketSyncService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncCoinDetail implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 90;

    public int $uniqueFor = 300;

    public function __construct(
        public int $coinId,
    ) {}

    public function uniqueId(): string
    {
        return (string) $this->coinId;
    }

    public function handle(MarketSyncService $sync): void
    {
        $coin = Coin::query()->find($this->coinId);

        if (! $coin) {
            return;
        }

        $sync->syncCoinDetail($coin);
    }
}
