<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ScheduleTest extends TestCase
{
    public function test_market_sync_and_horizon_snapshot_are_scheduled(): void
    {
        Artisan::call('schedule:list');

        $output = Artisan::output();

        $this->assertStringContainsString('marketdata:sync-markets', $output);
        $this->assertStringContainsString('marketdata:sync-global', $output);
        $this->assertStringContainsString('marketdata:sync-dex', $output);
        $this->assertStringContainsString('marketdata:sync-tickers', $output);
        $this->assertStringContainsString('marketdata:sync-coin-details', $output);
        $this->assertStringContainsString('marketdata:sync-insights', $output);
        $this->assertStringContainsString('marketdata:sync-currency-rates', $output);
        $this->assertStringContainsString('horizon:snapshot', $output);
    }
}
