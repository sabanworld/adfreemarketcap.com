<?php

declare(strict_types=1);

namespace App\Services\MarketData;

use App\Models\ProviderCallHour;
use App\Services\MarketData\DTOs\ProviderCallSummary;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class ProviderCallCounter
{
    public const COINGECKO = 'coingecko';

    public const GECKOTERMINAL = 'geckoterminal';

    public const COINPAPRIKA = 'coinpaprika';

    public const CRYPTOAPIS = 'cryptoapis';

    public const ALTERNATIVE_ME = 'alternative_me';

    public const BITCOIN_CHARTS = 'bitcoin_charts';

    /**
     * CoinGecko REST and Dex onchain share one Pro minute budget when they use the same key.
     *
     * @var list<string>
     */
    public const COINGECKO_QUOTA = [self::COINGECKO, self::GECKOTERMINAL];

    public function count(PendingRequest $request, string $provider): PendingRequest
    {
        return $request->beforeSending(function () use ($provider): void {
            $this->record($provider);
        });
    }

    public function record(string $provider): void
    {
        throw_unless($provider !== '', new InvalidArgumentException('Provider name is required.'));

        $hour = now()->copy()->startOfHour();

        try {
            $affected = ProviderCallHour::query()
                ->where('provider', $provider)
                ->where('hour_starts_at', $hour)
                ->increment('calls');

            if ($affected > 0) {
                return;
            }

            ProviderCallHour::query()->create([
                'provider' => $provider,
                'hour_starts_at' => $hour,
                'calls' => 1,
            ]);
        } catch (UniqueConstraintViolationException) {
            ProviderCallHour::query()
                ->where('provider', $provider)
                ->where('hour_starts_at', $hour)
                ->increment('calls');
        }
    }

    /**
     * @param  list<string>|null  $providers
     * @return Collection<int, ProviderCallSummary>
     */
    public function summaries(?Carbon $at = null, ?array $providers = null): Collection
    {
        $at ??= now();
        $hour = $at->copy()->startOfHour();
        $dayStart = $at->copy()->startOfDay();
        $monthStart = $at->copy()->startOfMonth();

        $query = ProviderCallHour::query()
            ->where('hour_starts_at', '>=', $monthStart)
            ->where('hour_starts_at', '<=', $hour)
            ->orderBy('provider');

        if (is_array($providers) && $providers !== []) {
            $query->whereIn('provider', $providers);
        }

        /** @var Collection<string, Collection<int, ProviderCallHour>> $grouped */
        $grouped = $query->get()->groupBy('provider');

        return $grouped
            ->map(function (Collection $rows, string $provider) use ($hour, $dayStart): ProviderCallSummary {
                return new ProviderCallSummary(
                    provider: $provider,
                    hour: $this->sumFrom($rows, $hour),
                    day: $this->sumFrom($rows, $dayStart),
                    month: (int) $rows->sum('calls'),
                );
            })
            ->values();
    }

    /**
     * @param  list<string>  $providers
     */
    public function combined(array $providers, ?Carbon $at = null): ProviderCallSummary
    {
        $parts = $this->summaries($at, $providers);

        return new ProviderCallSummary(
            provider: implode('+', $providers),
            hour: (int) $parts->sum(fn (ProviderCallSummary $row): int => $row->hour),
            day: (int) $parts->sum(fn (ProviderCallSummary $row): int => $row->day),
            month: (int) $parts->sum(fn (ProviderCallSummary $row): int => $row->month),
        );
    }

    /**
     * @param  Collection<int, ProviderCallHour>  $rows
     */
    private function sumFrom(Collection $rows, Carbon $fromInclusive): int
    {
        return (int) $rows
            ->filter(fn (ProviderCallHour $row): bool => $row->hourStartsAt()->greaterThanOrEqualTo($fromInclusive))
            ->sum('calls');
    }
}
