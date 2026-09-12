<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Coin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SeoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Opening a coin page dispatches sync jobs for anything stale, and the
        // testing queue runs inline, so keep those jobs off the provider.
        Queue::fake();
    }

    public function test_robots_txt_disallows_admin_and_points_at_sitemap(): void
    {
        $response = $this->get('/robots.txt');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
        $response->assertSee('User-agent: *', false);
        $response->assertSee('Disallow: /admin', false);
        $response->assertSee('Disallow: /horizon', false);
        $response->assertSee('Disallow: /livewire', false);
        $response->assertSee('Disallow: /login', false);
        $response->assertSee('Disallow: /register', false);
        $response->assertSee('Disallow: /watchlist', false);
        $response->assertSee('Disallow: /altcha', false);
        $response->assertSee('Sitemap: ' . route('sitemap'), false);
    }

    public function test_sitemap_includes_home_and_coin_urls(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-11 12:00:00'));

        Coin::query()->create([
            'slug' => 'bitcoin',
            'symbol' => 'BTC',
            'name' => 'Bitcoin',
            'rank' => 1,
            'price' => 50000,
            'market_synced_at' => now()->subHour(),
        ]);

        $response = $this->get('/sitemap.xml');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
        $response->assertSee('<loc>' . route('home') . '</loc>', false);
        $response->assertSee('<loc>' . route('coins.show', 'bitcoin') . '</loc>', false);
        $response->assertSee('<changefreq>hourly</changefreq>', false);
    }

    public function test_home_page_exposes_canonical_and_meta_description(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('<link rel="canonical" href="' . route('home') . '">', false);
        $response->assertSee('<meta name="description"', false);
        $response->assertSee('"@type":"WebSite"', false);
        $response->assertSee('<meta property="og:title"', false);
    }

    public function test_coin_page_exposes_unique_title_canonical_and_json_ld(): void
    {
        Coin::query()->create([
            'slug' => 'ethereum',
            'symbol' => 'ETH',
            'name' => 'Ethereum',
            'rank' => 2,
            'price' => 3000,
            'market_cap' => 350_000_000_000,
            'image_url' => 'https://example.test/eth.png',
            'detail_synced_at' => now(),
        ]);

        $response = $this->get('/coins/ethereum');

        $response->assertOk();
        $response->assertSee('Ethereum (ETH) Price and Market Cap', false);
        $response->assertSee('<link rel="canonical" href="' . route('coins.show', 'ethereum') . '">', false);
        $response->assertSee('https://example.test/eth.png', false);
        $response->assertSee('"@type":"BreadcrumbList"', false);
        $response->assertSee('"@type":"WebPage"', false);
    }

    public function test_filtered_home_url_still_canonicalizes_to_clean_home(): void
    {
        $response = $this->get('/?search=btc&sort=price&direction=desc');

        $response->assertOk();
        $response->assertSee('<link rel="canonical" href="' . route('home') . '">', false);
        $response->assertDontSee('<link rel="canonical" href="' . url('/?search=btc'), false);
    }
}
