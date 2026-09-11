<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Primary and failover providers
    |--------------------------------------------------------------------------
    |
    | Primary owns live rankings written to the coins table. Failover is used
    | only when the primary errors or is rate-limited.
    |
    */

    'primary' => env('MARKETDATA_PRIMARY', 'coingecko'),

    'failover' => env('MARKETDATA_FAILOVER', 'coinpaprika'),

    'coingecko' => [
        'base_url' => env('COINGECKO_BASE_URL', 'https://api.coingecko.com/api/v3'),
        'api_key' => env('COINGECKO_API_KEY'),
        'api_key_header' => env('COINGECKO_API_KEY_HEADER', 'x-cg-demo-api-key'),
    ],

    'coinpaprika' => [
        'base_url' => env('COINPAPRIKA_BASE_URL', 'https://api.coinpaprika.com/v1'),
    ],

    'coinmarketcap' => [
        'base_url' => env('COINMARKETCAP_BASE_URL', 'https://pro-api.coinmarketcap.com'),
        'api_key' => env('COINMARKETCAP_API_KEY'),
    ],

    'sync' => [
        'markets_pages' => (int) env('MARKETDATA_MARKETS_PAGES', 2),
        'per_page' => (int) env('MARKETDATA_PER_PAGE', 100),
        'markets_interval_minutes' => (int) env('MARKETDATA_MARKETS_INTERVAL', 5),
        'coin_detail_stale_hours' => (int) env('MARKETDATA_DETAIL_STALE_HOURS', 6),
        'divergence_threshold_percent' => (float) env('MARKETDATA_DIVERGENCE_THRESHOLD', 5),
    ],

];
