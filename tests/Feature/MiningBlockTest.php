<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\CoinShow;
use App\Models\Coin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class MiningBlockTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_page_shows_the_miners_block_with_entry_and_last_block(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee('id="mining"', false);
        $response->assertSee('For bitcoin miners', false);
        $response->assertSee('Mine via Rigly Blockparty', false);
        $response->assertSee('1,000 sats', false);
        $response->assertSee('955,703', false);
        $response->assertSee(
            'https://mempool.space/block/00000000000000000001089806eb0365ac250e44bc0b306ba0339a6b63c63fc5',
            false
        );
        $response->assertSee('June 2026', false);
    }

    public function test_miners_block_names_the_partnership_and_the_payout_risk(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk();
        // Rigly is a partner in config/picks.php, so the block has to say so where
        // the link is, not only on the disclosure page.
        $response->assertSee('Creator is a partner', false);
        $response->assertSee('strategic partnership with Rigly', false);
        $response->assertSee('pays out only when it finds a block', false);
    }

    public function test_miners_block_can_be_switched_off(): void
    {
        config(['mining.enabled' => false]);

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertDontSee('id="mining"', false);
        $response->assertDontSee('For bitcoin miners', false);
    }

    public function test_bitcoin_page_shows_the_miners_block(): void
    {
        // Visiting Bitcoin dispatches the insight sync, which would hit CoinGecko.
        Queue::fake();

        $bitcoin = Coin::query()->create([
            'slug' => 'bitcoin',
            'symbol' => 'BTC',
            'name' => 'Bitcoin',
            'rank' => 1,
            'price' => 77000,
            'detail_synced_at' => now(),
            'tickers_synced_at' => now(),
        ]);

        Livewire::test(CoinShow::class, ['coin' => $bitcoin])
            ->assertSee('For bitcoin miners')
            ->assertSee('Mine via Rigly Blockparty')
            ->assertOk();
    }

    public function test_other_coin_pages_do_not_show_the_miners_block(): void
    {
        $ethereum = Coin::query()->create([
            'slug' => 'ethereum',
            'symbol' => 'ETH',
            'name' => 'Ethereum',
            'rank' => 2,
            'price' => 3000,
            'detail_synced_at' => now(),
            'tickers_synced_at' => now(),
        ]);

        Livewire::test(CoinShow::class, ['coin' => $ethereum])
            ->assertDontSee('For bitcoin miners')
            ->assertDontSee('Mine via Rigly Blockparty')
            ->assertOk();
    }

    public function test_primary_navigation_no_longer_links_to_picks(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertDontSee('>Picks<', false);
        $response->assertDontSee(route('home') . '#picks', false);
        // The section itself and the disclosure link stay.
        $response->assertSee('id="picks"', false);
        $response->assertSee('Picks policy', false);
    }
}
