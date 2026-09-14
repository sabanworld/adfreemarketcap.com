<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Visitor statistics (Simple Analytics)
    |--------------------------------------------------------------------------
    |
    | The public layout loads a cookieless counter from Simple Analytics B.V.
    | (Amsterdam). It runs for every visitor because it writes nothing to the
    | device, and it is disclosed in resources/views/legal/privacy-policy and
    | cookie-policy. Changing anything here is a policy change: see
    | docs/privacy-and-legal.md before touching it.
    |
    | The only other third-party request is the Google Ads tag in
    | config/google-ads.php, which is off by default and never loads before the
    | visitor accepts it in the cookie bar.
    |
    | The counter is off unless ANALYTICS_ENABLED is true, so local runs and the
    | test suite do not send page views and do not reach the network.
    |
    */

    'enabled' => (bool) env('ANALYTICS_ENABLED', env('APP_ENV') === 'production'),

    'script_url' => env('ANALYTICS_SCRIPT_URL', 'https://scripts.simpleanalyticscdn.com/latest.js'),

    'noscript_url' => env('ANALYTICS_NOSCRIPT_URL', 'https://queue.simpleanalyticscdn.com/noscript.gif'),

    /*
    | Simple Analytics drops visits from browsers that send Do Not Track. Keep
    | this false so that setting keeps working; the privacy policy says it does.
    */
    'collect_dnt' => (bool) env('ANALYTICS_COLLECT_DNT', false),

];
