<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | ALTCHA (self-hosted proof-of-work)
    |--------------------------------------------------------------------------
    |
    | Complements Cloudflare (reverse proxy / WAF / bot fight). Do not send
    | challenge traffic to third-party captcha SaaS — HMAC secrets stay local.
    |
    */

    'enabled' => (bool) env('ALTCHA_ENABLED', true),

    'hmac_signature_secret' => env('ALTCHA_HMAC_SECRET'),

    'hmac_key_signature_secret' => env('ALTCHA_HMAC_KEY_SECRET'),

    'cost' => (int) env('ALTCHA_COST', 10000),

    'expires_seconds' => (int) env('ALTCHA_EXPIRES', 300),

    /*
    | When set (and the app is running unit tests), this exact payload string
    | is accepted without solving a challenge. Never enable outside testing.
    */
    'testing_bypass' => env('ALTCHA_TESTING_BYPASS'),

    'route' => [
        'path' => 'altcha',
        'name' => 'altcha.challenge',
    ],

];
