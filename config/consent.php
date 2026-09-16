<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Consent for non-essential storage
    |--------------------------------------------------------------------------
    |
    | Article 5(3) of the ePrivacy Directive, implemented by article 11.7a of
    | the Dutch Telecommunications Act, asks for opt-in before anything that is
    | not strictly necessary is written to a visitor's device. The cookie bar
    | records that answer here, and nothing in config/google-ads.php or
    | config/exchange-widget.php loads until the answer is yes. Read
    | docs/privacy-and-legal.md before changing any of this, because the cookie
    | policy names the key below.
    |
    */

    'storage_key' => 'afmc-consent',

    /*
    | Consent is specific to the purposes it was given for, so a stored answer
    | stops counting once the purposes change. Bump this in the same release as
    | the policy text and the bar asks everyone again.
    */

    'version' => (string) env('CONSENT_VERSION', '2026-09-16'),

];
