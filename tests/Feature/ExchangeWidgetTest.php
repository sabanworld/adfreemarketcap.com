<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\CoinShow;
use App\Models\Coin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The ChangeNOW swap widget is an affiliate embed that talks to changenow.io
 * from the browser, so these tests guard the promise the policies make about
 * it: placeholder until Accept, no iframe in the markup, commission labelled.
 */
class ExchangeWidgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_widget_is_off_in_the_test_suite_by_default(): void
    {
        $this->assertFalse(config('exchange-widget.enabled'));

        $this->get(route('home'))
            ->assertOk()
            ->assertDontSee('afmc-exchange', false)
            ->assertDontSee('Accept cookies to use this swap', false);
    }

    public function test_the_widget_stays_off_while_the_link_id_is_missing(): void
    {
        config([
            'exchange-widget.enabled' => true,
            'exchange-widget.link_id' => null,
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertDontSee('afmc-exchange', false)
            ->assertDontSee('Accept cookies to use this swap', false);
    }

    public function test_home_and_coin_pages_show_the_placeholder_without_an_iframe(): void
    {
        $this->enableWidget();
        Queue::fake();
        $coin = $this->coin('bitcoin', 'BTC');

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('afmc-exchange', false)
            ->assertSee('Accept cookies to use this swap', false)
            ->assertSee('Cookie preferences', false)
            ->assertDontSee('<iframe', false)
            ->assertDontSee('src="https://changenow.io', false)
            ->assertDontSee('<script src="https://changenow.io', false);

        Livewire::test(CoinShow::class, ['coin' => $coin])
            ->assertOk()
            ->assertSee('afmc-exchange', false)
            ->assertSee('Accept cookies to use this swap', false)
            ->assertDontSee('<iframe', false)
            ->assertDontSee('src="https://changenow.io', false);
    }

    public function test_the_coin_page_passes_the_ticker_into_the_widget_defaults(): void
    {
        $this->enableWidget();
        Queue::fake();
        $coin = $this->coin('solana', 'SOL');

        // Blade @js escapes quotes as \u0022 inside the HTML attribute.
        Livewire::test(CoinShow::class, ['coin' => $coin])
            ->assertOk()
            ->assertSee('from\u0022:\u0022sol', false)
            ->assertSee('to\u0022:\u0022eth', false)
            ->assertSee('2511974805bd4d', false);
    }

    public function test_ethereum_defaults_the_destination_to_bitcoin(): void
    {
        $this->enableWidget();
        Queue::fake();
        $coin = $this->coin('ethereum', 'ETH');

        Livewire::test(CoinShow::class, ['coin' => $coin])
            ->assertOk()
            ->assertSee('from\u0022:\u0022eth', false)
            ->assertSee('to\u0022:\u0022btc', false);
    }

    public function test_the_widget_names_the_commission_interest(): void
    {
        $this->enableWidget();
        $person = (string) config('company.person');

        $this->get(route('home'))
            ->assertOk()
            ->assertSee("{$person} earns a commission", false)
            ->assertSee('Non-custodial swaps.', false)
            ->assertDontSee('If you complete one, ' . $person . ' may earn a commission', false);
    }

    public function test_the_widget_remounts_when_the_page_theme_changes(): void
    {
        $this->enableWidget();

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('MutationObserver', false)
            ->assertSee("attributeFilter: ['data-theme']", false)
            ->assertSee('params.darkMode = dark', false)
            ->assertSee('this.darkBackground', false);
    }

    public function test_enabling_the_widget_alone_turns_the_cookie_bar_into_a_choice(): void
    {
        $this->enableWidget();

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Reject</button>', false)
            ->assertSee('Accept</button>', false)
            ->assertSee('ChangeNOW swap widget', false)
            ->assertSee('Cookie preferences</button>', false);
    }

    public function test_consent_loader_knows_the_connector_url_without_shipping_a_script_tag(): void
    {
        $this->enableWidget();

        $response = $this->get(route('home'));

        $response->assertOk();
        // @json escapes forward slashes, so the literal URL string is not in the HTML.
        $response->assertSee('changenow.io\/embeds\/exchange-widget\/v2\/stepper-connector.js', false);
        $response->assertDontSee("<script defer type='text/javascript' src='https://changenow.io", false);
        $response->assertDontSee('<script src="https://changenow.io', false);
    }

    private function coin(string $slug, string $symbol): Coin
    {
        return Coin::query()->create([
            'slug' => $slug,
            'symbol' => $symbol,
            'name' => ucfirst($slug),
            'rank' => 1,
            'price' => 100,
            'detail_synced_at' => Carbon::now(),
            'tickers_synced_at' => Carbon::now(),
            'charts_synced_at' => Carbon::now(),
        ]);
    }

    private function enableWidget(): void
    {
        config([
            'exchange-widget.enabled' => true,
            'exchange-widget.link_id' => '2511974805bd4d',
        ]);
    }
}
