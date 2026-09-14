<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Nostr\NostrFeedSyncService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncNostrFeed implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 180;

    public int $uniqueFor = 600;

    public function __construct(
        public readonly ?string $coinSlug = null,
    ) {}

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [30, 60, 120];
    }

    public function handle(NostrFeedSyncService $sync): void
    {
        $sync->sync($this->coinSlug);
    }

    public function uniqueId(): string
    {
        return 'nostr-feed-' . ($this->coinSlug ?? 'all');
    }
}
