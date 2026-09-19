<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Coin;
use App\Support\Icons;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class DesignSystemShellTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_layout_exposes_skip_link_main_landmark_and_mobile_shell(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee('Skip to main content', false);
        $response->assertSee('href="#afmc-main"', false);
        $response->assertSee('id="afmc-main"', false);
        $response->assertSee('data-afmc-tabbar', false);
        $response->assertSee('id="afmc-nav-drawer"', false);
        $response->assertSee('data-afmc-searchbtn', false);
        $response->assertSee(__('More'), false);
        $response->assertSee(__('Cryptocurrency prices by market cap'), false);
    }

    /**
     * The server HTML always ships data-theme="light". Without re-applying the saved theme
     * during wire:navigate's onSwap, dark mode flashes white between pages.
     */
    public function test_public_layout_reapplies_theme_on_livewire_navigate(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee("localStorage.getItem('afmc-theme') === 'dark'", false);
        $response->assertSee("document.addEventListener('livewire:navigating'", false);
        $response->assertSee('event.detail?.onSwap?.(apply)', false);
    }

    /**
     * Zero is not a direction. A change that rounds to 0.00% gets neutral ink and no caret,
     * because green would read as a gain, and 0.00% is the normal state for a stablecoin.
     */
    public function test_a_change_that_rounds_to_zero_carries_no_direction(): void
    {
        $flat = Blade::render('<x-afmc.price-change :value="0.001" />');

        $this->assertStringContainsString('afmc-change--flat', $flat);
        $this->assertStringNotContainsString('afmc-change--up', $flat);
        $this->assertStringNotContainsString('arrow_drop', $flat);
        $this->assertStringNotContainsString(Icons::character('arrow_drop_up'), $flat);

        $up = Blade::render('<x-afmc.price-change :value="1.4" />');
        $this->assertStringContainsString('afmc-change--up', $up);
        $this->assertStringContainsString(Icons::character('arrow_drop_up'), $up);

        $down = Blade::render('<x-afmc.price-change :value="-1.4" />');
        $this->assertStringContainsString('afmc-change--down', $down);
        $this->assertStringContainsString(Icons::character('arrow_drop_down'), $down);

        $missing = Blade::render('<x-afmc.price-change :value="null" />');
        $this->assertStringContainsString('afmc-change--empty', $missing);
    }

    /**
     * Some rows drop the caret to keep a line readable (the 1h column, the phone rows). The
     * sign takes over there, because green and red on their own leave a reader who cannot tell
     * them apart with no direction at all: WCAG 1.4.1.
     */
    public function test_a_change_without_a_caret_prints_its_sign(): void
    {
        $up = Blade::render('<x-afmc.price-change :value="1.4" :show-icon="false" />');
        $this->assertStringContainsString('+1.40%', $up);
        $this->assertStringNotContainsString(Icons::character('arrow_drop_up'), $up);

        $down = Blade::render('<x-afmc.price-change :value="-2.41" :show-icon="false" />');
        $this->assertStringContainsString('-2.41%', $down);

        // Flat has no direction to sign, and "+0.00%" would read as a gain.
        $flat = Blade::render('<x-afmc.price-change :value="0.001" :show-icon="false" />');
        $this->assertStringContainsString('0.00%', $flat);
        $this->assertStringNotContainsString('+0.00%', $flat);

        // With the caret, the caret is the marker and a sign as well would be noise.
        $carried = Blade::render('<x-afmc.price-change :value="1.4" />');
        $this->assertStringNotContainsString('+1.40%', $carried);
    }

    /**
     * A chart never contradicts its number, so the sparkline colours itself from the series it
     * draws and a flat line stays neutral.
     */
    public function test_a_sparkline_takes_its_direction_from_its_own_series(): void
    {
        $rising = Blade::render('<x-afmc.sparkline :data="[10, 11, 12]" />');
        $this->assertStringContainsString('var(--chart-up)', $rising);

        $falling = Blade::render('<x-afmc.sparkline :data="[12, 11, 10]" />');
        $this->assertStringContainsString('var(--chart-down)', $falling);

        $flat = Blade::render('<x-afmc.sparkline :data="[1.0, 1.0001, 1.0]" />');
        $this->assertStringContainsString('var(--chart-axis)', $flat);
        $this->assertStringNotContainsString('var(--chart-up)', $flat);

        // The line is stretched to the cell, so the stroke has to opt out of that scaling.
        $this->assertStringContainsString('vector-effect="non-scaling-stroke"', $rising);
    }

    /**
     * A drawer that Escape closes but that leaves focus at the top of the document is a defect.
     * x-trap holds focus inside it and hands it back to the button that opened it.
     */
    public function test_the_nav_drawer_traps_focus_while_it_is_open(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee('x-trap.noscroll="moreOpen"', false);
    }

    /**
     * A label on an --ink-900 chip reads in both themes. --ink-900 is cream in dark mode, so a
     * --text-inverse pinned to it would paint the text in its own background and the current
     * page number would vanish. The kit ships that pairing; our copy does not.
     */
    public function test_inverse_text_never_resolves_to_its_own_background(): void
    {
        $colors = File::get(resource_path('css/design-system/colors.css'));

        $this->assertSame(
            2,
            substr_count($colors, '--text-inverse:var(--paper-0)'),
            'Both the light and dark blocks must point --text-inverse at --paper-0.',
        );
        $this->assertStringNotContainsString('--text-inverse:var(--ink-900)', $colors);
    }

    /**
     * Every pager cell carries its own class. A bare `span` selector also matched the chevron's
     * icon span, and its font shorthand swapped the icon font for the mono face, so both arrows
     * rendered as a missing glyph.
     */
    public function test_the_pager_arrows_keep_the_icon_font(): void
    {
        for ($rank = 1; $rank <= 60; $rank++) {
            Coin::query()->create([
                'slug' => 'coin-' . $rank,
                'symbol' => 'C' . $rank,
                'name' => 'Coin ' . $rank,
                'rank' => $rank,
                'price' => 1,
                'market_cap' => 1_000_000 - $rank,
            ]);
        }

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee('afmc-pagination__link', false);
        $response->assertSee(Icons::character('chevron_right'), false);
        $response->assertSee('aria-label="' . __('Next page') . '"', false);
        // An arrow with nowhere to go is a real disabled button, so it keeps its label, its
        // place in the row, and the 44px target the coarse-pointer rule gives every control.
        $response->assertSee('disabled aria-label="' . __('Previous page') . '"', false);

        // Below 560px the numbered cells give way to a position readout, so both renderings
        // ship and CSS decides which one a viewport gets.
        $response->assertSee('afmc-pagination__link--page', false);
        $response->assertSee('afmc-pagination__position', false);
        $response->assertSee('1 / 2', false);
    }
}
