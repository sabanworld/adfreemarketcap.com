<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Sibling app price feed
    |--------------------------------------------------------------------------
    |
    | Server-to-server JSON for solochance.io (and future siblings). Reads the
    | local coins table so siblings do not burn a second CoinGecko quota.
    | Disabled until SIBLING_API_TOKEN is set.
    |
    */

    'enabled' => filled(env('SIBLING_API_TOKEN')),

    'token' => env('SIBLING_API_TOKEN'),

    'route' => env('SIBLING_API_ROUTE', 'sibling/prices'),

    /*
    | Symbols a sibling may request. Empty means any symbol present in coins.
    | Keep this tight in production so the feed stays a calculator feed, not
    | a public dump of the whole ranking.
    */
    'allowed_symbols' => array_values(array_filter(array_map(
        static fn (string $symbol): string => strtoupper(trim($symbol)),
        explode(',', (string) env('SIBLING_API_ALLOWED_SYMBOLS', 'BTC,BCH,BSV,XEC,LTC,DOGE,XMR')),
    ))),

    'throttle' => env('SIBLING_API_THROTTLE', '60,1'),

];
