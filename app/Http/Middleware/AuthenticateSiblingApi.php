<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateSiblingApi
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(config('sibling_api.enabled'), 404);

        $expected = (string) config('sibling_api.token');
        $provided = (string) ($request->bearerToken() ?? $request->header('X-Sibling-Token', ''));

        abort_unless(
            $expected !== '' && hash_equals($expected, $provided),
            401,
            'Invalid sibling API token.',
        );

        return $next($request);
    }
}
