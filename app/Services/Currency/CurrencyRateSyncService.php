<?php

declare(strict_types=1);

namespace App\Services\Currency;

use App\Models\CurrencyRate;
use App\Models\SyncRun;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class CurrencyRateSyncService
{
    public function __construct(
        private readonly ExchangeRateProvider $provider,
        private readonly CurrencyService $currency,
    ) {}

    public function syncRates(): SyncRun
    {
        $run = SyncRun::query()->create([
            'type' => 'currency_rates',
            'status' => SyncRun::STATUS_RUNNING,
            'provider' => $this->provider->name(),
            'started_at' => now(),
        ]);

        try {
            $rates = $this->provider->fetchRatesPerUsd();
            $processed = 0;

            foreach (array_keys((array) config('currency.units', [])) as $code) {
                $code = Str::lower((string) $code);
                $rate = $rates->get($code);

                if (! is_numeric($rate) || (float) $rate <= 0) {
                    continue;
                }

                CurrencyRate::query()->updateOrCreate(
                    ['code' => $code],
                    [
                        'provider' => $this->provider->name(),
                        'rate_per_usd' => (float) $rate,
                        'synced_at' => now(),
                    ],
                );

                $processed++;
            }

            throw_if($processed === 0, new RuntimeException(
                'No configured currency received a usable rate from ' . $this->provider->name() . '.'
            ));

            $this->currency->flushCache();
            $run->markSucceeded($processed, "Synced {$processed} currency rates.");

            return $run->fresh();
        } catch (Throwable $throwable) {
            $run->markFailed($throwable->getMessage());

            throw $throwable;
        }
    }
}
