<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\SyncDexPairs;
use App\Models\Coin;
use App\Models\CoinPlatform;
use App\Models\DexPair;
use App\Models\SyncRun;
use App\Services\MarketData\DexSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class SyncDexPairsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    public function test_sync_dex_pairs_persists_geckoterminal_payload(): void
    {
        Http::fake([
            'api.geckoterminal.com/api/v2/networks/trending_pools*' => Http::response(
                $this->fixture('geckoterminal_trending_pools.json'),
            ),
            'api.geckoterminal.com/api/v2/networks/new_pools*' => Http::response(
                $this->fixture('geckoterminal_new_pools.json'),
            ),
        ]);

        config([
            'marketdata.sync.dex_trending_pages' => 1,
            'marketdata.sync.dex_new_pages' => 1,
            'marketdata.sync.dex_networks' => [],
            'marketdata.sync.dex_detail_prewarm' => 0,
        ]);

        $run = app(DexSyncService::class)->syncPairs();

        $this->assertSame(SyncRun::STATUS_SUCCEEDED, $run->status);
        $this->assertSame('geckoterminal', $run->provider);
        $this->assertSame(3, $run->records_processed);

        $this->assertDatabaseHas('dex_pairs', [
            'provider' => 'geckoterminal',
            'external_id' => 'eth_0xfixturepooladdress00000000000000000001',
            'pair' => 'PEPE/WETH',
            'base_symbol' => 'PEPE',
            'quote_symbol' => 'WETH',
            'dex' => 'Uniswap V3',
            'chain' => 'Ethereum',
            'network_id' => 'eth',
            'base_token_address' => '0xpepebasetokenaddress000000000000000001',
            'is_trending' => 1,
            'audit_status' => 'partial',
        ]);

        $this->assertDatabaseHas('dex_tokens', [
            'network_id' => 'eth',
            'address' => '0xpepebasetokenaddress000000000000000001',
            'symbol' => 'PEPE',
            'coingecko_coin_id' => 'pepe',
        ]);

        $this->assertDatabaseHas('dex_pairs', [
            'external_id' => 'base_0xnewpooladdress000000000000000000001',
            'pair' => 'NEWCOIN/USDC',
            'chain' => 'Base',
            'is_trending' => 0,
        ]);
    }

    public function test_sync_marks_pair_verified_when_base_token_is_on_markets(): void
    {
        $coin = Coin::query()->create([
            'slug' => 'pepe',
            'symbol' => 'PEPE',
            'name' => 'Pepe',
            'rank' => 50,
        ]);

        CoinPlatform::query()->create([
            'coin_id' => $coin->id,
            'platform_id' => 'ethereum',
            'contract_address' => '0xpepebasetokenaddress000000000000000001',
        ]);

        Http::fake([
            'api.geckoterminal.com/api/v2/networks/trending_pools*' => Http::response(
                $this->fixture('geckoterminal_trending_pools.json'),
            ),
            'api.geckoterminal.com/api/v2/networks/new_pools*' => Http::response([
                'data' => [],
                'included' => [],
            ]),
        ]);

        config([
            'marketdata.sync.dex_trending_pages' => 1,
            'marketdata.sync.dex_new_pages' => 0,
            'marketdata.sync.dex_networks' => [],
            'marketdata.sync.dex_detail_prewarm' => 0,
        ]);

        app(DexSyncService::class)->syncPairs();

        $this->assertDatabaseHas('dex_pairs', [
            'external_id' => 'eth_0xfixturepooladdress00000000000000000001',
            'audit_status' => 'verified',
        ]);
    }

    public function test_sync_dex_pairs_job_runs_service(): void
    {
        Http::fake([
            'api.geckoterminal.com/api/v2/networks/trending_pools*' => Http::response(
                $this->fixture('geckoterminal_trending_pools.json'),
            ),
            'api.geckoterminal.com/api/v2/networks/new_pools*' => Http::response(
                $this->fixture('geckoterminal_new_pools.json'),
            ),
        ]);

        config([
            'marketdata.sync.dex_networks' => [],
            'marketdata.sync.dex_detail_prewarm' => 0,
        ]);

        (new SyncDexPairs)->handle(app(DexSyncService::class));

        $this->assertGreaterThan(0, DexPair::query()->count());
    }

    public function test_sync_dex_pairs_marks_failed_on_http_error(): void
    {
        Http::fake([
            'api.geckoterminal.com/*' => Http::response(['error' => 'down'], 500),
        ]);

        config([
            'marketdata.sync.dex_new_pages' => 0,
            'marketdata.sync.dex_networks' => [],
        ]);

        try {
            app(DexSyncService::class)->syncPairs();
            $this->fail('Expected RuntimeException');
        } catch (RuntimeException) {
            // expected
        }

        $this->assertDatabaseHas('sync_runs', [
            'type' => 'dex_pairs',
            'status' => SyncRun::STATUS_FAILED,
        ]);
    }

    public function test_two_pools_with_the_same_pair_name_both_persist(): void
    {
        Http::fake([
            'api.geckoterminal.com/api/v2/networks/trending_pools*' => Http::response([
                'data' => [
                    $this->solanaPool('solana_pool_one'),
                    $this->solanaPool('solana_pool_two'),
                ],
                'included' => [
                    ['id' => 'sol_base', 'type' => 'token', 'attributes' => [
                        'symbol' => 'THERSOL',
                        'address' => 'TherSolBase111111111111111111111111111',
                    ]],
                    ['id' => 'sol_quote', 'type' => 'token', 'attributes' => [
                        'symbol' => 'SOL',
                        'address' => 'So11111111111111111111111111111111111111112',
                    ]],
                    ['id' => 'pumpfun', 'type' => 'dex', 'attributes' => ['name' => 'PumpFun']],
                ],
            ]),
        ]);

        config([
            'marketdata.sync.dex_trending_pages' => 1,
            'marketdata.sync.dex_new_pages' => 0,
            'marketdata.sync.dex_networks' => [],
            'marketdata.sync.dex_detail_prewarm' => 0,
        ]);

        $run = app(DexSyncService::class)->syncPairs();

        $this->assertSame(SyncRun::STATUS_SUCCEEDED, $run->status);
        $this->assertSame(2, $run->records_processed);
        $this->assertSame(2, DexPair::query()->where('pair', 'THERSOL/SOL')->count());
        $this->assertSame(2, DexPair::query()->distinct()->count('slug'));
    }

    public function test_repeated_syncs_keep_the_same_slug_per_pool(): void
    {
        $payload = [
            'data' => [
                $this->solanaPool('solana_pool_one'),
                $this->solanaPool('solana_pool_two'),
            ],
            'included' => [
                ['id' => 'sol_base', 'type' => 'token', 'attributes' => [
                    'symbol' => 'THERSOL',
                    'address' => 'TherSolBase111111111111111111111111111',
                ]],
                ['id' => 'sol_quote', 'type' => 'token', 'attributes' => [
                    'symbol' => 'SOL',
                    'address' => 'So11111111111111111111111111111111111111112',
                ]],
                ['id' => 'pumpfun', 'type' => 'dex', 'attributes' => ['name' => 'PumpFun']],
            ],
        ];

        Http::fake([
            'api.geckoterminal.com/api/v2/networks/trending_pools*' => Http::response($payload),
        ]);

        config([
            'marketdata.sync.dex_trending_pages' => 1,
            'marketdata.sync.dex_new_pages' => 0,
            'marketdata.sync.dex_networks' => [],
            'marketdata.sync.dex_detail_prewarm' => 0,
        ]);

        app(DexSyncService::class)->syncPairs();
        $first = DexPair::query()->orderBy('external_id')->pluck('slug', 'external_id')->all();

        app(DexSyncService::class)->syncPairs();
        $second = DexPair::query()->orderBy('external_id')->pluck('slug', 'external_id')->all();

        $this->assertSame($first, $second);
        $this->assertSame(2, DexPair::query()->count());
    }

    public function test_provider_skips_non_array_pool_rows(): void
    {
        Http::fake([
            'api.geckoterminal.com/api/v2/networks/trending_pools*' => Http::response([
                'data' => [false, 'bad', null],
                'included' => [],
            ]),
            'api.geckoterminal.com/api/v2/networks/new_pools*' => Http::response([
                'data' => [],
                'included' => [],
            ]),
        ]);

        config([
            'marketdata.sync.dex_networks' => [],
            'marketdata.sync.dex_detail_prewarm' => 0,
        ]);

        $run = app(DexSyncService::class)->syncPairs();

        $this->assertSame(SyncRun::STATUS_SUCCEEDED, $run->status);
        $this->assertSame(0, $run->records_processed);
    }

    public function test_extreme_percent_changes_are_clamped_to_the_column_range(): void
    {
        Http::fake([
            'api.geckoterminal.com/api/v2/networks/trending_pools*' => Http::response([
                'data' => [
                    $this->solanaPool('solana_pool_pump', '3744620948379.6'),
                    $this->solanaPool('solana_pool_dump', '-3744620948379.6'),
                ],
                'included' => [
                    ['id' => 'sol_base', 'type' => 'token', 'attributes' => [
                        'symbol' => 'THERSOL',
                        'address' => 'TherSolBase111111111111111111111111111',
                    ]],
                    ['id' => 'sol_quote', 'type' => 'token', 'attributes' => [
                        'symbol' => 'SOL',
                        'address' => 'So11111111111111111111111111111111111111112',
                    ]],
                    ['id' => 'pumpfun', 'type' => 'dex', 'attributes' => ['name' => 'PumpFun']],
                ],
            ]),
            'api.geckoterminal.com/api/v2/networks/new_pools*' => Http::response([
                'data' => [],
                'included' => [],
            ]),
        ]);

        config([
            'marketdata.sync.dex_trending_pages' => 1,
            'marketdata.sync.dex_new_pages' => 0,
            'marketdata.sync.dex_networks' => [],
            'marketdata.sync.dex_detail_prewarm' => 0,
        ]);

        $run = app(DexSyncService::class)->syncPairs();

        $this->assertSame(SyncRun::STATUS_SUCCEEDED, $run->status);
        $this->assertSame(2, $run->records_processed);

        $this->assertSame(
            '99999999.9999',
            DexPair::query()->where('external_id', 'solana_pool_pump')->value('percent_change_24h'),
        );
        $this->assertSame(
            '-99999999.9999',
            DexPair::query()->where('external_id', 'solana_pool_dump')->value('percent_change_24h'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function solanaPool(string $id, string $percentChange24h = '9.5'): array
    {
        return [
            'id' => $id,
            'type' => 'pool',
            'attributes' => [
                'base_token_price_usd' => '0.002',
                'address' => $id,
                'name' => 'THERSOL / SOL',
                'pool_created_at' => '2026-09-12T06:00:00Z',
                'price_change_percentage' => ['h24' => $percentChange24h],
                'transactions' => ['h24' => ['buys' => 5, 'sells' => 4]],
                'volume_usd' => ['h24' => '80000'],
                'reserve_in_usd' => '30000',
            ],
            'relationships' => [
                'base_token' => ['data' => ['id' => 'sol_base', 'type' => 'token']],
                'quote_token' => ['data' => ['id' => 'sol_quote', 'type' => 'token']],
                'network' => ['data' => ['id' => 'solana', 'type' => 'network']],
                'dex' => ['data' => ['id' => 'pumpfun', 'type' => 'dex']],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function fixture(string $name): array
    {
        $path = base_path('tests/Fixtures/marketdata/' . $name);
        $decoded = json_decode((string) file_get_contents($path), true);

        $this->assertIsArray($decoded);

        return $decoded;
    }
}
