<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\MarketData\MarketStatusCalculator;
use App\Support\Bech32;
use Tests\TestCase;

class MarketStatusCalculatorTest extends TestCase
{
    public function test_afmc10_starts_at_one_hundred_when_base_is_missing(): void
    {
        $calculator = new MarketStatusCalculator;

        $result = $calculator->afmc10([
            [
                'market_cap' => 100.0,
                'percent_change_24h' => 2.0,
                'percent_change_7d' => 4.0,
                'percent_change_30d' => 6.0,
                'percent_change_200d' => 8.0,
                'percent_change_1y' => 10.0,
            ],
            [
                'market_cap' => 100.0,
                'percent_change_24h' => -1.0,
                'percent_change_7d' => -2.0,
                'percent_change_30d' => -3.0,
                'percent_change_200d' => -4.0,
                'percent_change_1y' => -5.0,
            ],
        ], null);

        $this->assertSame(100.0, $result['value']);
        $this->assertSame(200.0, $result['base_sum']);
        $this->assertEqualsWithDelta(0.5, $result['change_24h'], 0.0001);
        $this->assertEqualsWithDelta(1.0, $result['change_7d'], 0.0001);
        $this->assertEqualsWithDelta(1.5, $result['change_30d'], 0.0001);
        $this->assertEqualsWithDelta(2.0, $result['change_200d'], 0.0001);
        $this->assertEqualsWithDelta(2.5, $result['change_1y'], 0.0001);
    }

    public function test_afmc10_scales_against_existing_base(): void
    {
        $calculator = new MarketStatusCalculator;

        $result = $calculator->afmc10([
            ['market_cap' => 150.0, 'percent_change_24h' => null],
            ['market_cap' => 50.0, 'percent_change_24h' => null],
        ], 100.0);

        $this->assertSame(200.0, $result['value']);
        $this->assertNull($result['change_24h']);
    }

    public function test_afmc10_returns_null_when_no_market_caps(): void
    {
        $calculator = new MarketStatusCalculator;

        $result = $calculator->afmc10([
            ['market_cap' => null, 'percent_change_24h' => 1.0],
        ], null);

        $this->assertNull($result['value']);
        $this->assertNull($result['basket_sum']);
    }

    public function test_altcoin_season_counts_alts_beating_bitcoin(): void
    {
        $calculator = new MarketStatusCalculator;

        $result = $calculator->altcoinSeason(10.0, [20.0, 5.0, 30.0, null]);

        $this->assertSame(3, $result['sample_size']);
        $this->assertEqualsWithDelta(66.6667, $result['index'], 0.01);
    }

    public function test_altcoin_season_needs_bitcoin_change(): void
    {
        $calculator = new MarketStatusCalculator;

        $result = $calculator->altcoinSeason(null, [1.0, 2.0]);

        $this->assertNull($result['index']);
        $this->assertSame(0, $result['sample_size']);
    }

    public function test_bech32_decodes_known_npub(): void
    {
        $hex = Bech32::npubToHex('npub1t6el40knsq8hmrpr0m6tt3t0tr4pdeyhlt2qelwhgtwawddqx0xsv03scu');

        $this->assertSame('5eb3fabed3800f7d8c237ef4b5c56f58ea16e497fad40cfdd742ddd735a033cd', $hex);
    }
}
