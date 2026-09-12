<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Public form rate limits
    |--------------------------------------------------------------------------
    |
    | Every public mutating form / Livewire form action must declare a limiter.
    | Keys are per client IP (real IP behind Cloudflare when proxies are trusted).
    |
    */

    'login' => [
        'max_attempts' => (int) env('FORM_RATE_LIMIT_LOGIN', 5),
        'decay_seconds' => (int) env('FORM_RATE_LIMIT_LOGIN_DECAY', 60),
    ],

    'register' => [
        'max_attempts' => (int) env('FORM_RATE_LIMIT_REGISTER', 3),
        'decay_seconds' => (int) env('FORM_RATE_LIMIT_REGISTER_DECAY', 60),
    ],

    'logout' => [
        'max_attempts' => (int) env('FORM_RATE_LIMIT_LOGOUT', 10),
        'decay_seconds' => (int) env('FORM_RATE_LIMIT_LOGOUT_DECAY', 60),
    ],

    'watch_toggle' => [
        'max_attempts' => (int) env('FORM_RATE_LIMIT_WATCH_TOGGLE', 30),
        'decay_seconds' => (int) env('FORM_RATE_LIMIT_WATCH_TOGGLE_DECAY', 60),
    ],

    'currency' => [
        'max_attempts' => (int) env('FORM_RATE_LIMIT_CURRENCY', 30),
        'decay_seconds' => (int) env('FORM_RATE_LIMIT_CURRENCY_DECAY', 60),
    ],

    'altcha_challenge' => [
        'max_attempts' => (int) env('FORM_RATE_LIMIT_ALTCHA', 60),
        'decay_seconds' => (int) env('FORM_RATE_LIMIT_ALTCHA_DECAY', 60),
    ],

];
