<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\Home;
use App\Models\Coin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Below 700px a ranked coin table is replaced by a list of rows that open in place. Both forms
 * are server-rendered and CSS picks between them, so these tests check that both ship and that
 * the phone form carries the controls the table headers took with them.
 */
class MarketListTest extends TestCase
{
    use RefreshDatabase;

    public function test_markets_home_ships_both_the_table_and_the_phone_list(): void
    {
        $this->bitcoin();

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee('afmc-board__list', false);
        $response->assertSee('afmc-board__table', false);
        $response->assertSee('afmc-list__row', false);
        // The row expands rather than navigating, and the panel is what it controls.
        $response->assertSee('aria-controls="afmc-row-bitcoin"', false);
        $response->assertSee('id="afmc-row-bitcoin"', false);
    }

    /**
     * A row that navigates on tap costs the reader their place in the ranking, so the coin page
     * is a button inside the panel instead of the row itself.
     */
    public function test_a_list_row_is_a_disclosure_and_the_coin_page_is_a_button_inside_it(): void
    {
        $coin = $this->bitcoin();

        $rendered = Blade::render(
            '<x-afmc.market-list :coins="$coins" />',
            ['coins' => Coin::query()->get()],
        );

        $this->assertStringContainsString('aria-expanded="false"', $rendered);
        $this->assertStringContainsString('Bitcoin details', $rendered);
        $this->assertStringContainsString('href="' . route('coins.show', $coin) . '"', $rendered);
        // The identity inside the row must not be a link: an anchor inside a button is invalid
        // markup and the row would fight its own tap target.
        $this->assertStringNotContainsString('<a class="afmc-identity"', $rendered);
    }

    /**
     * The row gives one line to the coin's name and its price. Both of the things that used to
     * take that space away, a sparkline beside the price and two cents on a five-figure number,
     * ended up hiding the name itself: "Bitcoin" rendered as "Bit…" on a 390px screen.
     */
    public function test_a_row_spends_its_first_line_on_the_name_and_the_price(): void
    {
        $this->bitcoin();

        $rendered = Blade::render(
            '<x-afmc.market-list :coins="$coins" />',
            ['coins' => Coin::query()->get()],
        );

        $firstLine = Str::between($rendered, 'afmc-list__top', 'afmc-list__meta');
        $secondLine = Str::between($rendered, 'afmc-list__meta', 'afmc-list__panel');

        $this->assertStringContainsString('$77,304', $firstLine);
        $this->assertStringNotContainsString('$77,304.00', $firstLine);
        $this->assertStringNotContainsString('afmc-sparkline', $firstLine);
        $this->assertStringContainsString('afmc-sparkline', $secondLine);

        // The table keeps the cents, because a wide row has the characters to spare.
        $this->get(route('home'))->assertSee('$77,304.00', false);
    }

    public function test_the_row_panel_carries_the_figures_the_second_line_has_no_room_for(): void
    {
        $this->bitcoin();

        $rendered = Blade::render(
            '<x-afmc.market-list :coins="$coins" />',
            ['coins' => Coin::query()->get()],
        );

        $this->assertStringContainsString(__('Volume 24h'), $rendered);
        $this->assertStringContainsString(__('Vol / cap'), $rendered);
        $this->assertStringContainsString(__('Circulating'), $rendered);
        // 14.87B / 1.55T, the one ratio that says whether a rank is liquid.
        $this->assertStringContainsString('0.96%', $rendered);
        $this->assertStringContainsString('20.08M BTC', $rendered);
    }

    /**
     * Header sorting goes with the table, so the list gets a select and a direction toggle.
     * Both readings ship and the 700px rule decides which control a viewport sees.
     */
    public function test_the_phone_sort_control_offers_the_same_columns_as_the_table_headers(): void
    {
        $this->bitcoin();

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee('data-afmc-mobilesort', false);
        $response->assertSee('data-afmc-densetoggle', false);

        foreach (Home::SORT_COLUMNS as $column => $label) {
            $response->assertSee('value="' . $column . '"', false);
            $response->assertSee(__($label), false);
        }
    }

