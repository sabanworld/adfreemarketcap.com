<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalyticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_third_party_request_is_rendered_while_the_counter_is_off(): void
    {
        config(['analytics.enabled' => false]);

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertDontSee('simpleanalyticscdn.com', false);
    }

    public function test_counter_renders_the_script_and_the_noscript_pixel_when_enabled(): void
    {
        config(['analytics.enabled' => true]);

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee('<script async src="https://scripts.simpleanalyticscdn.com/latest.js"', false);
        $response->assertSee('<noscript><img src="https://queue.simpleanalyticscdn.com/noscript.gif" alt="" referrerpolicy="no-referrer-when-downgrade">', false);
    }

    public function test_do_not_track_is_honoured_unless_configuration_says_otherwise(): void
    {
        config(['analytics.enabled' => true, 'analytics.collect_dnt' => false]);

        // The privacy policy promises that a Do Not Track browser stays out of the
        // count, which only holds while the opt-out attribute is absent.
        $this->get(route('home'))->assertDontSee('data-collect-dnt', false);

        config(['analytics.collect_dnt' => true]);

        $this->get(route('home'))->assertSee('data-collect-dnt="true"', false);
    }

    public function test_the_counter_stays_off_in_the_test_environment_by_default(): void
    {
        $this->assertFalse(config('analytics.enabled'));
    }
}
