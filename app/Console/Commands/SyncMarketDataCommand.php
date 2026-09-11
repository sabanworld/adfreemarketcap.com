<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\SyncGlobalData;
use App\Jobs\SyncMarketData;
use App\Services\MarketData\MarketSyncService;
use Illuminate\Console\Command;

class SyncMarketDataCommand extends Command
{
    protected $signature = 'marketdata:sync {--queue : Dispatch jobs to the queue instead of running inline}';

    protected $description = 'Sync market rankings and global stats from configured providers';

    public function handle(MarketSyncService $sync): int
    {
        if ($this->option('queue')) {
            SyncMarketData::dispatch();
            SyncGlobalData::dispatch();
            $this->info('Market data sync jobs dispatched.');

            return self::SUCCESS;
        }

        $markets = $sync->syncMarkets();
        $global = $sync->syncGlobal();

        $this->info("Markets: {$markets->status} ({$markets->records_processed}) via {$markets->provider}");
        $this->info("Global: {$global->status} via {$global->provider}");

        return self::SUCCESS;
    }
}
