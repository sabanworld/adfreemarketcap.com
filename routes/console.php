<?php

declare(strict_types=1);

use App\Jobs\SyncCoinInsights;
use App\Jobs\SyncCurrencyRates;
use App\Jobs\SyncDexPairs;
use App\Jobs\SyncGlobalData;
use App\Jobs\SyncHotCoinTickers;
use App\Jobs\SyncMarketData;
use App\Jobs\SyncStaleCoinDetails;
use App\Jobs\SyncTopCoinTickers;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

$marketsInterval = max(1, min(59, (int) config('marketdata.sync.markets_interval_minutes', 10)));
$dexInterval = max(1, min(59, (int) config('marketdata.sync.dex_interval_minutes', 5)));
$hotTickersInterval = max(1, min(59, (int) config('marketdata.sync.hot_tickers_interval_minutes', 5)));
$tickersInterval = max(1, min(59, (int) config('marketdata.sync.tickers_interval_minutes', 15)));
$tickersTopCoins = max(0, (int) config('marketdata.sync.tickers_top_coins', 0));
$detailInterval = max(1, min(59, (int) config('marketdata.sync.detail_backfill_interval_minutes', 60)));
$insightsHours = max(1, (int) config('marketdata.sync.insights_interval_hours', 6));
$currencyInterval = max(1, min(59, (int) config('currency.rates_interval_minutes', 30)));

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

Schedule::job(new SyncDexPairs)
    ->cron("*/{$dexInterval} * * * *")
    ->withoutOverlapping()
    ->onOneServer()
    ->name('marketdata:sync-dex');

Schedule::job(new SyncHotCoinTickers)
    ->cron("*/{$hotTickersInterval} * * * *")
    ->withoutOverlapping()
    ->onOneServer()
    ->name('marketdata:sync-hot-tickers');

if ($tickersTopCoins > 0) {
    Schedule::job(new SyncTopCoinTickers)
        ->cron("*/{$tickersInterval} * * * *")
        ->withoutOverlapping()
        ->onOneServer()
        ->name('marketdata:sync-tickers');
}

Schedule::job(new SyncStaleCoinDetails)
    ->cron("*/{$detailInterval} * * * *")
    ->withoutOverlapping()
    ->onOneServer()
    ->name('marketdata:sync-coin-details');

Schedule::job(new SyncCurrencyRates)
    ->cron("*/{$currencyInterval} * * * *")
    ->withoutOverlapping()
    ->onOneServer()
    ->name('marketdata:sync-currency-rates');

Schedule::job(new SyncCoinInsights)
    ->cron("15 */{$insightsHours} * * *")
    ->withoutOverlapping()
    ->onOneServer()
    ->name('marketdata:sync-insights');

Schedule::command('horizon:snapshot')
    ->everyFiveMinutes()
    ->onOneServer();
