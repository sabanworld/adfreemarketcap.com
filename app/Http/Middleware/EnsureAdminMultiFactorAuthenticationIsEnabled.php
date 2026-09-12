<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Filament\Auth\MultiFactor\Http\Middleware\EnsureMultiFactorAuthenticationIsEnabled;
use Illuminate\Http\Request;

/**
 * Filament's MFA gate, only when config('admin.mfa_required') is true.
 *
 * The panel always registers the set-up route and attaches this middleware so
 * production deploys and tests can flip the config without re-booting routes.
 * Outside production the default is off, so local admins can still sign in
 * with a password alone and opt into MFA from the profile page.
 */
class EnsureAdminMultiFactorAuthenticationIsEnabled
{
    public function __construct(
        private EnsureMultiFactorAuthenticationIsEnabled $ensureMultiFactorAuthenticationIsEnabled,
    ) {}

    public function handle(Request $request, Closure $next): mixed
    {
        if (! config('admin.mfa_required')) {
            return $next($request);
        }

        return $this->ensureMultiFactorAuthenticationIsEnabled->handle($request, $next);
    }
}
