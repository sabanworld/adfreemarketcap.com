<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Default meta description
    |--------------------------------------------------------------------------
    |
    | Used on the homepage and as a fallback when a page has no better copy.
    |
    */

    'default_description' => env(
        'SEO_DEFAULT_DESCRIPTION',
        'Ad-free cryptocurrency market rankings, prices, market caps, and coin details. No ads, no clutter.',
    ),

    /*
    |--------------------------------------------------------------------------
    | Open Graph / social defaults
    |--------------------------------------------------------------------------
    |
    | Absolute URL preferred for og:image. Leave null until a brand asset exists.
    |
    */

    'og_image' => env('SEO_OG_IMAGE'),

    'twitter_site' => env('SEO_TWITTER_SITE'),

    /*
    |--------------------------------------------------------------------------
    | Robots
    |--------------------------------------------------------------------------
    */

    'robots' => [
        'disallow' => [
            '/admin',
            '/horizon',
            '/livewire',
            '/login',
            '/register',
            '/watchlist',
            '/logout',
            '/altcha',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Sitemap cache
    |--------------------------------------------------------------------------
    |
    | Full sitemap XML is cached and invalidated after a markets sync.
    |
    */

    'sitemap_cache_seconds' => (int) env('SEO_SITEMAP_CACHE_SECONDS', 600),

];
