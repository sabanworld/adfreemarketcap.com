<?php

declare(strict_types=1);

namespace Tests\Feature;

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
}
