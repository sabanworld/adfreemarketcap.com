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
}
