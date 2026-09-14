<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Google Ads conversion measurement
    |--------------------------------------------------------------------------
    |
    | The only tag on this site that needs consent. It loads gtag.js from
    | googletagmanager.com and writes advertising cookies (_gcl_*) on our own
    | domain, so it stays off until the visitor accepts in the cookie bar.
    |
    | Turning this on is a policy change, not a configuration change. The
    | privacy policy, the cookie policy, the Why ad-free page, and the cookie
    | bar all render a second set of sentences when it is true, and they have to
    | keep matching what the tag does. See docs/privacy-and-legal.md.
    |
    | Both values must be present before anything renders, so a half-configured
    | environment cannot fire the tag against a missing or wrong account.
    |
    */

    'enabled' => (bool) env('GOOGLE_ADS_ENABLED', false),

    'conversion_id' => env('GOOGLE_ADS_CONVERSION_ID'),

    'script_url' => env('GOOGLE_ADS_SCRIPT_URL', 'https://www.googletagmanager.com/gtag/js'),

    /*
    |--------------------------------------------------------------------------
    | Conversion actions
    |--------------------------------------------------------------------------
    |
    | The label Google shows next to a conversion action, without the account
    | prefix: paste "AbC-D_efG12h" rather than "AW-123/AbC-D_efG12h", because
    | conversion_id above is prepended for you.
    |
    | A label left empty switches that conversion off. Nothing is reported for
    | it, which is the right behaviour while the action is still being set up in
    | Google Ads.
    |
    */

    'conversions' => [
        'registration' => env('GOOGLE_ADS_CONVERSION_REGISTRATION'),
        'watchlist' => env('GOOGLE_ADS_CONVERSION_WATCHLIST'),
    ],

];