    public function test_picking_a_sort_column_starts_on_the_direction_that_column_is_read_in(): void
    {
        $this->bitcoin();

        Livewire::test(Home::class)
            ->set('sort', 'volume_24h')
            ->assertSet('direction', 'desc')
            ->set('sort', 'rank')
            ->assertSet('direction', 'asc')
            ->call('toggleDirection')
            ->assertSet('direction', 'desc');
    }

    public function test_an_unknown_sort_column_falls_back_to_rank(): void
    {
        $this->bitcoin();

        Livewire::test(Home::class)
            ->set('sort', 'drop table coins')
            ->assertSet('sort', 'rank')
            ->assertSet('direction', 'asc')
            ->assertOk();
    }

    /**
     * The star is a 32px cell target in the table. In the panel it is a labelled action, and on
     * the watchlist every row arrives already starred, so it reads "Saved".
     */
    public function test_the_panel_watch_action_says_what_it_does(): void
    {
        $coin = $this->bitcoin();
        $user = User::factory()->create();
        $this->actingAs($user);

        $unwatched = Blade::render(
            '<x-afmc.market-list :coins="$coins" />',
            ['coins' => Coin::query()->get()],
        );

        $this->assertStringContainsString('afmc-panel-action', $unwatched);
        $this->assertStringContainsString(__('Watch'), $unwatched);

        $watched = Blade::render(
            '<x-afmc.market-list :coins="$coins" :watched-ids="$ids" />',
            ['coins' => Coin::query()->get(), 'ids' => [$coin->id]],
        );

        $this->assertStringContainsString(__('Saved'), $watched);
        $this->assertStringContainsString('aria-pressed="true"', $watched);
    }

    /**
     * An empty result used to render its copy inside a table cell. The board renders it in
     * place of both forms, so a phone reads the same sentence and it exists once.
     */
    public function test_an_empty_result_explains_itself_once_for_both_forms(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee('afmc-board__empty', false);
        $response->assertSee(__('No coins yet'), false);
        $response->assertDontSee('afmc-board__list', false);
        $response->assertDontSee('afmc-table__empty', false);
    }

    /**
     * A 52px line cannot draw 168 hourly prices, and the coordinate list is the heaviest thing
     * in a market row. Both ends have to survive the resample or the line stops agreeing with
     * the percentage printed beside it.
     */
    public function test_a_sparkline_resamples_to_the_width_it_draws_at(): void
    {
        $rising = range(1, 200);

        $wide = Blade::render('<x-afmc.sparkline :data="$data" />', ['data' => $rising]);
        $narrow = Blade::render('<x-afmc.sparkline :data="$data" width="52" height="22" />', ['data' => $rising]);

        $this->assertLessThan(
            substr_count($wide, ' L '),
            substr_count($narrow, ' L '),
            'A narrower sparkline must carry fewer coordinates.',
        );
        $this->assertLessThan(strlen($wide), strlen($narrow));

        // Direction is read off the first and last price, so resampling may not drop either.
        $this->assertStringContainsString('var(--chart-up)', $narrow);
        $falling = Blade::render(
            '<x-afmc.sparkline :data="$data" width="52" height="22" />',
            ['data' => array_reverse($rising)],
        );
        $this->assertStringContainsString('var(--chart-down)', $falling);
    }

    /**
     * Price alerts do not exist yet. The button ships anyway because the panel is where it will
     * live, but it has to say so: no click target, a Soon marker for sighted readers, and a
     * label that gives a screen reader the same fact.
     */
    public function test_the_alert_action_admits_it_does_nothing_yet(): void
    {
        $this->bitcoin();

        $rendered = Blade::render(
            '<x-afmc.market-list :coins="$coins" />',
            ['coins' => Coin::query()->get()],
        );

        $this->assertStringContainsString('afmc-panel-action is-soon', $rendered);
        $this->assertStringContainsString('aria-disabled="true"', $rendered);
        $this->assertStringContainsString('class="afmc-soon"', $rendered);
        $this->assertStringContainsString(__('Coming soon'), $rendered);
        $this->assertStringContainsString(__('Price alerts, coming soon'), $rendered);

        // A promise the code cannot keep is worse than no button: nothing may be wired to it,
        // and it must not be reachable as a real control either.
        $this->assertStringNotContainsString('toggleAlert', $rendered);
        $this->assertDoesNotMatchRegularExpression(
            '/is-soon[^>]*(wire:click|x-on:click|@click)/',
            $rendered,
        );
    }

