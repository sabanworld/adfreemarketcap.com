<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\MarketData\CoinChartService;
use App\Services\MarketData\DexChartService;
use Tests\TestCase;

class ChartLabelStyleTest extends TestCase
{
    public function test_dex_chart_label_style_uses_time_inside_a_day(): void
    {
        $this->assertSame('time', app(DexChartService::class)->chartLabelStyle('1h'));
        $this->assertSame('time', app(DexChartService::class)->chartLabelStyle('12h'));
        $this->assertSame('time', app(DexChartService::class)->chartLabelStyle('1d'));
    }

    public function test_dex_chart_label_style_uses_day_on_week_to_quarter_views(): void
    {
        $this->assertSame('day', app(DexChartService::class)->chartLabelStyle('7d'));
        $this->assertSame('day', app(DexChartService::class)->chartLabelStyle('1m'));
        $this->assertSame('day', app(DexChartService::class)->chartLabelStyle('3m'));
    }

    public function test_dex_chart_label_style_uses_month_on_long_ranges(): void
    {
        $this->assertSame('month', app(DexChartService::class)->chartLabelStyle('1y'));
        $this->assertSame('month', app(DexChartService::class)->chartLabelStyle('all'));
    }

    public function test_coin_chart_label_style_matches_dex_bands(): void
    {
        $coins = app(CoinChartService::class);

        $this->assertSame('time', $coins->chartLabelStyle('1h'));
        $this->assertSame('day', $coins->chartLabelStyle('7d'));
        $this->assertSame('month', $coins->chartLabelStyle('1y'));
        $this->assertSame('month', $coins->chartLabelStyle('all'));
    }

    public function test_unknown_range_falls_back_to_default_band(): void
    {
        // normalizeRange defaults to 7d → day.
        $this->assertSame('day', app(DexChartService::class)->chartLabelStyle('not-a-range'));
        $this->assertSame('day', app(CoinChartService::class)->chartLabelStyle('not-a-range'));
    }
}
