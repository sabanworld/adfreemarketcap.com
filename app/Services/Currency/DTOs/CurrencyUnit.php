<?php

declare(strict_types=1);

namespace App\Services\Currency\DTOs;

use Illuminate\Support\Str;

final class CurrencyUnit
{
    public function __construct(
        public readonly string $code,
        public readonly string $label,
        public readonly string $symbol,
        public readonly bool $symbolAfter = false,
        public readonly bool $isCrypto = false,
        public readonly int $decimals = 2,
        public readonly ?string $baselineCoin = null,
        public readonly float $baselineMultiplier = 1.0,
    ) {}

    /**
     * @param  array<string, mixed>  $config
     */
    public static function fromConfig(string $code, array $config): self
    {
        $label = $config['label'] ?? null;
        $symbol = $config['symbol'] ?? null;
        $baselineCoin = data_get($config, 'baseline.coin');
        $baselineMultiplier = data_get($config, 'baseline.multiplier', 1);

        return new self(
            code: Str::lower($code),
            label: is_string($label) ? $label : Str::upper($code),
            symbol: is_string($symbol) ? $symbol : Str::upper($code) . ' ',
            symbolAfter: (bool) ($config['symbol_after'] ?? false),
            isCrypto: (bool) ($config['crypto'] ?? false),
            decimals: (int) ($config['decimals'] ?? 2),
            baselineCoin: is_string($baselineCoin) && $baselineCoin !== '' ? $baselineCoin : null,
            baselineMultiplier: is_numeric($baselineMultiplier) ? (float) $baselineMultiplier : 1.0,
        );
    }

    public function displayCode(): string
    {
        return Str::upper($this->code);
    }

    public function wrap(string $figure): string
    {
        return $this->symbolAfter
            ? $figure . $this->symbol
            : $this->symbol . $figure;
    }
}
