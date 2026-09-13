<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\SyncCoinDetail;
use App\Jobs\SyncStaleCoinDetails;
use App\Models\Coin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CoinDetailBackfillTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        Bus::fake();
    }

    public function test_it_dispatches_detail_sync_for_coins_without_detail_synced_at(): void
    {
        $coin = $this->coin('bitcoin', 1, null);

        $dispatched = (new SyncStaleCoinDetails)->handle();

        $this->assertSame(1, $dispatched);
        Bus::assertDispatched(
            SyncCoinDetail::class,
            fn (SyncCoinDetail $job): bool => $job->coinId === $coin->id,
        );
    }

    public function test_it_skips_coins_synced_within_the_stale_window(): void
    {
        $this->coin('bitcoin', 1, now()->subHour());

        config(['marketdata.sync.coin_detail_stale_hours' => 6]);

        $dispatched = (new SyncStaleCoinDetails)->handle();

        $this->assertSame(0, $dispatched);
        Bus::assertNothingDispatched();
    }

    public function test_it_takes_the_stalest_coins_up_to_the_batch_size(): void
    {
        $oldest = $this->coin('bitcoin', 1, now()->subDays(3));
        $older = $this->coin('ethereum', 2, now()->subDays(2));
        $this->coin('solana', 3, now()->subDay());

        config([
            'marketdata.sync.coin_detail_stale_hours' => 6,
            'marketdata.sync.detail_backfill_batch' => 2,
        ]);

        $dispatched = (new SyncStaleCoinDetails)->handle();

        $this->assertSame(2, $dispatched);
        Bus::assertDispatchedTimes(SyncCoinDetail::class, 2);
        Bus::assertDispatched(
            SyncCoinDetail::class,
            fn (SyncCoinDetail $job): bool => in_array($job->coinId, [$oldest->id, $older->id], true),
        );
    }

    public function test_it_ignores_coins_below_the_backfill_scope(): void
    {
        $this->coin('bitcoin', 1, null);
        $deepInTheRanking = $this->coin('dogecoin', 40, null);

        config(['marketdata.sync.detail_backfill_coins' => 1]);

        $dispatched = (new SyncStaleCoinDetails)->handle();

        $this->assertSame(1, $dispatched);
        Bus::assertNotDispatched(
            SyncCoinDetail::class,
            fn (SyncCoinDetail $job): bool => $job->coinId === $deepInTheRanking->id,
        );
    }

    public function test_it_ignores_unranked_coins(): void
    {
        $this->coin('some-token', null, null);

        $dispatched = (new SyncStaleCoinDetails)->handle();

        $this->assertSame(0, $dispatched);
        Bus::assertNothingDispatched();
    }

    private function coin(string $slug, ?int $rank, mixed $detailSyncedAt): Coin
    {
        return Coin::query()->create([
            'slug' => $slug,
            'symbol' => strtoupper($slug),
            'name' => ucfirst($slug),
            'rank' => $rank,
            'price' => 100,
            'detail_synced_at' => $detailSyncedAt,
        ]);
    }
}
