<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\MarketData\DexChartService;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DexChartServiceTest extends TestCase
{
    public function test_chart_labels_use_clock_times_inside_a_day(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-15 18:00:00', 'UTC'));

        $labels = app(DexChartService::class)->chartLabels([
            [Carbon::parse('2026-09-15 12:00:00', 'UTC')->getTimestampMs(), 0.34],
        ], '1h');

        $this->assertSame(['12:00'], $labels);
    }

    public function test_chart_labels_use_month_and_day_on_the_week_view(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-15 18:00:00', 'UTC'));

        $labels = app(DexChartService::class)->chartLabels([
            [Carbon::parse('2026-09-08 00:00:00', 'UTC')->getTimestampMs(), 0.34],
        ], '7d');

        $this->assertSame(['Sep 8'], $labels);
    }

    public function test_chart_labels_use_month_and_year_on_long_ranges(): void
    {
        $labels = app(DexChartService::class)->chartLabels([
            [Carbon::parse('2025-01-01 00:00:00', 'UTC')->getTimestampMs(), 0.34],
        ], '1y');

        $this->assertSame(['Jan 2025'], $labels);
    }

    public function test_chart_labels_skip_points_whose_timestamp_is_not_a_number(): void
    {
        $labels = app(DexChartService::class)->chartLabels([
            [false, 0.34],
            [Carbon::parse('2026-09-08 00:00:00', 'UTC')->getTimestampMs(), 0.35],
        ], '7d');

        $this->assertSame(['Sep 8'], $labels);
    }
}
