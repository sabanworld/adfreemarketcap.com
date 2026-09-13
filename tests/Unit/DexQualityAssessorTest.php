<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\DexPair;
use App\Services\MarketData\DexQualityAssessor;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * The assessor reads cast attributes off an unsaved DexPair, and Eloquent casts need the
 * framework booted, so this extends the application test case rather than bare PHPUnit.
 */
class DexQualityAssessorTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_a_deep_pool_with_years_of_history_and_a_verified_contract_is_blue_chip(): void
    {
        $this->assertSame(
            DexQualityAssessor::TIER_BLUE_CHIP,
            $this->tierFor(liquidity: 40_000_000, ageDays: 400, audit: 'verified'),
        );
    }

    public function test_a_deep_pool_with_months_of_history_is_established(): void
    {
        $this->assertSame(
            DexQualityAssessor::TIER_ESTABLISHED,
            $this->tierFor(liquidity: 5_000_000, ageDays: 120, audit: 'verified'),
        );
    }

    public function test_a_deep_pool_that_is_only_weeks_old_cannot_reach_established(): void
    {
        $this->assertSame(
            DexQualityAssessor::TIER_SPECULATIVE,
            $this->tierFor(liquidity: 40_000_000, ageDays: 20, audit: 'verified'),
        );
    }

    public function test_a_thin_pool_with_long_history_is_speculative(): void
    {
        $this->assertSame(
            DexQualityAssessor::TIER_SPECULATIVE,
            $this->tierFor(liquidity: 300_000, ageDays: 800, audit: 'verified'),
        );
    }

    public function test_an_unverified_contract_is_high_risk_however_deep_the_pool(): void
    {
        $this->assertSame(
            DexQualityAssessor::TIER_HIGH_RISK,
            $this->tierFor(liquidity: 90_000_000, ageDays: 900, audit: 'unverified'),
        );
    }

    public function test_a_partial_audit_can_still_reach_speculative_but_no_higher(): void
    {
        $this->assertSame(
            DexQualityAssessor::TIER_SPECULATIVE,
            $this->tierFor(liquidity: 90_000_000, ageDays: 900, audit: 'partial'),
        );
    }

    public function test_a_pool_under_the_floor_is_high_risk(): void
    {
        $this->assertSame(
            DexQualityAssessor::TIER_HIGH_RISK,
            $this->tierFor(liquidity: 4_000, ageDays: 500, audit: 'verified'),
        );
    }

    public function test_a_pair_launched_days_ago_is_high_risk(): void
    {
        $this->assertSame(
            DexQualityAssessor::TIER_HIGH_RISK,
            $this->tierFor(liquidity: 5_000_000, ageDays: 2, audit: 'verified'),
        );
    }

    public function test_missing_liquidity_and_pairing_date_are_treated_as_the_worst_case(): void
    {
        $pair = new DexPair(['audit_status' => 'verified']);

        $this->assertSame(DexQualityAssessor::TIER_HIGH_RISK, (new DexQualityAssessor)->tier($pair));
    }

    public function test_audit_status_is_matched_regardless_of_casing(): void
    {
        $this->assertSame(
            DexQualityAssessor::TIER_BLUE_CHIP,
            $this->tierFor(liquidity: 40_000_000, ageDays: 400, audit: 'VERIFIED'),
        );
    }

    public function test_describe_returns_the_tier_with_its_label_dots_and_reason(): void
    {
        Carbon::setTestNow('2026-09-13 12:00:00');

        $described = (new DexQualityAssessor)->describe(new DexPair([
            'audit_status' => 'unverified',
            'liquidity_usd' => 1_000,
            'paired_at' => Carbon::now()->subDay(),
        ]));

        $this->assertSame(DexQualityAssessor::TIER_HIGH_RISK, $described['tier']);
        $this->assertSame('High risk', $described['label']);
        $this->assertSame(4, $described['dots']);
        $this->assertStringContainsString('lose everything', $described['why']);
    }

    public function test_every_tier_declares_a_label_a_dot_count_and_a_reason(): void
    {
        foreach (DexQualityAssessor::TIERS as $tier => $meta) {
            $this->assertArrayHasKey('label', $meta, $tier);
            $this->assertArrayHasKey('dots', $meta, $tier);
            $this->assertArrayHasKey('why', $meta, $tier);
            $this->assertGreaterThanOrEqual(1, $meta['dots'], $tier);
            $this->assertLessThanOrEqual(4, $meta['dots'], $tier);
        }
    }

    private function tierFor(float $liquidity, int $ageDays, string $audit): string
    {
        Carbon::setTestNow('2026-09-13 12:00:00');

        $pair = new DexPair([
            'audit_status' => $audit,
            'liquidity_usd' => $liquidity,
            'paired_at' => Carbon::now()->subDays($ageDays),
        ]);

        return (new DexQualityAssessor)->tier($pair);
    }
}
