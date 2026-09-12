<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Require authenticator-app MFA
    |--------------------------------------------------------------------------
    |
    | When true, a signed-in admin must enable an authenticator app before
    | using the panel. Defaults to on in production. Set ADMIN_MFA_REQUIRED
    | to force the behaviour in any environment (for example true on staging).
    |
    */

    'mfa_required' => filter_var(
        env('ADMIN_MFA_REQUIRED', env('APP_ENV') === 'production'),
        FILTER_VALIDATE_BOOL,
    ),

];
