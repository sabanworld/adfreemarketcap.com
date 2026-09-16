<?php

declare(strict_types=1);

use App\Jobs\SyncCoinInsights;
use App\Jobs\SyncCoinPlatforms;
use App\Jobs\SyncCurrencyRates;
use App\Jobs\SyncDexPairs;
use App\Jobs\SyncGlobalData;
use App\Jobs\SyncHotCoinCharts;
use App\Jobs\SyncHotCoinTickers;
use App\Jobs\SyncMarketData;
use App\Jobs\SyncMarketStatus;
use App\Jobs\SyncNinetyDayChanges;
use App\Jobs\SyncNostrFeed;
use App\Jobs\SyncStaleCoinDetails;
use App\Jobs\SyncTopCoinTickers;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

$marketsInterval = max(1, min(59, (int) config('marketdata.sync.markets_interval_minutes', 10)));
$dexInterval = max(1, min(59, (int) config('marketdata.sync.dex_interval_minutes', 5)));
$hotTickersInterval = max(1, min(59, (int) config('marketdata.sync.hot_tickers_interval_minutes', 5)));
$hotChartsInterval = max(1, min(59, (int) config('marketdata.sync.hot_charts_interval_minutes', 60)));
$tickersInterval = max(1, min(59, (int) config('marketdata.sync.tickers_interval_minutes', 15)));
$tickersTopCoins = max(0, (int) config('marketdata.sync.tickers_top_coins', 0));
$detailInterval = max(1, min(59, (int) config('marketdata.sync.detail_backfill_interval_minutes', 60)));
$insightsHours = max(1, (int) config('marketdata.sync.insights_interval_hours', 6));
$currencyInterval = max(1, min(59, (int) config('currency.rates_interval_minutes', 30)));
$statusInterval = max(1, min(59, (int) config('marketdata.sync.market_status_interval_minutes', 60)));
$ninetyDayInterval = max(1, min(59, (int) config('marketdata.sync.ninety_day_interval_minutes', 60)));
$platformsHours = max(1, (int) config('marketdata.sync.platforms_interval_hours', 24));
$nostrInterval = max(1, min(59, (int) config('marketdata.sync.nostr_interval_minutes', 30)));

$sentryMonitor = static function (Event $event): Event {
    if (config('sentry.cron_monitoring')) {
        $event->sentryMonitor();
    }

    return $event;
};

$sentryMonitor(
    Schedule::job(new SyncMarketData)
        ->cron("*/{$marketsInterval} * * * *")
        ->withoutOverlapping()
        ->name('marketdata:sync-markets')
);

$sentryMonitor(
    Schedule::job(new SyncGlobalData)
        ->cron("*/{$marketsInterval} * * * *")
        ->withoutOverlapping()
        ->name('marketdata:sync-global')
);

$sentryMonitor(
    Schedule::job(new SyncMarketStatus)
        ->cron("*/{$statusInterval} * * * *")
        ->withoutOverlapping()
        ->name('marketdata:sync-status')
);

// Hourly, but almost every run is a no-op: it only fetches coins whose 90-day change has gone
// stale, so this is a once-a-day pass spread across as many runs as the per-run time budget needs.
// The snapshot beside it can therefore be up to an hour behind a freshly derived figure, which is
// immaterial for a 90-day number.
$sentryMonitor(
    Schedule::job(new SyncNinetyDayChanges)
        ->cron("*/{$ninetyDayInterval} * * * *")
        ->withoutOverlapping()
        ->name('marketdata:sync-ninety-day')
);

$sentryMonitor(
    Schedule::job(new SyncDexPairs)
        ->cron("*/{$dexInterval} * * * *")
        ->withoutOverlapping()
        ->name('marketdata:sync-dex')
);

$sentryMonitor(
    Schedule::job(new SyncHotCoinTickers)
        ->cron("*/{$hotTickersInterval} * * * *")
        ->withoutOverlapping()
        ->name('marketdata:sync-hot-tickers')
);

$sentryMonitor(
    Schedule::job(new SyncHotCoinCharts)
        ->cron("*/{$hotChartsInterval} * * * *")
        ->withoutOverlapping()
        ->name('marketdata:sync-hot-charts')
);

if ($tickersTopCoins > 0) {
    $sentryMonitor(
        Schedule::job(new SyncTopCoinTickers)
            ->cron("*/{$tickersInterval} * * * *")
            ->withoutOverlapping()
            ->name('marketdata:sync-tickers')
    );
}

$sentryMonitor(
    Schedule::job(new SyncStaleCoinDetails)
        ->cron("*/{$detailInterval} * * * *")
        ->withoutOverlapping()
        ->name('marketdata:sync-coin-details')
);

$sentryMonitor(
    Schedule::job(new SyncCurrencyRates)
        ->cron("*/{$currencyInterval} * * * *")
        ->withoutOverlapping()
        ->name('marketdata:sync-currency-rates')
);

$sentryMonitor(
    Schedule::job(new SyncCoinInsights)
        ->cron("15 */{$insightsHours} * * *")
        ->withoutOverlapping()
        ->name('marketdata:sync-insights')
);

$sentryMonitor(
    Schedule::job(new SyncCoinPlatforms)
        ->cron("30 */{$platformsHours} * * *")
        ->withoutOverlapping()
        ->name('marketdata:sync-platforms')
);

$sentryMonitor(
    Schedule::job(new SyncNostrFeed)
        ->cron("*/{$nostrInterval} * * * *")
        ->withoutOverlapping()
        ->name('marketdata:sync-nostr')
);

$sentryMonitor(
    Schedule::command('horizon:snapshot')
        ->everyFiveMinutes()
);
