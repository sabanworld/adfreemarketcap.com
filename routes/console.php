<?php

declare(strict_types=1);

use App\Jobs\SyncGlobalData;
use App\Jobs\SyncMarketData;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

$marketsInterval = max(1, min(59, (int) config('marketdata.sync.markets_interval_minutes', 5)));

Schedule::job(new SyncMarketData)
    ->cron("*/{$marketsInterval} * * * *")
    ->withoutOverlapping()
    ->onOneServer()
    ->name('marketdata:sync-markets');

Schedule::job(new SyncGlobalData)
    ->cron("*/{$marketsInterval} * * * *")
    ->withoutOverlapping()
    ->onOneServer()
    ->name('marketdata:sync-global');

Schedule::command('horizon:snapshot')
    ->everyFiveMinutes()
    ->onOneServer();