    /**
     * Sharing needs no server round trip and no third party: the sheet is the visitor's own OS,
     * and the fallback writes the link to their clipboard because they asked for it.
     */
    public function test_the_share_action_carries_the_absolute_coin_url_and_hides_when_it_cannot_work(): void
    {
        $coin = $this->bitcoin();

        $rendered = Blade::render(
            '<x-afmc.market-list :coins="$coins" />',
            ['coins' => Coin::query()->get()],
        );

        $this->assertStringContainsString('afmcShare', $rendered);
        $this->assertStringContainsString(route('coins.show', $coin), $rendered);
        // Hidden until Alpine confirms the browser has a share sheet or a clipboard, so the
        // label never promises something the browser cannot do.
        $this->assertMatchesRegularExpression('/x-show="supported"[^>]*x-cloak/', $rendered);
        $this->assertStringContainsString(__('Link copied to your clipboard'), $rendered);
        $this->assertStringContainsString('aria-live="polite"', $rendered);
    }

    /**
     * The share helper is registered once for the page. Fifty rows carrying their own copy of
     * the same closure is the kind of weight that makes a phone list pointless.
     */
    public function test_the_share_behaviour_is_registered_once_rather_than_inlined_per_row(): void
    {
        $this->bitcoin();

        $rendered = Blade::render(
            '<x-afmc.market-list :coins="$coins" />',
            ['coins' => Coin::query()->get()],
        );

        $this->assertSame(1, substr_count($rendered, 'x-data="afmcShare"'));
        $this->assertStringNotContainsString('navigator.share', $rendered);
        $this->assertStringContainsString('afmcShare', File::get(resource_path('js/app.js')));
    }

    public function test_the_watchlist_uses_the_same_board_as_the_markets_table(): void
    {
        $coin = $this->bitcoin();
        $user = User::factory()->create();
        $user->watchedCoins()->attach($coin);

        $response = $this->actingAs($user)->get(route('watchlist'));

        $response->assertOk();
        $response->assertSee('afmc-board__list', false);
        $response->assertSee('afmc-board__table', false);
        $response->assertSee('Bitcoin', false);
    }

    /**
     * The swap is a CSS decision on server-rendered markup. Measuring the viewport in
     * JavaScript first would paint the table and then replace it on a phone.
     */
    public function test_the_breakpoint_and_not_javascript_decides_which_form_a_viewport_gets(): void
    {
        $components = File::get(resource_path('css/afmc.css'));
        $tokens = File::get(resource_path('css/design-system/base.css'));

        $this->assertStringContainsString('.afmc-board__list', $components);
        $this->assertStringContainsString('.afmc-board__table', $components);
        $this->assertStringContainsString('[data-afmc-mobilesort]{display:none}', $tokens);
        $this->assertStringContainsString('[data-afmc-row-1h]{display:none!important}', $tokens);
    }

    private function bitcoin(): Coin
    {
        return Coin::query()->create([
            'slug' => 'bitcoin',
            'symbol' => 'BTC',
            'name' => 'Bitcoin',
            'rank' => 1,
            'price' => 77304,
            'percent_change_1h' => 0.08,
            'percent_change_24h' => 0.06,
            'percent_change_7d' => -3.28,
            'market_cap' => 1_550_000_000_000,
            'volume_24h' => 14_870_000_000,
            'circulating_supply' => 20_083_406,
            'sparkline_7d' => [100, 101, 99, 98],
        ]);
    }
}
