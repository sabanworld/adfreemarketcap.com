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
        // Rigly is a partner in config/picks.php, so the block has to name that where
        // the link is, not only on the disclosure page.
        $response->assertSee(config('company.person') . ' is a partner', false);
        $response->assertSee('strategic partnership with Rigly', false);
        $response->assertSee('pays out only when it finds a block', false);
    }

    public function test_home_page_refers_to_solochance(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee('solochance.io turns your hashrate into the odds of finding a whole block solo', false);
        $response->assertSee('Work out your odds', false);
        $response->assertSee('Also ad-free', false);
        $response->assertSee('href="https://solochance.io"', false);
    }

    public function test_primary_navigation_links_to_solochance(): void
    {
        $url = config('company.sister_sites.solochance.url');

        $response = $this->get(route('home'));

        $response->assertOk();
        // Amber primary CTA inside the desktop nav, not another muted market tab. The visible
        // label says what the visitor gets (mining odds); the site name stays in the accessible name.
        $this->assertMatchesRegularExpression(
            '#data-afmc-nav[^>]*>.*href="' . preg_quote($url, '#') . '"[^>]*class="[^"]*afmc-btn--primary[^"]*afmc-nav__cta[^"]*"[^>]*>.*Your odds of mining a block.*, on solochance\.io.*</nav>#s',
            $response->getContent(),
        );
        // Same CTA leads the mobile More drawer, with a line saying what the calculator does.
        $this->assertMatchesRegularExpression(
            '#id="afmc-nav-drawer"[^>]*>.*href="' . preg_quote($url, '#') . '"[^>]*class="[^"]*afmc-btn--primary[^"]*"[^>]*>.*Your odds of mining a block.*Put in your hashrate and solochance\.io shows how likely you are to find a block#s',
            $response->getContent(),
        );
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
        // Visiting a coin with a Nostr feed dispatches that sync, which would hit a relay
        // gateway. The test queue runs inline, so it has to be faked here too.
        Queue::fake();

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
