<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Coin;
use App\Models\CoinProviderId;
use App\Models\SyncRun;
use App\Services\MarketData\MarketSyncService;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Event;
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

    public function test_crypto_apis_replaces_the_rounded_coingecko_percentages(): void
    {
        Http::fake([
            'api.coingecko.com/api/v3/coins/markets*' => Http::response([
                $this->coinGeckoCoin('bitcoin', 'btc', 'Bitcoin', 1),
            ]),
            'rest.cryptoapis.io/market-data/metadata/assets*' => Http::response(
                $this->fixture('cryptoapis_assets.json'),
            ),
        ]);

        $this->syncWithCryptoApisKey();

        $coin = Coin::query()->where('slug', 'bitcoin')->sole();

        $this->assertSame('0.0796', $coin->percent_change_1h);
        $this->assertSame('-3.1710', $coin->percent_change_7d);
        // 24h is precise enough on CoinGecko, so it stays where the ranking comes from.
        $this->assertSame('2.2000', $coin->percent_change_24h);
    }

    public function test_a_crypto_apis_outage_keeps_the_coingecko_percentages(): void
    {
        Http::fake([
            'api.coingecko.com/api/v3/coins/markets*' => Http::response([
                $this->coinGeckoCoin('bitcoin', 'btc', 'Bitcoin', 1),
            ]),
            'rest.cryptoapis.io/*' => Http::response(['error' => 'down'], 500),
        ]);

        $run = $this->syncWithCryptoApisKey();

        $coin = Coin::query()->where('slug', 'bitcoin')->sole();

        $this->assertSame(SyncRun::STATUS_SUCCEEDED, $run->status);
        $this->assertSame('0.1000', $coin->percent_change_1h);
        $this->assertSame('-1.1000', $coin->percent_change_7d);
    }

    public function test_a_ticker_two_coins_share_keeps_the_coingecko_percentages(): void
    {
        Http::fake([
            'api.coingecko.com/api/v3/coins/markets*' => Http::response([
                $this->coinGeckoCoin('bitcoin', 'btc', 'Bitcoin', 1),
                $this->coinGeckoCoin('bitcoin-clone', 'btc', 'Bitcoin Clone', 2),
                // Crypto APIs lists this ticker twice, so it cannot resolve either.
                $this->coinGeckoCoin('ethereum', 'eth', 'Ethereum', 3),
            ]),
            'rest.cryptoapis.io/market-data/metadata/assets*' => Http::response(
                $this->fixture('cryptoapis_assets.json'),
            ),
        ]);

        $this->syncWithCryptoApisKey();

        foreach (['bitcoin', 'bitcoin-clone', 'ethereum'] as $slug) {
            $coin = Coin::query()->where('slug', $slug)->sole();

            $this->assertSame('0.1000', $coin->percent_change_1h, "Expected [{$slug}] to keep the CoinGecko value.");
            $this->assertSame('-1.1000', $coin->percent_change_7d, "Expected [{$slug}] to keep the CoinGecko value.");
        }
    }

    public function test_crypto_apis_is_not_called_without_an_api_key(): void
    {
        Http::fake([
            'api.coingecko.com/api/v3/coins/markets*' => Http::response([
                $this->coinGeckoCoin('bitcoin', 'btc', 'Bitcoin', 1),
            ]),
        ]);

        config([
            'marketdata.primary' => 'coingecko',
            'marketdata.failover' => 'coinpaprika',
            'marketdata.sync.markets_pages' => 1,
            'marketdata.sync.per_page' => 100,
            'marketdata.cryptoapis.api_key' => '',
        ]);

        app(MarketSyncService::class)->syncMarkets();

        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'cryptoapis.io'));
        $this->assertSame('0.1000', Coin::query()->where('slug', 'bitcoin')->sole()->percent_change_1h);
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

    public function test_sync_markets_select_queries_stay_flat_across_many_coins(): void
    {
        $payload = [];

        for ($i = 1; $i <= 25; $i++) {
            $externalId = "coin-{$i}";
            $coin = Coin::query()->create([
                'slug' => $externalId,
                'symbol' => 'C' . $i,
                'name' => "Coin {$i}",
                'rank' => $i,
                'price' => 1.0,
            ]);

            CoinProviderId::query()->create([
                'coin_id' => $coin->id,
                'provider' => 'coingecko',
                'external_id' => $externalId,
            ]);

            $payload[] = $this->coinGeckoCoin($externalId, 'c' . $i, "Coin {$i}", $i);
        }

        Http::fake([
            'api.coingecko.com/api/v3/coins/markets*' => Http::response($payload),
        ]);

        config([
            'marketdata.primary' => 'coingecko',
            'marketdata.failover' => 'coinpaprika',
            'marketdata.sync.markets_pages' => 1,
            'marketdata.sync.per_page' => 100,
            'marketdata.cryptoapis.api_key' => null,
        ]);

        $selects = 0;
        Event::listen(QueryExecuted::class, function (QueryExecuted $query) use (&$selects): void {
            if (str_starts_with(strtolower($query->sql), 'select')) {
                $selects++;
            }
        });

        app(MarketSyncService::class)->syncMarkets();

        // The previous path issued several SELECTs per coin. A batched upsert keeps
        // lookups to a handful of whereIn queries regardless of page size.
        $this->assertLessThan(20, $selects);
    }

    private function syncWithCryptoApisKey(): SyncRun
    {
        config([
            'marketdata.primary' => 'coingecko',
            'marketdata.failover' => 'coinpaprika',
            'marketdata.sync.markets_pages' => 1,
            'marketdata.sync.per_page' => 100,
            'marketdata.cryptoapis.api_key' => 'testing-crypto-apis-key',
        ]);

        return app(MarketSyncService::class)->syncMarkets();
    }

    /**
     * A CoinGecko markets row, with the 1h and 7d percentages rounded to 0.1 the
     * way that endpoint reports them.
     *
     * @return array<string, mixed>
     */
    private function coinGeckoCoin(string $id, string $symbol, string $name, int $rank): array
    {
        return [
            'id' => $id,
            'symbol' => $symbol,
            'name' => $name,
            'current_price' => 50000.12,
            'market_cap' => 1_000_000_000,
            'market_cap_rank' => $rank,
            'total_volume' => 25_000_000,
            'circulating_supply' => 19_000_000,
            'price_change_percentage_1h_in_currency' => 0.1,
            'price_change_percentage_24h_in_currency' => 2.2,
            'price_change_percentage_7d_in_currency' => -1.1,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function fixture(string $name): array
    {
        $decoded = json_decode((string) file_get_contents(base_path('tests/Fixtures/marketdata/' . $name)), true);
        $this->assertIsArray($decoded);

        return $decoded;
    }
}
