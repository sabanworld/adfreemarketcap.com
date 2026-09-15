<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\SyncDexPairDetail;
use App\Jobs\SyncDexTokenDetail;
use App\Models\DexChartSeries;
use App\Models\DexPair;
use App\Models\DexToken;
use App\Models\DexTrade;
use App\Services\MarketData\DexChartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DexScanDetailTest extends TestCase
{
    use RefreshDatabase;

    public function test_dexscan_list_links_to_pair_detail(): void
    {
        $pair = $this->pair();

        $this->get(route('dexscan'))
            ->assertOk()
            ->assertSee(route('dexscan.pair', $pair), false);
    }

    public function test_pair_detail_page_renders_hero_and_dispatches_sync(): void
    {
        Queue::fake();

        $pair = $this->pair();

        $this->get(route('dexscan.pair', $pair))
            ->assertOk()
            ->assertSee('PEPE/WETH', false)
            ->assertSee('Trades', false)
            ->assertSee('Liquidity', false)
            ->assertSee('Holders', false);

        Queue::assertPushed(SyncDexPairDetail::class);
    }

    public function test_pair_detail_shows_trades_when_present(): void
    {
        Queue::fake();

        $pair = $this->pair([
            'detail_synced_at' => now(),
            'trades_synced_at' => now(),
        ]);

        DexTrade::query()->create([
            'dex_pair_id' => $pair->id,
            'kind' => 'buy',
            'price_usd' => 1.2,
            'volume_usd' => 500,
            'trader_address' => '0xtrader000000000000000000000000000001',
            'traded_at' => now()->subMinute(),
        ]);

        $this->get(route('dexscan.pair', $pair))
            ->assertOk()
            ->assertSee('Buy', false);
    }

    public function test_seed_pair_detail_shows_chart_without_dispatching_sync(): void
    {
        Queue::fake();

        $pair = $this->pair([
            'provider' => 'seed',
            'external_id' => 'seed_pepe-weth',
            'detail_synced_at' => now(),
            'trades_synced_at' => now(),
        ]);

        app(DexChartService::class)->upsertSeriesFor($pair, [
            DexChartSeries::SERIES_SHORT => [
                [now()->subDay()->getTimestampMs(), 1.1],
                [now()->getTimestampMs(), 1.2],
            ],
        ]);

        $this->get(route('dexscan.pair', $pair))
            ->assertOk()
            ->assertSee('id="dex-chart"', false)
            ->assertDontSee('Chart data will appear after the next sync.', false);

        Queue::assertNotPushed(SyncDexPairDetail::class);
    }

    public function test_token_detail_page_renders_and_dispatches_sync(): void
    {
        Queue::fake();

        $token = $this->token();

        $this->get(route('dexscan.token', [
            'network' => $token->network_id,
            'address' => $token->address,
        ]))
            ->assertOk()
            ->assertSee('Pepe', false)
            ->assertSee('PEPE', false)
            ->assertSee('Holders', false);

        Queue::assertPushed(SyncDexTokenDetail::class);
    }

    public function test_token_route_is_case_insensitive_on_address(): void
    {
        Queue::fake();

        $token = $this->token();

        $this->get(route('dexscan.token', [
            'network' => $token->network_id,
            'address' => strtoupper($token->address),
        ]))->assertOk();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function pair(array $attributes = []): DexPair
    {
        $token = $this->token();

        return DexPair::query()->create($attributes + [
            'slug' => 'pepe-weth-ethereum-uniswap-v3',
            'provider' => 'geckoterminal',
            'external_id' => 'eth_0xfixturepooladdress00000000000000000001',
            'pair' => 'PEPE/WETH',
            'base_symbol' => 'PEPE',
            'quote_symbol' => 'WETH',
            'dex' => 'Uniswap V3',
            'chain' => 'Ethereum',
            'network_id' => 'eth',
            'contract_address' => '0xfixturepooladdress00000000000000000001',
            'base_token_address' => $token->address,
            'dex_token_id' => $token->id,
            'audit_status' => 'partial',
            'price' => 1.23,
            'percent_change_24h' => 12.5,
            'liquidity_usd' => 2_500_000,
            'volume_24h' => 1_500_000,
            'txns_24h' => 180,
            'paired_at' => now()->subDays(30),
            'is_trending' => true,
            'synced_at' => now(),
        ]);
    }

    private function token(): DexToken
    {
        return DexToken::query()->firstOrCreate(
            [
                'network_id' => 'eth',
                'address' => '0xpepebasetokenaddress000000000000000001',
            ],
            [
                'symbol' => 'PEPE',
                'name' => 'Pepe',
                'coingecko_coin_id' => 'pepe',
                'price' => 1.23,
                'synced_at' => now(),
            ],
        );
    }
}
