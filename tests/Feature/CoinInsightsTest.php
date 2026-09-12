<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\CoinShow;
use App\Models\Coin;
use App\Models\SyncRun;
use App\Services\MarketData\CoinInsightSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class CoinInsightsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    public function test_sync_bitcoin_insights_persists_treasury_and_cycles(): void
    {
        Coin::query()->create([
            'slug' => 'bitcoin',
            'symbol' => 'BTC',
            'name' => 'Bitcoin',
            'rank' => 1,
            'price' => 77000,
            'detail_synced_at' => now(),
        ]);

        Http::fake([
            'api.coingecko.com/api/v3/companies/public_treasury/bitcoin' => Http::response(
                $this->fixture('coingecko_bitcoin_treasury.json'),
            ),
            'charts.bitcoin.com/api/v1/charts/pi-cycle-top*' => Http::response(
                $this->fixture('bitcoin_com_pi_cycle.json'),
            ),
        ]);

        $run = app(CoinInsightSyncService::class)->syncBitcoinInsights();

        $this->assertSame(SyncRun::STATUS_SUCCEEDED, $run->status);
        $this->assertDatabaseHas('coin_treasury_snapshots', [
            'total_holdings' => 1294579.77,
            'companies_count' => 2,
            'provider' => 'coingecko',
        ]);
        $this->assertDatabaseHas('coin_treasury_holders', [
            'name' => 'Strategy',
            'rank' => 1,
        ]);
        $this->assertDatabaseHas('coin_market_cycle_snapshots', [
            'pi_cycle_status' => 'mid_cycle',
            'provider' => 'bitcoin_com_charts',
        ]);
    }

    public function test_bitcoin_detail_page_shows_treasury_and_cycles(): void
    {
        $coin = Coin::query()->create([
            'slug' => 'bitcoin',
            'symbol' => 'BTC',
            'name' => 'Bitcoin',
            'rank' => 1,
            'price' => 77000,
            'detail_synced_at' => now(),
            'tickers_synced_at' => now(),
        ]);

        $coin->treasurySnapshot()->create([
            'total_holdings' => 1_300_000,
            'total_value_usd' => 100_000_000_000,
            'market_cap_dominance' => 6.2,
            'companies_count' => 1,
            'provider' => 'coingecko',
            'synced_at' => now(),
        ]);

        $coin->treasuryHolders()->create([
            'name' => 'Strategy',
            'symbol' => 'MSTR.US',
            'country' => 'US',
            'total_holdings' => 845050,
            'percentage_of_total_supply' => 4.024,
            'rank' => 1,
        ]);

        $coin->marketCycleSnapshot()->create([
            'price' => 77000,
            'ma111' => 67000,
            'ma350x2' => 160000,
            'ma_gap_percent' => 58.1,
            'pi_cycle_status' => 'mid_cycle',
            'last_halving_at' => now()->subYear(),
            'next_halving_at' => now()->addYears(2),
            'days_since_halving' => 365,
            'days_until_halving' => 700,
            'cycle_progress_percent' => 34.2,
            'halving_epoch' => 4,
            'provider' => 'bitcoin_com_charts',
            'synced_at' => now(),
        ]);

        Livewire::test(CoinShow::class, ['coin' => $coin->fresh()])
            ->assertSee('Bitcoin Market Cycles')
            ->assertSee('Bitcoin Treasury Holdings')
            ->assertSee('Strategy')
            ->assertSee('Halving cycle progress')
            ->assertOk();
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
