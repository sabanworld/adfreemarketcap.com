<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Visitor statistics (Plausible)
    |--------------------------------------------------------------------------
    |
    | The public layout counts page views with Plausible Insights OÜ (Tartu,
    | Estonia), which processes and stores the figures in Germany. The tracker
    | comes from the @plausible-analytics/tracker package through Vite, so the
    | browser fetches no file from Plausible and the measurement request is the
    | only thing that leaves it. Nothing is written to the device, which is why
    | it runs for everyone, and it is disclosed in resources/views/legal/
    | privacy-policy and cookie-policy. Changing anything here is a policy
    | change: see docs/privacy-and-legal.md before touching it.
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

    /*
    | The site as it is registered in the Plausible dashboard. Every event
    | carries it, so it decides which dashboard the figures land in rather than
    | being read from the address the visitor is on.
    */

    'domain' => env('ANALYTICS_DOMAIN', 'adfreemarketcap.com'),

    'endpoint' => env('ANALYTICS_ENDPOINT', 'https://plausible.io/api/event'),

    /*
    | When true (the default), the counter runs even if the browser sends Do Not
    | Track or Global Privacy Control. Plausible itself never checked those
    | signals; we used to skip the tracker ourselves. The privacy policy names
    | the remaining ways out (plausible_ignore and blocking plausible.io), so
    | leave this true unless you deliberately want to honour the signals again.
    */

    'collect_dnt' => (bool) env('ANALYTICS_COLLECT_DNT', true),

    /*
    | What is counted besides page views. The privacy policy names each of these
    | one by one, so changing a line here means changing that copy in the same
    | commit; tests/Feature/AnalyticsTest.php fails until you do. Scroll depth
    | and time on the page come with the tracker and cannot be switched off.
    */

    'capture' => [
        'outbound_links' => (bool) env('ANALYTICS_CAPTURE_OUTBOUND_LINKS', true),
        'file_downloads' => (bool) env('ANALYTICS_CAPTURE_FILE_DOWNLOADS', true),
        'form_submissions' => (bool) env('ANALYTICS_CAPTURE_FORM_SUBMISSIONS', true),
    ],

];
