<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\DexPair;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DexScanTest extends TestCase
{
    use RefreshDatabase;

    public function test_dexscan_page_renders_pairs(): void
    {
        DexPair::query()->create([
            'slug' => 'weth-usdc-ethereum-uniswap-v3',
            'pair' => 'WETH/USDC',
            'base_symbol' => 'WETH',
            'quote_symbol' => 'USDC',
            'dex' => 'Uniswap v3',
            'chain' => 'Ethereum',
            'audit_status' => 'verified',
            'price' => 3000,
            'percent_change_24h' => 1.5,
            'liquidity_usd' => 10_000_000,
            'volume_24h' => 50_000_000,
            'txns_24h' => 1200,
            'paired_at' => now()->subDays(30),
            'is_trending' => true,
            'synced_at' => now(),
        ]);

        $this->get(route('dexscan'))
            ->assertOk()
            ->assertSee('WETH/USDC')
            ->assertSee('DexScan', false);
    }

    public function test_the_quality_column_shows_the_tier_the_assessor_derived(): void
    {
        $this->pair(['slug' => 'deep', 'pair' => 'DEEP/USDC', 'audit_status' => 'verified', 'liquidity_usd' => 40_000_000, 'paired_at' => now()->subDays(400)]);
        $this->pair(['slug' => 'shady', 'pair' => 'SHADY/WETH', 'audit_status' => 'unverified', 'liquidity_usd' => 30_000, 'paired_at' => now()->subDay()]);

        $response = $this->get(route('dexscan'));

        $response->assertOk();
        $response->assertSee('Quality', false);
        $response->assertSee('afmc-risk afmc-risk--blue-chip', false);
        $response->assertSee('afmc-risk afmc-risk--high-risk', false);
        $response->assertSee('Blue chip', false);
        $response->assertSee('High risk', false);
    }

    public function test_the_quality_footnote_only_claims_inputs_the_assessor_reads(): void
    {
        $response = $this->get(route('dexscan'));

        $response->assertOk();
        $response->assertSee('How the Quality column is set', false);
        $response->assertSee('Pool liquidity, how long the pair has existed, and whether the base token is listed on Markets', false);
        // We do not store how many venues list a token, so the page must not claim we weigh it.
        $response->assertDontSee('venue count', false);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function pair(array $attributes = []): DexPair
    {
        return DexPair::query()->create($attributes + [
            'slug' => 'weth-usdc-ethereum-uniswap-v3',
            'pair' => 'WETH/USDC',
            'base_symbol' => 'WETH',
            'quote_symbol' => 'USDC',
            'dex' => 'Uniswap v3',
            'chain' => 'Ethereum',
            'audit_status' => 'verified',
            'price' => 3000,
            'percent_change_24h' => 1.5,
            'liquidity_usd' => 10_000_000,
            'volume_24h' => 50_000_000,
            'txns_24h' => 1200,
            'paired_at' => now()->subDays(30),
            'is_trending' => true,
            'synced_at' => now(),
        ]);
    }
}
