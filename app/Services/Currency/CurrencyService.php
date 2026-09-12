<?php

declare(strict_types=1);

namespace App\Services\Currency;

use App\Models\CurrencyRate;
use App\Services\Currency\DTOs\CurrencyUnit;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Every monetary column in this app is USD (see config/currency.php). This
 * service resolves the visitor's display currency and converts USD figures
 * into it at render time; nothing is ever stored in a non-USD unit.
 */
class CurrencyService
{
    public const CACHE_KEY = 'currency.rates';

    /**
     * @var array{rates: array<string, float>, synced_at: string|null}|null
     */
    private ?array $snapshot = null;

    /**
     * Configured units that currently have a usable rate, in config order.
     *
     * @return Collection<string, CurrencyUnit>
     */
    public function units(): Collection
    {
        $rates = $this->rates();

        return collect((array) config('currency.units', []))
            ->mapWithKeys(function (mixed $config, mixed $code): array {
                if (! is_array($config) || ! is_string($code) || $code === '') {
                    return [];
                }

                $unit = CurrencyUnit::fromConfig($code, $config);

                return [$unit->code => $unit];
            })
            ->filter(fn (CurrencyUnit $unit): bool => isset($rates[$unit->code]));
    }

    public function unit(?string $code): ?CurrencyUnit
    {
        if (! is_string($code) || $code === '') {
            return null;
        }

        return $this->units()->get(Str::lower($code));
    }

    public function usd(): CurrencyUnit
    {
        $config = (array) config('currency.units.usd', []);

        return CurrencyUnit::fromConfig('usd', $config === [] ? ['label' => 'US Dollar', 'symbol' => '$'] : $config);
    }

    public function default(): CurrencyUnit
    {
        return $this->unit((string) config('currency.default', 'usd')) ?? $this->usd();
    }

    /**
     * The visitor's chosen unit, or the default when nothing valid is stored.
     */
    public function active(): CurrencyUnit
    {
        return $this->unit($this->storedCode()) ?? $this->default();
    }

    public function activeCode(): string
    {
        return $this->active()->code;
    }

    /**
     * Persist a chosen unit for the session. Unknown or unrated codes are
     * dropped so a tampered value cannot force an unconvertible currency.
     */
    public function remember(?string $code): CurrencyUnit
    {
        $unit = $this->unit($code) ?? $this->default();

        session()->put($this->sessionKey(), $unit->code);

        return $unit;
    }

    public function rate(string $code): ?float
    {
        return $this->rates()[Str::lower($code)] ?? null;
    }

    /**
     * @return array<string, float>
     */
    public function rates(): array
    {
        return $this->snapshot()['rates'];
    }

    /**
     * Convert a stored USD figure into the given (or active) display unit.
     */
    public function convert(?float $usdValue, ?string $code = null): ?float
    {
        if ($usdValue === null) {
            return null;
        }

        $rate = $this->rate($code ?? $this->activeCode());

        return $rate === null ? $usdValue : $usdValue * $rate;
    }

    public function ratesSyncedAt(): ?Carbon
    {
        $syncedAt = $this->snapshot()['synced_at'];

        return is_string($syncedAt) ? Carbon::parse($syncedAt) : null;
    }

    public function ratesAreStale(): bool
    {
        $syncedAt = $this->ratesSyncedAt();

        if (! $syncedAt instanceof Carbon) {
            return true;
        }

        return $syncedAt->lt(now()->subMinutes(max(1, (int) config('currency.rates_stale_minutes', 180))));
    }

    public function flushCache(): void
    {
        $this->snapshot = null;

        Cache::forget(self::CACHE_KEY);
    }

    private function storedCode(): ?string
    {
        $code = session()->get($this->sessionKey());

        return is_string($code) ? $code : null;
    }

    private function sessionKey(): string
    {
        return (string) config('currency.session_key', 'display_currency');
    }

    /**
     * @return array{rates: array<string, float>, synced_at: string|null}
     */
    private function snapshot(): array
    {
        return $this->snapshot ??= Cache::remember(
            self::CACHE_KEY,
            max(1, (int) config('currency.rates_cache_seconds', 300)),
            function (): array {
                $rows = CurrencyRate::query()->get(['code', 'rate_per_usd', 'synced_at']);

                $rates = $rows
                    ->mapWithKeys(fn (CurrencyRate $row): array => $row->rate_per_usd > 0
                        ? [Str::lower($row->code) => $row->rate_per_usd]
                        : [])
                    ->all();

                $rates['usd'] = 1.0;

                return [
                    'rates' => $rates,
                    'synced_at' => $rows->max('synced_at')?->toIso8601String(),
                ];
            },
        );
    }
}
