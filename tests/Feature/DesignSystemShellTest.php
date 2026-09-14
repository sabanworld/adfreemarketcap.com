<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\Icons;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class DesignSystemShellTest extends TestCase
{
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
}
