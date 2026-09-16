<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalyticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_nothing_is_rendered_while_the_counter_is_off(): void
    {
        config(['analytics.enabled' => false]);

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertDontSee('afmc-analytics', false);
        $response->assertDontSee('plausible', false);
    }

    public function test_counter_renders_its_settings_when_enabled(): void
    {
        config(['analytics.enabled' => true]);

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee('<script type="application/json" id="afmc-analytics">', false);
        $response->assertSee('"domain":"' . config('analytics.domain') . '"', false);
        $response->assertSee('"endpoint":"https:\/\/plausible.io\/api\/event"', false);
    }

    public function test_the_tracker_is_served_from_our_own_domain(): void
    {
        config(['analytics.enabled' => true]);

        // The privacy and cookie policies both say the browser fetches no file
        // from Plausible, which only holds while the tracker ships in our own
        // bundle instead of a script tag pointing at plausible.io.
        $this->get(route('home'))->assertDontSee('src="https://plausible.io', false);
    }

    public function test_do_not_track_is_ignored_unless_configuration_says_otherwise(): void
    {
        config(['analytics.enabled' => true]);

        // Default is to count even when the browser sends Do Not Track or Global
        // Privacy Control. The privacy policy says so. Flipping the flag is how
        // you honour those signals again.
        $this->assertTrue(config('analytics.collect_dnt'));
        $this->get(route('home'))->assertSee('"collectDnt":true', false);

        config(['analytics.collect_dnt' => false]);

        $this->get(route('home'))->assertSee('"collectDnt":false', false);
    }

    public function test_what_is_captured_matches_what_the_privacy_policy_says(): void
    {
        // Each of these is named in the Visitor statistics section of the privacy
        // policy. Turning one off means rewriting that paragraph in the same
        // change, so this test is the reminder.
        $this->assertTrue(config('analytics.capture.outbound_links'));
        $this->assertTrue(config('analytics.capture.file_downloads'));
        $this->assertTrue(config('analytics.capture.form_submissions'));

        config(['analytics.enabled' => true]);

        $response = $this->get(route('home'));

        $response->assertSee('"outboundLinks":true', false);
        $response->assertSee('"fileDownloads":true', false);
        $response->assertSee('"formSubmissions":true', false);
    }

    public function test_the_counter_stays_off_in_the_test_environment_by_default(): void
    {
        $this->assertFalse(config('analytics.enabled'));
    }
}
