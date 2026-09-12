<?php

declare(strict_types=1);

namespace App\Services\MarketData\Exceptions;

use RuntimeException;

class ProviderCoinNotFoundException extends RuntimeException
{
    public function __construct(
        public readonly string $provider,
        public readonly string $externalId,
    ) {
        parent::__construct("{$provider} coin not found: {$externalId}");
    }
}
