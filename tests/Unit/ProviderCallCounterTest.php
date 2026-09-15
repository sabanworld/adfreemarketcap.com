<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\MarketData\ProviderCallCounter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ProviderCallCounterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-09-15 08:40:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_it_rolls_hour_day_and_month_from_hourly_grains(): void
    {
        $counter = app(ProviderCallCounter::class);

        $counter->record(ProviderCallCounter::COINGECKO);
        $counter->record(ProviderCallCounter::COINGECKO);
        $counter->record(ProviderCallCounter::GECKOTERMINAL);

        Carbon::setTestNow(Carbon::parse('2026-09-15 09:05:00'));
        $counter->record(ProviderCallCounter::COINGECKO);

        $rows = $counter->summaries()->keyBy('provider');

        $this->assertSame(1, $rows[ProviderCallCounter::COINGECKO]->hour);
        $this->assertSame(3, $rows[ProviderCallCounter::COINGECKO]->day);
        $this->assertSame(3, $rows[ProviderCallCounter::COINGECKO]->month);
        $this->assertSame(0, $rows[ProviderCallCounter::GECKOTERMINAL]->hour);
        $this->assertSame(1, $rows[ProviderCallCounter::GECKOTERMINAL]->day);

        $quota = $counter->combined(ProviderCallCounter::COINGECKO_QUOTA);
        $this->assertSame(1, $quota->hour);
        $this->assertSame(4, $quota->day);
        $this->assertSame(4, $quota->month);
    }

    public function test_a_new_month_does_not_keep_last_month_in_the_month_total(): void
    {
        $counter = app(ProviderCallCounter::class);
        $counter->record(ProviderCallCounter::COINGECKO);

        Carbon::setTestNow(Carbon::parse('2026-10-01 00:15:00'));
        $counter->record(ProviderCallCounter::COINGECKO);

        $row = $counter->summaries()->first();

        $this->assertSame(1, $row?->hour);
        $this->assertSame(1, $row?->day);
        $this->assertSame(1, $row?->month);
    }
}
