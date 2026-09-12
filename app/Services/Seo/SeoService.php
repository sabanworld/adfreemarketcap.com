<?php

declare(strict_types=1);

namespace App\Services\Seo;

use App\Livewire\LegalPage;
use App\Models\Coin;
use App\Services\MarketData\MarketNumberFormatter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final class SeoService
{
    public function forHome(): PageSeo
    {
        $siteName = (string) config('app.name', 'adfreemarketcap.com');
        $canonical = route('home');
        $description = (string) config('seo.default_description');

        return new PageSeo(
            title: __('seo.home_title', ['site' => $siteName]),
            description: $description,
            canonical: $canonical,
            image: $this->defaultImage(),
            ogType: 'website',
            jsonLd: [
                [
                    '@context' => 'https://schema.org',
                    '@type' => 'WebSite',
                    'name' => $siteName,
                    'url' => $canonical,
                    'description' => $description,
                    'potentialAction' => [
                        '@type' => 'SearchAction',
                        'target' => [
                            '@type' => 'EntryPoint',
                            'urlTemplate' => $canonical . '?search={search_term_string}',
                        ],
                        'query-input' => 'required name=search_term_string',
                    ],
                ],
            ],
        );
    }

    public function forCoin(Coin $coin): PageSeo
    {
        $siteName = (string) config('app.name', 'adfreemarketcap.com');
        $canonical = route('coins.show', $coin);
        // Meta copy is crawled and shared, so it stays USD regardless of the visitor's display currency.
        $price = MarketNumberFormatter::moneyUsd($coin->price !== null ? (float) $coin->price : null, 8);
        $marketCap = MarketNumberFormatter::moneyUsd($coin->market_cap !== null ? (float) $coin->market_cap : null);

        $description = $this->truncate(
            __('seo.coin_description', [
                'name' => $coin->name,
                'symbol' => strtoupper((string) $coin->symbol),
                'price' => $price,
                'market_cap' => $marketCap,
                'rank' => $coin->rank ?? '?',
            ]),
        );

        $image = filled($coin->image_url) ? (string) $coin->image_url : $this->defaultImage();

        return new PageSeo(
            title: __('seo.coin_title', [
                'name' => $coin->name,
                'symbol' => strtoupper((string) $coin->symbol),
                'site' => $siteName,
            ]),
            description: $description,
            canonical: $canonical,
            image: $image,
            ogType: 'website',
            jsonLd: [
                [
                    '@context' => 'https://schema.org',
                    '@type' => 'WebPage',
                    'name' => $coin->name,
                    'url' => $canonical,
                    'description' => $description,
                    'isPartOf' => [
                        '@type' => 'WebSite',
                        'name' => $siteName,
                        'url' => route('home'),
                    ],
                    'about' => [
                        '@type' => 'Thing',
                        'name' => $coin->name,
                        'alternateName' => strtoupper((string) $coin->symbol),
                        'image' => $image,
                    ],
                ],
                [
                    '@context' => 'https://schema.org',
                    '@type' => 'BreadcrumbList',
                    'itemListElement' => [
                        [
                            '@type' => 'ListItem',
                            'position' => 1,
                            'name' => __('seo.breadcrumb_markets'),
                            'item' => route('home'),
                        ],
                        [
                            '@type' => 'ListItem',
                            'position' => 2,
                            'name' => $coin->name,
                            'item' => $canonical,
                        ],
                    ],
                ],
            ],
        );
    }

    public function forStaticPage(
        string $title,
        string $description,
        string $canonical,
        string $robots = 'index,follow',
    ): PageSeo {
        return new PageSeo(
            title: $title,
            description: $this->truncate($description),
            canonical: $canonical,
            image: $this->defaultImage(),
            ogType: 'website',
            jsonLd: [
                [
                    '@context' => 'https://schema.org',
                    '@type' => 'WebPage',
                    'name' => $title,
                    'url' => $canonical,
                    'description' => $this->truncate($description),
                ],
            ],
            robots: $robots,
        );
    }

    /**
     * @return Collection<int, array{loc: string, lastmod: string|null, changefreq: string, priority: string}>
     */
    public function sitemapEntries(): Collection
    {
        $entries = collect([
            [
                'loc' => route('home'),
                'lastmod' => now()->toAtomString(),
                'changefreq' => 'hourly',
                'priority' => '1.0',
            ],
            [
                'loc' => route('dexscan'),
                'lastmod' => now()->toAtomString(),
                'changefreq' => 'hourly',
                'priority' => '0.9',
            ],
        ]);

        foreach (array_keys(LegalPage::PAGES) as $legalPage) {
            $entries->push([
                'loc' => route('legal.show', $legalPage),
                'lastmod' => now()->toAtomString(),
                'changefreq' => 'monthly',
                'priority' => '0.3',
            ]);
        }

        $coins = Coin::query()
            ->whereNotNull('slug')
            ->orderBy('rank')
            ->orderBy('id')
            ->get(['slug', 'market_synced_at', 'detail_synced_at', 'updated_at']);

        foreach ($coins as $coin) {
            $entries->push([
                'loc' => route('coins.show', $coin),
                'lastmod' => $this->lastmodForCoin($coin)?->toAtomString(),
                'changefreq' => 'hourly',
                'priority' => '0.8',
            ]);
        }

        return $entries;
    }

    public function robotsTxt(): string
    {
        $lines = [
            'User-agent: *',
            'Allow: /',
        ];

        foreach ((array) config('seo.robots.disallow', []) as $path) {
            if (! is_string($path) || $path === '') {
                continue;
            }

            $lines[] = 'Disallow: ' . $path;
        }

        $lines[] = '';
        $lines[] = 'Sitemap: ' . route('sitemap');

        return implode("\n", $lines) . "\n";
    }

    private function defaultImage(): ?string
    {
        $image = config('seo.og_image');

        return is_string($image) && filled($image) ? $image : null;
    }

    private function lastmodForCoin(Coin $coin): ?Carbon
    {
        $candidates = array_filter([
            $coin->market_synced_at,
            $coin->detail_synced_at,
            $coin->updated_at,
        ]);

        if ($candidates === []) {
            return null;
        }

        return collect($candidates)->max();
    }

    private function plainText(?string $value): string
    {
        if (! is_string($value) || $value === '') {
            return '';
        }

        return trim(preg_replace('/\s+/u', ' ', strip_tags($value)) ?? '');
    }

    private function truncate(string $value, int $limit = 160): string
    {
        return Str::limit($this->plainText($value), $limit, '…');
    }
}
