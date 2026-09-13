<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Seo\SeoService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

final class SitemapController extends Controller
{
    public const CACHE_KEY = 'seo.sitemap.xml';

    public function __invoke(SeoService $seo): Response
    {
        $ttl = max(60, (int) config('seo.sitemap_cache_seconds', 600));

        $xml = Cache::remember(self::CACHE_KEY, $ttl, function () use ($seo): string {
            return view('seo.sitemap', [
                'entries' => $seo->sitemapEntries(),
            ])->render();
        });

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age=' . min(300, $ttl),
        ]);
    }
}
