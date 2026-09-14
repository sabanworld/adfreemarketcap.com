<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WhyAdFreePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_page_is_reachable_and_states_the_pledge(): void
    {
        $response = $this->get(route('why-ad-free'));

        $response->assertOk();
        $response->assertSee(__('Why ad-free'), false);
        $response->assertSee(__('No ads. No paid rankings. No sponsored listings.'), false);
        $response->assertSee(__('What we will not do'), false);
    }

    public function test_the_page_has_its_own_title_description_and_clean_canonical(): void
    {
        $response = $this->get(route('why-ad-free'));

        $response->assertSee(__('seo.why_ad_free_title', ['site' => config('app.name')]), false);
        $response->assertSee('<link rel="canonical" href="' . route('why-ad-free') . '">', false);
        $response->assertSee('<meta name="robots" content="index,follow">', false);
        $response->assertSee('"@type":"WebPage"', false);
    }

    public function test_the_sitemap_lists_the_page(): void
    {
        $response = $this->get('/sitemap.xml');

        $response->assertOk();
        $response->assertSee('<loc>' . route('why-ad-free') . '</loc>', false);
    }

    public function test_the_footer_and_drawer_link_to_the_page_instead_of_the_home_anchor(): void
    {
        $response = $this->get(route('why-ad-free'));

        $response->assertOk();
        $response->assertSee('href="' . route('why-ad-free') . '"', false);
        $response->assertDontSee('href="' . route('home') . '#pledge"', false);
    }

    public function test_the_home_page_shows_the_same_pledge_band_and_points_here(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee(__('No ads. No paid rankings. No sponsored listings.'), false);
        $response->assertSee('href="' . route('why-ad-free') . '"', false);
        $response->assertSee(__('Why we build it this way'), false);
    }
}
