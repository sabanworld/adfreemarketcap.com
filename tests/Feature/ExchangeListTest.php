<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Coin;
use App\Models\CoinTicker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * The exchange markets table on a coin page is seven columns behind a sideways scroll. Below
 * 700px the same venues read as two lines each, with the volume share as a bar.
 */
class ExchangeListTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Visiting a coin page can enqueue a freshness sync. Nothing here is testing the queue.
        Queue::fake();
    }

    public function test_a_coin_page_ships_both_the_exchange_table_and_the_phone_list(): void
    {
        $coin = $this->coinWithVenues();

        $response = $this->get(route('coins.show', $coin));

        $response->assertOk();
        $response->assertSee('afmc-exlist', false);
        $response->assertSee('afmc-board__table', false);
        $response->assertSee('Venue 1', false);
    }

    /**
     * Bars are relative to the biggest venue, not to 100%. The top exchange rarely holds more
     * than a fifth of a coin's volume, so a bar scaled to 100% never fills and reads as missing
     * data instead of a small share.
     */
    public function test_the_share_bar_is_relative_to_the_largest_venue(): void
    {
        $this->coinWithVenues(2);

        $rendered = Blade::render(
            '<x-afmc.exchange-list :tickers="$tickers" />',
            ['tickers' => CoinTicker::query()->orderBy('rank')->get()],
        );

        // 20% is the top venue, so it fills the track; 10% is half of it.
        $this->assertStringContainsString('width:100%', $rendered);
        $this->assertStringContainsString('width:50%', $rendered);
        $this->assertStringContainsString('20.00%', $rendered);
        $this->assertStringContainsString('10.00%', $rendered);
    }

    public function test_the_list_shows_the_top_venues_and_offers_the_rest(): void
    {
        $this->coinWithVenues(8);

        $rendered = Blade::render(
            '<x-afmc.exchange-list :tickers="$tickers" :initial="5" />',
            ['tickers' => CoinTicker::query()->orderBy('rank')->get()],
        );

        $this->assertStringContainsString(__('Show all :count markets', ['count' => 8]), $rendered);
        // The tail is in the markup and hidden, not fetched on tap: it is three rows, and a
        // round trip for three rows is worse than the bytes.
        $this->assertSame(8, substr_count($rendered, 'afmc-exlist__item'));
        $this->assertSame(3, preg_match_all('/afmc-exlist__item[^>]*x-show="all"/', $rendered));
    }

    public function test_a_short_list_has_no_show_all_button(): void
    {
        $this->coinWithVenues(3);

        $rendered = Blade::render(
            '<x-afmc.exchange-list :tickers="$tickers" :initial="5" />',
            ['tickers' => CoinTicker::query()->orderBy('rank')->get()],
        );

        $this->assertStringNotContainsString('afmc-exlist__foot', $rendered);
    }

    /**
     * A referral link is a paid relationship, so it stays declared in both readings of the
     * table rather than only the wide one.
     */
    public function test_venue_links_stay_declared_as_sponsored(): void
    {
        $this->coinWithVenues(1);

        $rendered = Blade::render(
            '<x-afmc.exchange-list :tickers="$tickers" />',
            ['tickers' => CoinTicker::query()->get()],
        );

        $this->assertStringContainsString('rel="noopener noreferrer sponsored"', $rendered);
    }

    public function test_stale_and_anomalous_venues_are_muted_in_the_list_too(): void
    {
        $coin = $this->coinWithVenues(1);

        CoinTicker::query()->create([
            'coin_id' => $coin->id,
            'exchange_id' => 'stale-venue',
            'exchange_name' => 'Stale Venue',
            'base_symbol' => 'BTC',
            'target_symbol' => 'USD',
            'pair' => 'BTC/USD',
            'price_usd' => 60000,
            'volume_24h_usd' => 1_000_000,
            'volume_share_percent' => 0.01,
            'trust_score' => 'red',
            'is_stale' => true,
            'rank' => 2,
            'synced_at' => now(),
        ]);

        $rendered = Blade::render(
            '<x-afmc.exchange-list :tickers="$tickers" />',
            ['tickers' => CoinTicker::query()->orderBy('rank')->get()],
        );

        $this->assertSame(1, substr_count($rendered, 'is-muted'));
        $this->assertStringContainsString(__('Low'), $rendered);
    }

    private function coinWithVenues(int $count = 8): Coin
    {
        $coin = Coin::query()->create([
            'slug' => 'bitcoin',
            'symbol' => 'BTC',
            'name' => 'Bitcoin',
            'rank' => 1,
            'price' => 77304,
            'market_cap' => 1_550_000_000_000,
            'volume_24h' => 14_870_000_000,
            'detail_synced_at' => now(),
            'tickers_synced_at' => now(),
        ]);

        foreach (range(1, $count) as $rank) {
            CoinTicker::query()->create([
                'coin_id' => $coin->id,
                'exchange_id' => 'venue-' . $rank,
                'exchange_name' => 'Venue ' . $rank,
                'base_symbol' => 'BTC',
                'target_symbol' => 'USDT',
                'pair' => 'BTC/USDT',
                'price_usd' => 77300 + $rank,
                'volume_24h_usd' => 2_000_000_000 / $rank,
                'volume_share_percent' => 20 / $rank,
                'trust_score' => 'green',
                'trade_url' => 'https://example.test/venue-' . $rank,
                'rank' => $rank,
                'synced_at' => now(),
            ]);
        }

        return $coin;
    }
}
