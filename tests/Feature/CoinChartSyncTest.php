<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\SyncCoinCharts;
use App\Jobs\SyncHotCoinCharts;
use App\Livewire\CoinShow;
use App\Models\Coin;
use App\Models\CoinChartSeries;
use App\Models\CoinProviderId;
use App\Models\SyncRun;
use App\Services\MarketData\CoinChartService;
use App\Services\MarketData\CoinChartSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class CoinChartSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        config([
            'marketdata.coingecko.base_url' => 'https://api.coingecko.com/api/v3',
        ]);
    }

    public function test_sync_coin_persists_three_series_and_derives_chart_7d(): void
    {
        $coin = $this->bitcoin();
        $nowMs = ((int) now()->timestamp) * 1000;

        Http::fake(function ($request) use ($nowMs) {
            $url = (string) $request->url();

            if (! str_contains($url, '/market_chart')) {
                return Http::response(['error' => 'unexpected'], 500);
            }

            if (str_contains($url, 'days=1')) {
                return Http::response([
                    'prices' => [
                        [$nowMs - 3_600_000, 64000.0],
                        [$nowMs - 1_800_000, 64100.0],
                        [$nowMs, 64200.0],
                    ],
                ]);
            }

            if (str_contains($url, 'days=90')) {
                return Http::response([
                    'prices' => [
                        [$nowMs - 10 * 86_400_000, 58000.0],
                        [$nowMs - 5 * 86_400_000, 60000.0],
                        [$nowMs - 2 * 86_400_000, 62000.0],
                        [$nowMs, 64000.0],
                    ],
                ]);
            }

            return Http::response([
                'prices' => [
                    [$nowMs - 400 * 86_400_000, 20000.0],
                    [$nowMs - 200 * 86_400_000, 40000.0],
                    [$nowMs, 64000.0],
                ],
            ]);
        });

        $run = app(CoinChartSyncService::class)->syncCoin($coin);

        $this->assertSame(SyncRun::STATUS_SUCCEEDED, $run->status);
        $this->assertSame(3, $run->records_processed);
        $this->assertDatabaseHas('coin_chart_series', [
            'coin_id' => $coin->id,
            'series' => CoinChartSeries::SERIES_INTRADAY,
        ]);
        $this->assertDatabaseHas('coin_chart_series', [
            'coin_id' => $coin->id,
            'series' => CoinChartSeries::SERIES_SHORT,
        ]);
        $this->assertDatabaseHas('coin_chart_series', [
            'coin_id' => $coin->id,
            'series' => CoinChartSeries::SERIES_DAILY,
        ]);

        $coin->refresh();
        $this->assertNotEmpty($coin->chart_7d);
    }

    public function test_hot_charts_job_syncs_configured_majors(): void
    {
        $this->bitcoin();
        Coin::query()->create([
            'slug' => 'ethereum',
            'symbol' => 'ETH',
            'name' => 'Ethereum',
            'rank' => 2,
            'price' => 3000,
            'last_provider' => 'coingecko',
        ]);
        CoinProviderId::query()->create([
            'coin_id' => Coin::query()->where('slug', 'ethereum')->value('id'),
            'provider' => 'coingecko',
            'external_id' => 'ethereum',
        ]);

        config(['marketdata.sync.hot_coins' => ['bitcoin', 'ethereum']]);

        Http::fake([
            'api.coingecko.com/api/v3/coins/*/market_chart*' => Http::response([
                'prices' => [
                    [((int) now()->subDay()->timestamp) * 1000, 100.0],
                    [((int) now()->timestamp) * 1000, 110.0],
                ],
            ]),
        ]);

        $run = app(CoinChartSyncService::class)->syncHotCoins();

        $this->assertSame(SyncRun::STATUS_SUCCEEDED, $run->status);
        $this->assertGreaterThanOrEqual(6, (int) $run->records_processed);
    }

    public function test_coin_detail_shows_range_tabs_and_switches_series(): void
    {
        Queue::fake();

        $coin = $this->bitcoin();
        $now = (int) now()->timestamp * 1000;

        app(CoinChartService::class)->upsertSeries($coin, CoinChartSeries::SERIES_INTRADAY, [
            [$now - 3_600_000, 64000.0],
            [$now - 1_800_000, 64100.0],
            [$now, 64200.0],
        ]);
        app(CoinChartService::class)->upsertSeries($coin, CoinChartSeries::SERIES_SHORT, [
            [$now - 7 * 86_400_000, 60000.0],
            [$now - 3 * 86_400_000, 62000.0],
            [$now, 64000.0],
        ]);
        app(CoinChartService::class)->upsertSeries($coin, CoinChartSeries::SERIES_DAILY, [
            [$now - 400 * 86_400_000, 20000.0],
            [$now - 200 * 86_400_000, 40000.0],
            [$now, 64000.0],
        ]);

        Livewire::test(CoinShow::class, ['coin' => $coin->fresh()])
            ->assertSet('chartRange', '7d')
            ->assertSee('1H', false)
            ->assertSee('All', false)
            ->assertSeeHtml('id="coin-chart"')
            // A canvas is opaque to a screen reader, so the chart says what it plots.
            ->assertSeeHtml('role="img"')
            ->assertSeeHtml('Bitcoin price over 7 days in USD')
            ->call('setChartRange', '1y')
            ->assertSet('chartRange', '1y')
            ->assertSeeHtml('Bitcoin price over a year in USD')
            ->assertOk();
    }

    public function test_coin_detail_dispatches_chart_sync_when_series_missing(): void
    {
        Queue::fake();

        $coin = $this->bitcoin();

        Livewire::test(CoinShow::class, ['coin' => $coin]);

        Queue::assertPushed(
            SyncCoinCharts::class,
            fn (SyncCoinCharts $job): bool => $job->coinId === $coin->id,
        );
    }

    public function test_schedule_registers_hot_chart_sync_job(): void
    {
        $this->assertTrue(class_exists(SyncHotCoinCharts::class));
    }

    private function bitcoin(): Coin
    {
        $coin = Coin::query()->create([
            'slug' => 'bitcoin',
            'symbol' => 'BTC',
            'name' => 'Bitcoin',
            'rank' => 1,
            'price' => 64000,
            'last_provider' => 'coingecko',
            'detail_synced_at' => now(),
            'tickers_synced_at' => now(),
        ]);

        CoinProviderId::query()->create([
            'coin_id' => $coin->id,
            'provider' => 'coingecko',
            'external_id' => 'bitcoin',
        ]);

        return $coin;
    }
}
