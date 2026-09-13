<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\CoinShow;
use App\Models\Coin;
use App\Models\CoinProviderId;
use App\Models\SyncRun;
use App\Services\MarketData\CoinTickerSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class CoinTickersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    public function test_sync_coin_tickers_persists_exchange_markets(): void
    {
        $coin = Coin::query()->create([
            'slug' => 'bitcoin',
            'symbol' => 'BTC',
            'name' => 'Bitcoin',
            'rank' => 1,
            'price' => 77000,
            'detail_synced_at' => now(),
        ]);

        CoinProviderId::query()->create([
            'coin_id' => $coin->id,
            'provider' => 'coingecko',
            'external_id' => 'bitcoin',
        ]);

        Http::fake([
            'api.coingecko.com/api/v3/coins/bitcoin/tickers*' => Http::response(
                $this->fixture('coingecko_bitcoin_tickers.json'),
            ),
        ]);

        config(['marketdata.sync.tickers_pages' => 1]);

        $run = app(CoinTickerSyncService::class)->syncCoin($coin);

        $this->assertSame(SyncRun::STATUS_SUCCEEDED, $run->status);
        $this->assertSame(2, $run->records_processed);
        $this->assertDatabaseHas('coin_tickers', [
            'coin_id' => $coin->id,
            'exchange_id' => 'binance',
            'pair' => 'BTC/USDT',
            'rank' => 1,
        ]);
        $this->assertNotNull($coin->fresh()->tickers_synced_at);
    }

    public function test_sync_coin_tickers_falls_back_to_coin_id_for_on_chain_symbols(): void
    {
        $coin = Coin::query()->create([
            'slug' => 'xrp',
            'symbol' => 'XRP',
            'name' => 'XRP',
            'rank' => 6,
            'price' => 1.37,
            'detail_synced_at' => now(),
        ]);

        CoinProviderId::query()->create([
            'coin_id' => $coin->id,
            'provider' => 'coingecko',
            'external_id' => 'ripple',
        ]);

        Http::fake([
            'api.coingecko.com/api/v3/coins/ripple/tickers*' => Http::response(
                $this->fixture('coingecko_ripple_tickers.json'),
            ),
        ]);

        config(['marketdata.sync.tickers_pages' => 1]);

        $run = app(CoinTickerSyncService::class)->syncCoin($coin);

        $this->assertSame(SyncRun::STATUS_SUCCEEDED, $run->status);
        $this->assertDatabaseHas('coin_tickers', [
            'coin_id' => $coin->id,
            'exchange_id' => 'first-ledger',
            'base_symbol' => 'RIPPLE-USD',
            'target_symbol' => 'XRP',
            'pair' => 'RIPPLE-USD/XRP',
        ]);
    }

    public function test_coin_detail_shows_markets_table(): void
    {
        $coin = Coin::query()->create([
            'slug' => 'ethereum',
            'symbol' => 'ETH',
            'name' => 'Ethereum',
            'rank' => 2,
            'price' => 3000,
            'detail_synced_at' => now(),
            'tickers_synced_at' => now(),
        ]);

        $coin->tickers()->create([
            'provider' => 'coingecko',
            'exchange_id' => 'binance',
            'exchange_name' => 'Binance',
            'base_symbol' => 'ETH',
            'target_symbol' => 'USDT',
            'pair' => 'ETH/USDT',
            'price_usd' => 3000,
            'volume_24h_usd' => 500_000_000,
            'volume_share_percent' => 42.5,
            'trust_score' => 'green',
            'rank' => 1,
            'synced_at' => now(),
        ]);

        Livewire::test(CoinShow::class, ['coin' => $coin->fresh()])
            ->assertSee('Ethereum Markets')
            ->assertSee('Binance')
            ->assertSee('ETH/USDT')
            ->assertOk();
    }

    public function test_sync_coin_tickers_removes_coin_when_provider_returns_404(): void
    {
        $coin = Coin::query()->create([
            'slug' => 'gone-coin',
            'symbol' => 'GONE',
            'name' => 'Gone Coin',
            'rank' => 272,
            'price' => 0.01,
            'detail_synced_at' => now(),
        ]);

        CoinProviderId::query()->create([
            'coin_id' => $coin->id,
            'provider' => 'coingecko',
            'external_id' => 'gone-coin',
        ]);

        $coin->tickers()->create([
            'provider' => 'coingecko',
            'exchange_id' => 'old-exchange',
            'exchange_name' => 'Old Exchange',
            'base_symbol' => 'GONE',
            'target_symbol' => 'USDT',
            'pair' => 'GONE/USDT',
            'price_usd' => 0.01,
            'volume_24h_usd' => 1000,
            'rank' => 1,
            'synced_at' => now()->subDay(),
        ]);

        Http::fake([
            'api.coingecko.com/api/v3/coins/gone-coin/tickers*' => Http::response(
                ['error' => 'coin not found'],
                404,
            ),
        ]);

        config(['marketdata.sync.tickers_pages' => 1]);

        $run = app(CoinTickerSyncService::class)->syncCoin($coin);

        $this->assertSame(SyncRun::STATUS_SUCCEEDED, $run->status);
        $this->assertSame(0, $run->records_processed);
        $this->assertStringContainsString('Removed delisted coin gone-coin', (string) $run->message);
        $this->assertDatabaseMissing('coins', ['id' => $coin->id]);
        $this->assertDatabaseMissing('coin_provider_ids', ['external_id' => 'gone-coin']);
        $this->assertDatabaseMissing('coin_tickers', ['coin_id' => $coin->id]);
    }

    public function test_sync_coin_tickers_removes_coin_when_primary_provider_returns_404_even_if_failover_mapping_remains(): void
    {
        $coin = Coin::query()->create([
            'slug' => 'multi-provider-coin',
            'symbol' => 'MPC',
            'name' => 'Multi Provider Coin',
            'rank' => 50,
            'price' => 1.5,
            'detail_synced_at' => now(),
            'last_provider' => 'coingecko',
        ]);

        CoinProviderId::query()->create([
            'coin_id' => $coin->id,
            'provider' => 'coingecko',
            'external_id' => 'multi-provider-coin',
        ]);

        CoinProviderId::query()->create([
            'coin_id' => $coin->id,
            'provider' => 'coinpaprika',
            'external_id' => 'mpc-multi-provider-coin',
        ]);

        Http::fake([
            'api.coingecko.com/api/v3/coins/multi-provider-coin/tickers*' => Http::response(
                ['error' => 'coin not found'],
                404,
            ),
        ]);

        config([
            'marketdata.sync.tickers_pages' => 1,
            'marketdata.primary' => 'coingecko',
        ]);

        $run = app(CoinTickerSyncService::class)->syncCoin($coin);

        $this->assertSame(SyncRun::STATUS_SUCCEEDED, $run->status);
        $this->assertStringContainsString('Removed delisted coin multi-provider-coin', (string) $run->message);
        $this->assertDatabaseMissing('coins', ['id' => $coin->id]);
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
