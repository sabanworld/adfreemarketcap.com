<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\Auth\Register;
use App\Livewire\Home;
use App\Models\Coin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Optional third-party contact (Google Ads and/or ChangeNOW) is the only thing
 * on this site that writes to a visitor's device or reaches another company
 * from the browser without being strictly necessary, so these tests guard the
 * promise the cookie and privacy policies make about it: nothing reaches those
 * hosts before the visitor says yes, and refusing is no harder than accepting.
 */
class ConsentTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_advertising_tag_is_off_by_default(): void
    {
        $this->assertFalse(config('google-ads.enabled'));

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertDontSee('googletagmanager.com', false);
        $response->assertDontSee('AW-', false);
        $response->assertDontSee('gtag', false);
    }

    public function test_the_tag_stays_off_while_the_conversion_id_is_missing(): void
    {
        config(['google-ads.enabled' => true, 'google-ads.conversion_id' => null]);

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertDontSee('googletagmanager.com', false);
        // A half-configured environment must not fall back to a notice-only bar
        // that silently claims there is nothing to consent to.
        $response->assertSee('Got it</button>', false);
    }

    public function test_no_request_reaches_google_before_the_visitor_accepts(): void
    {
        $this->enableTag();

        $response = $this->get(route('home'));

        $response->assertOk();
        // The loader knows the URL, but the page must never ship a script tag or
        // a preconnect that fetches it on load.
        $response->assertDontSee('<script async src="https://www.googletagmanager.com', false);
        $response->assertDontSee('<link rel="preconnect" href="https://www.googletagmanager.com', false);
        $response->assertSee('AW-18451487610', false);
    }

    public function test_consent_mode_starts_denied_for_every_google_purpose(): void
    {
        $this->enableTag();

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee("gtag('consent', 'default'", false);

        foreach (['ad_storage', 'ad_user_data', 'ad_personalization', 'analytics_storage'] as $purpose) {
            $response->assertSee($purpose . ": 'denied'", false);
        }
    }

    public function test_rejecting_is_offered_as_plainly_as_accepting(): void
    {
        $this->enableTag();

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee('Reject</button>', false);
        $response->assertSee('Accept</button>', false);
        // Equal prominence is the legal requirement, so neither button may carry
        // the primary treatment while the other does not.
        $response->assertDontSee('afmc-btn--primary afmc-btn--sm"', false);
    }

    public function test_the_bar_stays_a_notice_while_nothing_needs_consent(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee('Got it</button>', false);
        $response->assertSee('there is nothing here to opt out of', false);
        $response->assertDontSee('Reject</button>', false);
    }

    public function test_the_footer_control_offers_preferences_only_when_there_is_a_choice(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Cookie notice</button>', false)
            ->assertDontSee('Cookie preferences</button>', false);

        $this->enableTag();

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Cookie preferences</button>', false);
    }

    public function test_withdrawing_deletes_the_google_cookies_rather_than_only_stopping_the_next_one(): void
    {
        $this->enableTag();

        $response = $this->get(route('home'));

        $response->assertOk();
        // The privacy policy promises deletion on withdrawal, which only holds
        // while the reject path actually expires the _gcl_* cookies.
        $response->assertSee("name.startsWith('_gcl')", false);
        $response->assertSee('max-age=0', false);
    }

    public function test_a_conversion_with_no_label_configured_is_not_reportable(): void
    {
        $this->enableTag();

        $response = $this->get(route('home'));

        $response->assertOk();
        // An empty label has to leave the map empty rather than send a broken
        // send_to value against an action that does not exist in the account.
        $response->assertSee('const CONVERSIONS = {}', false);
    }

    public function test_a_configured_label_is_prefixed_with_the_account_id(): void
    {
        $this->enableTag();
        config([
            'google-ads.conversions.registration' => 'AbC-D_efG12h',
            'google-ads.conversions.watchlist' => null,
        ]);

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee('AW-18451487610\/AbC-D_efG12h', false);
        $response->assertDontSee('"watchlist"', false);
    }

    public function test_no_conversion_label_leaks_while_the_tag_is_off(): void
    {
        config(['google-ads.conversions.registration' => 'AbC-D_efG12h']);

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertDontSee('AbC-D_efG12h', false);
    }

    public function test_registering_reports_once_on_the_page_the_visitor_lands_on(): void
    {
        $this->enableTag();
        config(['google-ads.conversions.registration' => 'AbC-D_efG12h']);

        // The redirect ends the component that earned the conversion, so it has
        // to survive as a flash and be replayed by the layout.
        Livewire::test(Register::class)
            ->set('name', 'Ada')
            ->set('email', 'ada@example.test')
            ->set('password', 'correct-horse-battery-staple')
            ->set('password_confirmation', 'correct-horse-battery-staple')
            ->set('altcha', 'test-altcha-bypass-payload')
            ->call('register')
            ->assertRedirect(route('watchlist'));

        // Flashed, so it is gone again after the page that replays it. An
        // ordinary load carries no marker and reports nothing.
        $this->get(route('watchlist'))
            ->assertOk()
            ->assertSee('data-afmc-conversion="registration"', false);
    }

    public function test_an_ordinary_page_load_reports_no_conversion(): void
    {
        $this->enableTag();
        config(['google-ads.conversions.registration' => 'AbC-D_efG12h']);

        $this->get(route('home'))
            ->assertOk()
            ->assertDontSee('data-afmc-conversion', false);
    }

    public function test_starting_a_watchlist_reports_once_and_later_coins_do_not(): void
    {
        $user = User::factory()->create();
        $first = $this->coin('bitcoin', 'BTC', 1);
        $second = $this->coin('ethereum', 'ETH', 2);

        $component = Livewire::actingAs($user)->test(Home::class);

        $component->call('toggleWatch', $first->id)
            ->assertDispatched('afmc-conversion', name: 'watchlist');

        $component->call('toggleWatch', $second->id)
            ->assertNotDispatched('afmc-conversion');
    }

    public function test_emptying_and_restarting_a_watchlist_reports_again(): void
    {
        $user = User::factory()->create();
        $coin = $this->coin('bitcoin', 'BTC', 1);

        $component = Livewire::actingAs($user)->test(Home::class);

        $component->call('toggleWatch', $coin->id)->assertDispatched('afmc-conversion');
        // Removing the only coin empties the list, so adding one is a start again.
        $component->call('toggleWatch', $coin->id)->assertNotDispatched('afmc-conversion');
        $component->call('toggleWatch', $coin->id)->assertDispatched('afmc-conversion');
    }

    public function test_a_stored_answer_expires_when_the_consent_version_moves(): void
    {
        $this->enableTag();
        config(['consent.version' => '2027-01-01']);

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee('2027-01-01', false);
        $response->assertSee('stored.version === VERSION', false);
        $response->assertSee('stored.optional === true', false);
    }

    public function test_exchange_widget_alone_requires_consent_without_loading_google(): void
    {
        config([
            'exchange-widget.enabled' => true,
            'exchange-widget.link_id' => '2511974805bd4d',
        ]);

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee('Reject</button>', false);
        $response->assertSee('Accept</button>', false);
        $response->assertDontSee('googletagmanager.com', false);
        $response->assertSee('afmc-consent-changed', false);
    }

    private function coin(string $slug, string $symbol, int $rank): Coin
    {
        return Coin::query()->create([
            'slug' => $slug,
            'symbol' => $symbol,
            'name' => ucfirst($slug),
            'rank' => $rank,
            'price' => 50000,
        ]);
    }

    private function enableTag(): void
    {
        config([
            'google-ads.enabled' => true,
            'google-ads.conversion_id' => 'AW-18451487610',
        ]);
    }
}
