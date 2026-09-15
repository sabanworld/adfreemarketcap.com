<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\SyncDexPairDetail;
use App\Jobs\SyncDexTokenDetail;
use App\Models\DexPair;
use App\Models\DexToken;
use App\Models\DexTokenHolder;
use App\Models\DexTrade;
use App\Models\SyncRun;
use App\Services\MarketData\DexDetailSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SyncDexDetailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();

        config([
            'marketdata.geckoterminal.base_url' => 'https://api.geckoterminal.com/api/v2',
            'marketdata.geckoterminal.onchain_base_url' => 'https://pro-api.coingecko.com/api/v3/onchain',
            'marketdata.geckoterminal.api_key' => '',
            'marketdata.geckoterminal.retry_times' => 1,
            'marketdata.geckoterminal.retry_sleep_ms' => 0,
        ]);
    }

    public function test_sync_pair_detail_persists_trades_and_charts(): void
    {
        $pair = $this->pair();

        Http::fake(function (Request $request) {
            $url = $request->url();

            if (str_contains($url, '/trades')) {
                return Http::response($this->fixture('geckoterminal_trades.json'));
            }

            if (str_contains($url, '/ohlcv/')) {
                return Http::response($this->fixture('geckoterminal_ohlcv.json'));
            }

            if (str_contains($url, '/pools/')) {
                return Http::response($this->fixture('geckoterminal_pool_detail.json'));
            }

            return Http::response(['error' => 'unexpected ' . $url], 500);
        });

        $run = app(DexDetailSyncService::class)->syncPair($pair->fresh());

        $this->assertSame(SyncRun::STATUS_SUCCEEDED, $run->status);
        $this->assertSame(2, DexTrade::query()->where('dex_pair_id', $pair->id)->count());
        $this->assertSame(3, $pair->fresh()->chartSeries()->count());
        $this->assertNotNull($pair->fresh()->detail_synced_at);
    }

    public function test_sync_token_detail_soft_fails_holders_without_api_key(): void
    {
        config(['marketdata.geckoterminal.api_key' => '']);

        $token = $this->token();
        $this->fakeTokenDetailHttp();

        $run = app(DexDetailSyncService::class)->syncToken($token->fresh());

        $this->assertSame(SyncRun::STATUS_SUCCEEDED, $run->status);
        $this->assertSame(0, DexTokenHolder::query()->count());
        $this->assertNull($token->fresh()->holders_synced_at);
        $this->assertSame(2, DexTrade::query()->where('dex_token_id', $token->id)->count());
    }

    public function test_sync_token_detail_stores_holders_when_pro_responds(): void
    {
        config([
            'marketdata.geckoterminal.api_key' => 'test-pro-key',
            'marketdata.geckoterminal.api_key_header' => 'x-cg-pro-api-key',
            'marketdata.geckoterminal.onchain_base_url' => 'https://pro-api.coingecko.com/api/v3/onchain',
        ]);

        $token = $this->token();
        $this->fakeTokenDetailHttp(holdersStatus: 200);

        $run = app(DexDetailSyncService::class)->syncToken($token->fresh());

        $this->assertSame(SyncRun::STATUS_SUCCEEDED, $run->status);
        $this->assertSame(2, DexTokenHolder::query()->where('dex_token_id', $token->id)->count());
        $this->assertNotNull($token->fresh()->holders_synced_at);
    }

    public function test_sync_token_detail_soft_fails_when_holders_forbidden(): void
    {
        config([
            'marketdata.geckoterminal.api_key' => 'test-pro-key',
            'marketdata.geckoterminal.onchain_base_url' => 'https://pro-api.coingecko.com/api/v3/onchain',
        ]);

        $token = $this->token();
        $this->fakeTokenDetailHttp(holdersStatus: 403);

        $run = app(DexDetailSyncService::class)->syncToken($token->fresh());

        $this->assertSame(SyncRun::STATUS_SUCCEEDED, $run->status);
        $this->assertSame(0, DexTokenHolder::query()->count());
    }

    public function test_pair_detail_job_is_unique_per_pair(): void
    {
        Queue::fake();

        $pair = $this->pair();
        SyncDexPairDetail::dispatch($pair->id);

        Queue::assertPushed(SyncDexPairDetail::class, fn (SyncDexPairDetail $job): bool => $job->pairId === $pair->id);
    }

    public function test_token_detail_job_is_unique_per_token(): void
    {
        Queue::fake();

        $token = $this->token();
        SyncDexTokenDetail::dispatch($token->id);

        Queue::assertPushed(SyncDexTokenDetail::class, fn (SyncDexTokenDetail $job): bool => $job->tokenId === $token->id);
    }

    private function fakeTokenDetailHttp(int $holdersStatus = 200): void
    {
        Http::fake(function (Request $request) use ($holdersStatus) {
            $url = $request->url();

            if (str_contains($url, '/top_holders')) {
                if ($holdersStatus !== 200) {
                    return Http::response(['error' => 'forbidden'], $holdersStatus);
                }

                return Http::response($this->fixture('coingecko_onchain_top_holders.json'));
            }

            if (str_contains($url, '/trades')) {
                return Http::response($this->fixture('geckoterminal_trades.json'));
            }

            if (str_contains($url, '/ohlcv/')) {
                return Http::response($this->fixture('geckoterminal_ohlcv.json'));
            }

            if (str_contains($url, '/pools')) {
                return Http::response(['data' => [], 'included' => []]);
            }

            if (str_contains($url, '/tokens/')) {
                return Http::response($this->fixture('geckoterminal_token_detail.json'));
            }

            return Http::response(['error' => 'unexpected ' . $url], 500);
        });
    }

    private function pair(): DexPair
    {
        $token = $this->token();

        return DexPair::query()->create([
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
