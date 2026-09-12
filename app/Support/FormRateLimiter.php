<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

final class FormRateLimiter
{
    /**
     * @param  non-empty-string  $form
     */
    public static function ensureIsNotRateLimited(string $form, ?string $ip = null, string $errorKey = 'email'): void
    {
        $config = (array) config("forms.{$form}", []);
        $maxAttempts = max(1, (int) ($config['max_attempts'] ?? 5));
        $key = self::key($form, $ip);

        if (! RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            return;
        }

        $seconds = RateLimiter::availableIn($key);

        throw ValidationException::withMessages([
            $errorKey => __('Too many attempts. Please try again in :seconds seconds.', [
                'seconds' => $seconds,
            ]),
        ])->status(429);
    }

    /**
     * @param  non-empty-string  $form
     */
    public static function ensureOrAbort(string $form, ?string $ip = null): void
    {
        $config = (array) config("forms.{$form}", []);
        $maxAttempts = max(1, (int) ($config['max_attempts'] ?? 5));
        $key = self::key($form, $ip);

        if (! RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            return;
        }

        throw new TooManyRequestsHttpException(
            RateLimiter::availableIn($key),
            __('Too many attempts. Please try again later.'),
        );
    }

    /**
     * @param  non-empty-string  $form
     */
    public static function hit(string $form, ?string $ip = null): void
    {
        $config = (array) config("forms.{$form}", []);
        $decaySeconds = max(1, (int) ($config['decay_seconds'] ?? 60));

        RateLimiter::hit(self::key($form, $ip), $decaySeconds);
    }

    /**
     * @param  non-empty-string  $form
     */
    public static function clear(string $form, ?string $ip = null): void
    {
        RateLimiter::clear(self::key($form, $ip));
    }

    /**
     * @param  non-empty-string  $form
     */
    private static function key(string $form, ?string $ip): string
    {
        $ip ??= request()->ip() ?? 'unknown';

        return 'form:' . $form . ':' . $ip;
    }
}
