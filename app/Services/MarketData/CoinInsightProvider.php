<?php

declare(strict_types=1);

namespace App\Services\MarketData;

use App\Services\MarketData\DTOs\MarketCycleData;
use App\Services\MarketData\DTOs\TreasuryHolderData;
use App\Services\MarketData\DTOs\TreasuryHoldingsData;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class CoinInsightProvider
{
    /**
     * Known Bitcoin halving dates (UTC). Next after Apr 2024 is estimated ~4 years later.
     *
     * @var list<string>
     */
    private const BITCOIN_HALVINGS = [
        '2012-11-28',
        '2016-07-09',
        '2020-05-11',
        '2024-04-20',
        '2028-04-01',
    ];

    public function fetchTreasuryHoldings(string $coinExternalId): TreasuryHoldingsData
    {
        $response = Http::baseUrl((string) config('marketdata.coingecko.base_url'))
            ->acceptJson()
            ->timeout(30)
            ->when(
                filled(config('marketdata.coingecko.api_key')),
                fn ($request) => $request->withHeaders([
                    (string) config('marketdata.coingecko.api_key_header') => (string) config('marketdata.coingecko.api_key'),
                ]),
            )
            ->get('/companies/public_treasury/' . $coinExternalId);

        throw_unless($response->successful(), new RuntimeException(
            'CoinGecko treasury failed: ' . $response->status() . ' ' . $response->body()
        ));

        /** @var array<string, mixed> $payload */
        $payload = $response->json() ?? [];
        $companies = is_array($payload['companies'] ?? null) ? $payload['companies'] : [];
        $holders = [];

        foreach (array_values($companies) as $index => $row) {
            if (! is_array($row)) {
                continue;
            }

            $name = $row['name'] ?? null;
            if (! is_string($name) || $name === '') {
                continue;
            }

            $holdings = $row['total_holdings'] ?? null;
            if (! is_numeric($holdings)) {
                continue;
            }

            $symbol = $row['symbol'] ?? null;
            $country = $row['country'] ?? null;

            $holders[] = new TreasuryHolderData(
                name: $name,
                symbol: is_string($symbol) ? $symbol : null,
                country: is_string($country) ? $country : null,
                totalHoldings: (float) $holdings,
                totalEntryValueUsd: is_numeric($row['total_entry_value_usd'] ?? null)
                    ? (float) $row['total_entry_value_usd']
                    : null,
                totalCurrentValueUsd: is_numeric($row['total_current_value_usd'] ?? null)
                    ? (float) $row['total_current_value_usd']
                    : null,
                percentageOfTotalSupply: is_numeric($row['percentage_of_total_supply'] ?? null)
                    ? (float) $row['percentage_of_total_supply']
                    : null,
                rank: $index + 1,
            );
        }

        return new TreasuryHoldingsData(
            coinExternalId: $coinExternalId,
            totalHoldings: is_numeric($payload['total_holdings'] ?? null) ? (float) $payload['total_holdings'] : 0.0,
            totalValueUsd: is_numeric($payload['total_value_usd'] ?? null) ? (float) $payload['total_value_usd'] : null,
            marketCapDominance: is_numeric($payload['market_cap_dominance'] ?? null)
                ? (float) $payload['market_cap_dominance']
                : null,
            companies: $holders,
        );
    }

    public function fetchBitcoinMarketCycle(): MarketCycleData
    {
        $baseUrl = (string) config('marketdata.bitcoin_charts.base_url', 'https://charts.bitcoin.com/api/v1');
        $timespan = (string) config('marketdata.bitcoin_charts.pi_cycle_timespan', '1y');
        $limit = max(30, (int) config('marketdata.bitcoin_charts.pi_cycle_limit', 365));

        $response = Http::baseUrl($baseUrl)
            ->acceptJson()
            ->timeout(30)
            ->get('/charts/pi-cycle-top', [
                'interval' => 'daily',
                'timespan' => $timespan,
                'limit' => $limit,
            ]);

        throw_unless($response->successful(), new RuntimeException(
            'Bitcoin.com Pi Cycle failed: ' . $response->status() . ' ' . $response->body()
        ));

        /** @var array<string, mixed> $payload */
        $payload = $response->json() ?? [];
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];

        $priceSeries = $this->seriesPoints($data['price'] ?? null, 'price');
        $ma111Series = $this->seriesPoints($data['ma111'] ?? null, 'value');
        $ma350Series = $this->seriesPoints($data['ma350x2'] ?? null, 'value');

        $price = $this->lastValue($priceSeries);
        $ma111 = $this->lastValue($ma111Series);
        $ma350x2 = $this->lastValue($ma350Series);

        $gapPercent = null;
        if ($ma111 !== null && $ma350x2 !== null && $ma350x2 != 0.0) {
            $gapPercent = (($ma350x2 - $ma111) / $ma350x2) * 100;
        }

        $status = $this->piCycleStatus($ma111, $ma350x2, $gapPercent);
        $lastCrossAt = $this->lastCrossAt($data['crosses'] ?? null);
        $halving = $this->halvingProgress(now());

        return new MarketCycleData(
            price: $price,
            ma111: $ma111,
            ma350x2: $ma350x2,
            maGapPercent: $gapPercent,
            piCycleStatus: $status,
            lastCrossAt: $lastCrossAt,
            lastHalvingAt: $halving['last'],
            nextHalvingAt: $halving['next'],
            daysSinceHalving: $halving['days_since'],
            daysUntilHalving: $halving['days_until'],
            cycleProgressPercent: $halving['progress'],
            halvingEpoch: $halving['epoch'],
            chartPrice: $priceSeries,
            chartMa111: $ma111Series,
            chartMa350x2: $ma350Series,
        );
    }

    /**
     * @return array{last: Carbon, next: Carbon, days_since: int, days_until: int, progress: float, epoch: int}
     */
    public function halvingProgress(Carbon $now): array
    {
        $dates = array_map(
            static fn (string $date): Carbon => Carbon::parse($date, 'UTC')->startOfDay(),
            self::BITCOIN_HALVINGS,
        );

        $last = $dates[0];
        $next = $dates[1];
        $epoch = 1;

        foreach ($dates as $index => $date) {
            if ($now->gte($date)) {
                $last = $date;
                $epoch = $index + 1;
                $next = $dates[$index + 1] ?? $date->copy()->addDays(1458);
            }
        }

        $cycleLength = max(1, $last->diffInDays($next));
        $daysSince = max(0, $last->diffInDays($now));
        $daysUntil = max(0, $now->diffInDays($next));
        $progress = min(100, ($daysSince / $cycleLength) * 100);

        return [
            'last' => $last,
            'next' => $next,
            'days_since' => (int) $daysSince,
            'days_until' => (int) $daysUntil,
            'progress' => round($progress, 4),
            'epoch' => $epoch,
        ];
    }

    /**
     * @return list<array{0: int, 1: float}>
     */
    private function seriesPoints(mixed $rows, string $valueKey): array
    {
        if (! is_array($rows)) {
            return [];
        }

        $points = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $timestamp = $row['timestamp'] ?? null;
            $value = $row[$valueKey] ?? null;

            if (! is_numeric($timestamp) || ! is_numeric($value)) {
                continue;
            }

            $points[] = [(int) $timestamp, (float) $value];
        }

        return $points;
    }

    /**
     * @param  list<array{0: int, 1: float}>  $points
     */
    private function lastValue(array $points): ?float
    {
        if ($points === []) {
            return null;
        }

        return $points[array_key_last($points)][1];
    }

    private function piCycleStatus(?float $ma111, ?float $ma350x2, ?float $gapPercent): string
    {
        if ($ma111 === null || $ma350x2 === null || $gapPercent === null) {
            return 'unknown';
        }

        if ($ma111 >= $ma350x2) {
            return 'top_signal';
        }

        if ($gapPercent <= 5) {
            return 'approaching_top';
        }

        if ($gapPercent <= 15) {
            return 'late_cycle';
        }

        return 'mid_cycle';
    }

    private function lastCrossAt(mixed $crosses): ?Carbon
    {
        if (! is_array($crosses) || $crosses === []) {
            return null;
        }

        $last = end($crosses);
        if (! is_array($last)) {
            return null;
        }

        $timestamp = $last['timestamp'] ?? null;
        if (! is_numeric($timestamp)) {
            return null;
        }

        return Carbon::createFromTimestampMs((int) $timestamp);
    }
}
