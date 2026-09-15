<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\DexPair;
use App\Services\MarketData\DexDetailSyncService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncDexPairDetail implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 180;

    public int $uniqueFor = 300;

    public function __construct(public int $pairId) {}

    public function uniqueId(): string
    {
        return (string) $this->pairId;
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [30, 60, 120];
    }

    public function handle(DexDetailSyncService $sync): void
    {
        $pair = DexPair::query()->find($this->pairId);
        if (! $pair instanceof DexPair) {
            return;
        }

        $sync->syncPair($pair);
    }
}
