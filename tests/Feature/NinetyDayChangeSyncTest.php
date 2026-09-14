<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\SyncNinetyDayChanges;
use App\Models\Coin;
use App\Models\CoinProviderId;
use App\Models\MarketStatusSnapshot;
use App\Services\MarketData\AltcoinSeasonSampler;
use App\Services\MarketData\CoinGeckoProvider;
use App\Services\MarketData\MarketStatusSyncService;
use App\Services\MarketData\NinetyDayChangeSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use ReflectionMethod;
use Tests\TestCase;

/**
 * The altcoin season index needs a 90-day change per coin, and no ranking endpoint reports one:
 * CoinGecko's markets call accepts 1h, 24h, 7d, 14d, 30d, 200d and 1y, and silently omits any
 * other window instead of erroring. These tests pin the replacement source (chart history) and
 * the reason the old wiring failed quietly.
 */
class NinetyDayChangeSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();

        config([
            'marketdata.coingecko.base_url' => 'https://api.coingecko.com/api/v3',
            'marketdata.altcoin_season.top_n' => 50,
            'marketdata.altcoin_season.exclude_symbols' => ['USDT'],
            'marketdata.altcoin_season.minimum_history_days' => 80,
        ]);
    }

    public function test_the_markets_request_does_not_ask_for_an_unsupported_window(): void
    {
        $reflection = new ReflectionMethod(CoinGeckoProvider::class, 'fetchMarkets');
        $source = file_get_contents($reflection->getFileName());

        // A window CoinGecko does not support is not an error, it is a missing field, so asking
        // for one leaves a column permanently null and looks like a provider outage.
        $this->assertStringNotContainsString('90d,', (string) $source);
        $this->assertStringNotContainsString(',90d', (string) $source);
    }

    public function test_the_ranking_sync_cannot_null_the_derived_change(): void
    {
        $source = (string) file_get_contents(app_path('Services/MarketData/MarketSyncService.php'));

        // The ranking sync fills every other percentage column. If it listed this one it would
        // overwrite the derived figure with null on the next run, every ten minutes.
        $this->assertStringNotContainsString("'percent_change_90d' =>", $source);
    }

    public function test_the_sync_derives_the_change_from_ninety_days_of_history(): void
    {
        $bitcoin = $this->coin('bitcoin', 'BTC', 1);
        $alt = $this->coin('ethereum', 'ETH', 2);

        Http::fake([
            'api.coingecko.com/api/v3/coins/bitcoin/market_chart*' => Http::response([
                'prices' => $this->series(100.0, 150.0),
            ]),
            'api.coingecko.com/api/v3/coins/ethereum/market_chart*' => Http::response([
                'prices' => $this->series(100.0, 90.0),
            ]),
        ]);

        $run = app(NinetyDayChangeSyncService::class)->sync();

        $this->assertSame('succeeded', $run->status);
        $this->assertSame(2, (int) $run->records_processed);

        $this->assertSame(50.0, round((float) $bitcoin->fresh()->percent_change_90d, 2));
        $this->assertSame(-10.0, round((float) $alt->fresh()->percent_change_90d, 2));
        $this->assertNotNull($bitcoin->fresh()->percent_change_90d_synced_at);
    }

    public function test_a_coin_without_a_full_window_of_history_is_left_out(): void
    {
        $bitcoin = $this->coin('bitcoin', 'BTC', 1);
        $newcomer = $this->coin('rain', 'RAIN', 2);

        Http::fake([
            'api.coingecko.com/api/v3/coins/bitcoin/market_chart*' => Http::response([
                'prices' => $this->series(100.0, 150.0),
            ]),
            // Listed three weeks ago: its return is real but it is not a 90-day return, and
            // scoring it against Bitcoin's 90 days would compare two different questions.
            'api.coingecko.com/api/v3/coins/rain/market_chart*' => Http::response([
                'prices' => $this->series(100.0, 400.0, 21),
            ]),
        ]);

        app(NinetyDayChangeSyncService::class)->sync();

        $this->assertNotNull($bitcoin->fresh()->percent_change_90d);
        $this->assertNull($newcomer->fresh()->percent_change_90d);
    }

    public function test_a_fresh_change_is_not_fetched_again(): void
    {
        $bitcoin = $this->coin('bitcoin', 'BTC', 1);
        $bitcoin->update([
            'percent_change_90d' => 12.5,
            'percent_change_90d_synced_at' => now()->subHour(),
        ]);

        config(['marketdata.sync.ninety_day_stale_hours' => 20]);

        $run = app(NinetyDayChangeSyncService::class)->sync();

        $this->assertSame(0, (int) $run->records_processed);
        $this->assertSame('12.5000', $bitcoin->fresh()->percent_change_90d);
        Http::assertNothingSent();
    }

    public function test_a_stale_change_is_refreshed(): void
    {
        $bitcoin = $this->coin('bitcoin', 'BTC', 1);
        $bitcoin->update([
            'percent_change_90d' => 12.5,
            'percent_change_90d_synced_at' => now()->subDays(2),
        ]);

        Http::fake([
            'api.coingecko.com/api/v3/coins/bitcoin/market_chart*' => Http::response([
                'prices' => $this->series(100.0, 200.0),
            ]),
        ]);

        app(NinetyDayChangeSyncService::class)->sync();

        $this->assertSame(100.0, round((float) $bitcoin->fresh()->percent_change_90d, 2));
    }

    public function test_the_sample_is_bitcoin_plus_the_top_alts_minus_the_excluded_symbols(): void
    {
        $this->coin('bitcoin', 'BTC', 1);
        $this->coin('tether', 'USDT', 2);
        $this->coin('ethereum', 'ETH', 3);
        $unranked = $this->coin('ghost', 'GHOST', null);

        config(['marketdata.altcoin_season.top_n' => 1]);

        $sampler = app(AltcoinSeasonSampler::class);

        $this->assertSame(['ethereum'], $sampler->alts()->pluck('slug')->all());
        $this->assertSame(['bitcoin', 'ethereum'], $sampler->sample()->pluck('slug')->all());
        $this->assertNotContains($unranked->slug, $sampler->sample()->pluck('slug')->all());
    }

    public function test_the_status_snapshot_scores_the_derived_changes(): void
    {
        $this->coin('bitcoin', 'BTC', 1)->update(['percent_change_90d' => 10.0, 'market_cap' => 1_000_000]);
        $this->coin('ethereum', 'ETH', 2)->update(['percent_change_90d' => 40.0, 'market_cap' => 500_000]);
        $this->coin('solana', 'SOL', 3)->update(['percent_change_90d' => 5.0, 'market_cap' => 200_000]);
        // No history yet, so it is outside the sample rather than counted as a loser.
        $this->coin('rain', 'RAIN', 4);

        config(['marketdata.afmc10' => ['bitcoin']]);

        Http::fake([
            'api.alternative.me/fng/*' => Http::response([
                'data' => [['value' => '55', 'value_classification' => 'Neutral', 'timestamp' => '1710000000']],
            ]),
        ]);

        $run = app(MarketStatusSyncService::class)->sync();
        $snapshot = MarketStatusSnapshot::latestSnapshot();

        $this->assertSame('succeeded', $run->status);
        // One of the two alts with history beat Bitcoin's +10%.
        $this->assertSame(2, (int) $snapshot->altcoin_season_sample_size);
        $this->assertSame(50.0, round((float) $snapshot->altcoin_season_index, 2));
    }

    public function test_the_job_runs_the_service(): void
    {
        $this->coin('bitcoin', 'BTC', 1);

        Http::fake([
            'api.coingecko.com/api/v3/coins/bitcoin/market_chart*' => Http::response([
                'prices' => $this->series(100.0, 110.0),
            ]),
        ]);

        (new SyncNinetyDayChanges)->handle(app(NinetyDayChangeSyncService::class));

        $this->assertSame(10.0, round((float) Coin::query()->where('slug', 'bitcoin')->value('percent_change_90d'), 2));
    }

    /**
     * A job allowed to outrun the queue's retry_after is re-reserved and processed a second time,
     * so two workers would make the same fifty chart requests.
     */
    public function test_the_job_cannot_outrun_the_queue_retry_window(): void
    {
        $retryAfter = (int) config('queue.connections.redis.retry_after');
        $job = new SyncNinetyDayChanges;

        $this->assertGreaterThan(0, $retryAfter);
        $this->assertLessThan($retryAfter, $job->timeout);
        // The fetch budget has to leave room for the run to finish and record itself.
        $this->assertLessThan($job->timeout, (int) config('marketdata.sync.ninety_day_budget_seconds'));
    }

    public function test_a_run_that_hits_its_budget_leaves_the_rest_for_the_next_run(): void
    {
        $this->coin('bitcoin', 'BTC', 1);
        $this->coin('ethereum', 'ETH', 2);

        // No budget left, so the run should fetch nothing rather than start a pass it cannot end.
        config(['marketdata.sync.ninety_day_budget_seconds' => 0]);

        $run = app(NinetyDayChangeSyncService::class)->sync();

        $this->assertSame('succeeded', $run->status);
        $this->assertSame(0, (int) $run->records_processed);
        $this->assertStringContainsString('2 left for the next run', (string) $run->message);
        Http::assertNothingSent();
    }

    public function test_a_short_or_broken_series_yields_no_change(): void
    {
        $service = app(NinetyDayChangeSyncService::class);

        $this->assertNull($service->changeFrom([]));
        $this->assertNull($service->changeFrom([[0, 100.0]]));
        // A zero or negative opening price would make the percentage meaningless.
        $this->assertNull($service->changeFrom($this->series(0.0, 100.0)));
    }

    private function coin(string $slug, string $symbol, ?int $rank): Coin
    {
        $coin = Coin::query()->create([
            'slug' => $slug,
            'symbol' => $symbol,
            'name' => ucfirst($slug),
            'rank' => $rank,
            'price' => 100,
        ]);

        CoinProviderId::query()->create([
            'coin_id' => $coin->id,
            'provider' => 'coingecko',
            'external_id' => $slug,
        ]);

        return $coin;
    }

    /**
     * Daily closes spanning $days, moving from $from to $to.
     *
     * @return list<array{0: int, 1: float}>
     */
    private function series(float $from, float $to, int $days = 90): array
    {
        $points = [];
        $start = Carbon::now()->subDays($days);

        for ($day = 0; $day <= $days; $day++) {
            $fraction = $days === 0 ? 1.0 : $day / $days;
            $points[] = [
                $start->copy()->addDays($day)->getTimestampMs(),
                $from + ($to - $from) * $fraction,
            ];
        }

        return $points;
    }
}
