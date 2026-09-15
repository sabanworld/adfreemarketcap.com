<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\DexToken;
use App\Services\MarketData\DexDetailSyncService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncDexTokenDetail implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 180;

    public int $uniqueFor = 300;

    public function __construct(public int $tokenId) {}

    public function uniqueId(): string
    {
        return (string) $this->tokenId;
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
        $token = DexToken::query()->find($this->tokenId);
        if (! $token instanceof DexToken) {
            return;
        }

        $sync->syncToken($token);
    }
}
