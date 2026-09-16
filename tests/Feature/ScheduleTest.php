<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;
use ReflectionProperty;
use Tests\TestCase;

class ScheduleTest extends TestCase
{
    public function test_market_sync_and_horizon_snapshot_are_scheduled(): void
    {
        Artisan::call('schedule:list');

        $output = Artisan::output();

        $this->assertStringContainsString('marketdata:sync-markets', $output);
        $this->assertStringContainsString('marketdata:sync-global', $output);
        $this->assertStringContainsString('marketdata:sync-status', $output);
        $this->assertStringContainsString('marketdata:sync-ninety-day', $output);
        $this->assertStringContainsString('marketdata:sync-dex', $output);
        if ((int) config('marketdata.sync.tickers_top_coins') > 0) {
            $this->assertStringContainsString('marketdata:sync-tickers', $output);
        }

        $this->assertStringContainsString('marketdata:sync-coin-details', $output);
        $this->assertStringContainsString('marketdata:sync-insights', $output);
        $this->assertStringContainsString('marketdata:sync-platforms', $output);
        $this->assertStringContainsString('marketdata:sync-nostr', $output);
        $this->assertStringContainsString('marketdata:sync-currency-rates', $output);
        $this->assertStringContainsString('horizon:snapshot', $output);
    }

    public function test_sentry_cron_monitoring_is_off_outside_production(): void
    {
        $this->assertFalse(config('sentry.cron_monitoring'));
    }

    public function test_scheduled_events_skip_sentry_monitor_when_cron_monitoring_is_off(): void
    {
        $this->assertFalse(config('sentry.cron_monitoring'));

        $beforeCallbacks = new ReflectionProperty(
            Event::class,
            'beforeCallbacks'
        );

        foreach ($this->app->make(Schedule::class)->events() as $event) {
            $this->assertSame(
                [],
                $beforeCallbacks->getValue($event),
                'Sentry monitor callbacks should not register when cron_monitoring is off: ' . $event->description
            );
        }
    }
}
