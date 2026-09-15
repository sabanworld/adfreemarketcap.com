<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Coin;
use App\Services\Seo\SeoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeoServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_for_coin_builds_title_and_description_with_price(): void
    {
        $coin = Coin::query()->create([
            'slug' => 'bitcoin',
            'symbol' => 'btc',
            'name' => 'Bitcoin',
            'rank' => 1,
            'price' => 50000,
            'market_cap' => 1_000_000_000_000,
        ]);

        $seo = app(SeoService::class)->forCoin($coin);

        $this->assertStringContainsString('Bitcoin (BTC)', $seo->title);
        $this->assertStringContainsString('$50,000', $seo->description);
        $this->assertSame(route('coins.show', $coin), $seo->canonical);
        $this->assertSame('index,follow', $seo->robots);
        $this->assertNotEmpty($seo->jsonLd);
    }

    public function test_robots_txt_ends_with_newline_and_sitemap(): void
    {
        $body = app(SeoService::class)->robotsTxt();

        $this->assertStringEndsWith("\n", $body);
        $this->assertStringContainsString('Sitemap: ' . route('sitemap'), $body);
    }

    public function test_for_error_page_is_noindex_without_json_ld(): void
    {
        $seo = app(SeoService::class)->forErrorPage(404, 'Page not found', 'That address is not on this site.');

        $this->assertStringContainsString('Page not found', $seo->title);
        $this->assertStringContainsString('That address is not on this site.', $seo->description);
        $this->assertSame('noindex,nofollow', $seo->robots);
        $this->assertSame([], $seo->jsonLd);
    }
}
