<?php

declare(strict_types=1);

namespace App\Services\MarketData;

use App\Models\DexPair;
use Illuminate\Support\Carbon;

/**
 * Turns what we store about a DEX pair into the Quality tier shown on DexScan.
 *
 * Three inputs, all of them facts from stored market data: pool liquidity, how long the pair
 * has existed, and whether the base token is listed on Markets (audit_status). Nothing here
 * looks at price performance, and there is no field a project can pay us to change. The tiers
 * are deliberately pessimistic, because an on-chain pair with a thin pool is a real way to
 * lose everything.
 */
final class DexQualityAssessor
{
    public const TIER_BLUE_CHIP = 'blue-chip';

    public const TIER_ESTABLISHED = 'established';

    public const TIER_SPECULATIVE = 'speculative';

    public const TIER_HIGH_RISK = 'high-risk';

    /**
     * @var array<string, array{label: string, dots: int, why: string}>
     */
    public const TIERS = [
        self::TIER_BLUE_CHIP => [
            'label' => 'Blue chip',
            'dots' => 1,
            'why' => 'Deep pool, more than a year of trading, base token listed on Markets.',
        ],
        self::TIER_ESTABLISHED => [
            'label' => 'Established',
            'dots' => 2,
            'why' => 'Months of trading on a pool deep enough to absorb an ordinary order.',
        ],
        self::TIER_SPECULATIVE => [
            'label' => 'Speculative',
            'dots' => 3,
            'why' => 'Thin pool or short history. Price can move several percent on one order.',
        ],
        self::TIER_HIGH_RISK => [
            'label' => 'High risk',
            'dots' => 4,
            'why' => 'Very thin pool, days old, or a base token we do not list on Markets. Assume you can lose everything.',
        ],
    ];

    /**
     * Liquidity floors, in USD, for the two tiers that need a deep pool. Below the
     * speculative floor a pair is high risk whatever else it has going for it.
     */
    private const LIQUIDITY_BLUE_CHIP = 25_000_000.0;

    private const LIQUIDITY_ESTABLISHED = 2_000_000.0;

    private const LIQUIDITY_SPECULATIVE = 100_000.0;

    /** Age floors, in days. A deep pool that appeared this week has not been tested yet. */
    private const AGE_BLUE_CHIP_DAYS = 365;

    private const AGE_ESTABLISHED_DAYS = 90;

    private const AGE_SPECULATIVE_DAYS = 7;

    public function tier(DexPair $pair): string
    {
        $liquidity = $pair->liquidity_usd !== null ? (float) $pair->liquidity_usd : 0.0;
        $ageDays = $this->ageInDays($pair);
        $verified = $this->isVerified($pair);
        $partial = strtolower((string) $pair->audit_status) === 'partial';

        // An unknown Markets listing caps the pair at high risk: we treat tokens that are not
        // on our rankings (and lack even a CoinGecko id) as unverified.
        if (! $verified && ! $partial) {
            return self::TIER_HIGH_RISK;
        }

        if ($liquidity < self::LIQUIDITY_SPECULATIVE || $ageDays < self::AGE_SPECULATIVE_DAYS) {
            return self::TIER_HIGH_RISK;
        }

        if ($verified && $liquidity >= self::LIQUIDITY_BLUE_CHIP && $ageDays >= self::AGE_BLUE_CHIP_DAYS) {
            return self::TIER_BLUE_CHIP;
        }

        if ($verified && $liquidity >= self::LIQUIDITY_ESTABLISHED && $ageDays >= self::AGE_ESTABLISHED_DAYS) {
            return self::TIER_ESTABLISHED;
        }

        return self::TIER_SPECULATIVE;
    }

    /**
     * @return array{tier: string, label: string, dots: int, why: string}
     */
    public function describe(DexPair $pair): array
    {
        $tier = $this->tier($pair);

        return ['tier' => $tier] + self::TIERS[$tier];
    }

    private function isVerified(DexPair $pair): bool
    {
        return strtolower((string) $pair->audit_status) === 'verified';
    }

    /**
     * An unknown pairing date is treated as brand new rather than as old, so a missing field
     * can never promote a pair into a calmer tier than it has earned.
     */
    private function ageInDays(DexPair $pair): float
    {
        if (! $pair->paired_at instanceof Carbon) {
            return 0.0;
        }

        return (float) $pair->paired_at->diffInDays(Carbon::now(), true);
    }
}
