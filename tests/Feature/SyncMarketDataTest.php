<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Coin;
use App\Models\SyncRun;
use App\Services\MarketData\MarketSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SyncMarketDataTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    public function test_sync_markets_persists_coingecko_payload(): void
    {
        Http::fake([
            'api.coingecko.com/api/v3/coins/markets*' => Http::response([
                [
                    'id' => 'bitcoin',
                    'symbol' => 'btc',
                    'name' => 'Bitcoin',
                    'image' => 'https://example.test/btc.png',
                    'current_price' => 50000.12,
                    'market_cap' => 1_000_000_000,
                    'market_cap_rank' => 1,
                    'total_volume' => 25_000_000,
                    'circulating_supply' => 19_000_000,
                    'price_change_percentage_1h_in_currency' => 0.1,
                    'price_change_percentage_24h_in_currency' => 2.2,
                    'price_change_percentage_7d_in_currency' => -1.1,
                    'sparkline_in_7d' => ['price' => [1, 2, 3]],
                ],
            ]),
            'api.coingecko.com/api/v3/global' => Http::response([
                'data' => [
                    'total_market_cap' => ['usd' => 2_000_000_000_000],
                    'total_volume' => ['usd' => 90_000_000_000],
                    'market_cap_percentage' => ['btc' => 52.5],
                    'active_cryptocurrencies' => 10000,
                ],
            ]),
        ]);

        config([
            'marketdata.primary' => 'coingecko',
            'marketdata.failover' => 'coinpaprika',
            'marketdata.sync.markets_pages' => 1,
            'marketdata.sync.per_page' => 100,
        ]);

        $sync = app(MarketSyncService::class);
        $markets = $sync->syncMarkets();
        $global = $sync->syncGlobal();

        $this->assertSame(SyncRun::STATUS_SUCCEEDED, $markets->status);
        $this->assertSame('coingecko', $markets->provider);
        $this->assertDatabaseHas('coins', [
            'slug' => 'bitcoin',
            'symbol' => 'BTC',
            'name' => 'Bitcoin',
            'rank' => 1,
        ]);
        $this->assertSame(SyncRun::STATUS_SUCCEEDED, $global->status);
        $this->assertDatabaseHas('market_globals', [
            'provider' => 'coingecko',
        ]);
    }

    public function test_failover_to_coinpaprika_when_coingecko_fails(): void
    {
        Http::fake([
            'api.coingecko.com/*' => Http::response(['error' => 'down'], 500),
            'api.coinpaprika.com/v1/tickers' => Http::response([
                [
                    'id' => 'btc-bitcoin',
                    'name' => 'Bitcoin',
                    'symbol' => 'BTC',
                    'rank' => 1,
                    'circulating_supply' => 19_000_000,
                    'quotes' => [
                        'USD' => [
                            'price' => 51000,
                            'percent_change_1h' => 0.2,
                            'percent_change_24h' => 1.1,
                            'percent_change_7d' => 3.3,
                            'market_cap' => 1_100_000_000,
                            'volume_24h' => 30_000_000,
                        ],
                    ],
                ],
            ]),
        ]);

        config([
            'marketdata.primary' => 'coingecko',
            'marketdata.failover' => 'coinpaprika',
            'marketdata.sync.markets_pages' => 1,
            'marketdata.sync.per_page' => 100,
        ]);

        $run = app(MarketSyncService::class)->syncMarkets();

        $this->assertSame('coinpaprika', $run->provider);
        $this->assertDatabaseHas('coins', [
            'symbol' => 'BTC',
            'last_provider' => 'coinpaprika',
        ]);
        $this->assertTrue(Coin::query()->where('symbol', 'BTC')->exists());
    }
}
