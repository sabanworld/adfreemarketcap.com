<?php

declare(strict_types=1);

namespace App\Services\Altcha;

use AltchaOrg\Altcha\Algorithm\Pbkdf2;
use AltchaOrg\Altcha\Altcha;
use AltchaOrg\Altcha\Challenge;
use AltchaOrg\Altcha\CreateChallengeOptions;
use AltchaOrg\Altcha\VerifySolutionOptions;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use RuntimeException;

final class AltchaService
{
    public function enabled(): bool
    {
        return (bool) config('altcha.enabled', true);
    }

    public function createChallenge(): Challenge
    {
        return $this->client()->createChallenge(new CreateChallengeOptions(
            algorithm: new Pbkdf2,
            cost: max(1000, (int) config('altcha.cost', 10000)),
            expiresAt: time() + max(60, (int) config('altcha.expires_seconds', 300)),
        ));
    }

    public function verify(?string $payload): bool
    {
        if (! $this->enabled()) {
            return true;
        }

        if (! is_string($payload) || $payload === '') {
            return false;
        }

        $bypass = config('altcha.testing_bypass');
        if (app()->runningUnitTests() && is_string($bypass) && $bypass !== '' && hash_equals($bypass, $payload)) {
            return true;
        }

        try {
            $result = $this->client()->verifySolution(new VerifySolutionOptions(
                payload: $payload,
                algorithm: new Pbkdf2,
            ));
        } catch (InvalidArgumentException) {
            return false;
        }

        if (! $result->verified) {
            return false;
        }

        return $this->consumePayloadOnce($payload);
    }

    private function client(): Altcha
    {
        $secret = config('altcha.hmac_signature_secret');
        throw_unless(is_string($secret) && $secret !== '', new RuntimeException(
            'ALTCHA_HMAC_SECRET is required when ALTCHA is enabled.',
        ));

        $keySecret = config('altcha.hmac_key_signature_secret');

        return new Altcha(
            hmacSignatureSecret: $secret,
            hmacKeySignatureSecret: is_string($keySecret) && $keySecret !== '' ? $keySecret : null,
        );
    }

    private function consumePayloadOnce(string $payload): bool
    {
        $key = 'altcha:used:' . hash('sha256', $payload);
        $ttl = max(60, (int) config('altcha.expires_seconds', 300));

        if (Cache::has($key)) {
            return false;
        }

        Cache::put($key, true, $ttl);

        return true;
    }
}
