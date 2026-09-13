<?php

declare(strict_types=1);

namespace App\Services\MarketData;

use App\Models\Coin;
use App\Models\CoinChartSeries;
use App\Models\CoinProviderId;
use App\Services\Currency\MarketDisplayService;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

final class CoinChartService
{
    /**
     * @var array<string, array{series: string, seconds: int|null, label: string}>
     */
    public const RANGES = [
        '1h' => ['series' => CoinChartSeries::SERIES_INTRADAY, 'seconds' => 3600, 'label' => '1H'],
        '12h' => ['series' => CoinChartSeries::SERIES_INTRADAY, 'seconds' => 43_200, 'label' => '12H'],
        '1d' => ['series' => CoinChartSeries::SERIES_INTRADAY, 'seconds' => 86_400, 'label' => '1D'],
        '7d' => ['series' => CoinChartSeries::SERIES_SHORT, 'seconds' => 604_800, 'label' => '7D'],
        '1m' => ['series' => CoinChartSeries::SERIES_SHORT, 'seconds' => 2_592_000, 'label' => '1M'],
        '3m' => ['series' => CoinChartSeries::SERIES_SHORT, 'seconds' => 7_776_000, 'label' => '3M'],
        '6m' => ['series' => CoinChartSeries::SERIES_DAILY, 'seconds' => 15_552_000, 'label' => '6M'],
        '1y' => ['series' => CoinChartSeries::SERIES_DAILY, 'seconds' => 31_536_000, 'label' => '1Y'],
        '5y' => ['series' => CoinChartSeries::SERIES_DAILY, 'seconds' => 157_680_000, 'label' => '5Y'],
        '10y' => ['series' => CoinChartSeries::SERIES_DAILY, 'seconds' => 315_360_000, 'label' => '10Y'],
        'all' => ['series' => CoinChartSeries::SERIES_DAILY, 'seconds' => null, 'label' => 'All'],
    ];

    /**
     * @var array<string, array{days: string, interval: string|null}>
     */
    public const SERIES_FETCH = [
        CoinChartSeries::SERIES_INTRADAY => ['days' => '1', 'interval' => null],
        CoinChartSeries::SERIES_SHORT => ['days' => '90', 'interval' => null],
        CoinChartSeries::SERIES_DAILY => ['days' => 'max', 'interval' => 'daily'],
    ];

    public function __construct(
        private readonly MarketDisplayService $display,
    ) {}

    /**
     * @return list<string>
     */
    public function rangeKeys(): array
    {
        return array_keys(self::RANGES);
    }

    public function normalizeRange(string $range): string
    {
        return array_key_exists($range, self::RANGES) ? $range : '7d';
    }

    public function seriesForRange(string $range): string
    {
        $range = $this->normalizeRange($range);

        return self::RANGES[$range]['series'];
    }

    public function labelFor(string $range): string
    {
        $range = $this->normalizeRange($range);

        return self::RANGES[$range]['label'];
    }

    /**
     * @return list<string>
     */
    public function availableRanges(Coin $coin): array
    {
        $coin->loadMissing('chartSeries');

        $available = [];

        foreach (self::RANGES as $key => $meta) {
            $points = $this->rawPointsFor($coin, $key);

            if (count($points) >= 2) {
                $available[] = $key;
            }
        }

        return $available;
    }

    /**
     * Display-currency chart points for a range.
     *
     * @return list<array{0: int, 1: float}>
     */
    public function pointsFor(Coin $coin, string $range): array
    {
        return $this->display->chart($this->rawPointsFor($coin, $range));
    }

    /**
     * @return list<array{0: int, 1: float}>
     */
    public function rawPointsFor(Coin $coin, string $range): array
    {
        $range = $this->normalizeRange($range);
        $seriesKey = self::RANGES[$range]['series'];
        $seconds = self::RANGES[$range]['seconds'];

        $row = $coin->relationLoaded('chartSeries')
            ? $coin->chartSeries->firstWhere('series', $seriesKey)
            : $coin->chartSeries()->where('series', $seriesKey)->first();

        $points = $this->normalizePoints($row?->points);

        if ($points === [] && $range === '7d' && is_array($coin->chart_7d)) {
            $points = $this->normalizePoints($coin->chart_7d);
        }

        if ($seconds === null || $points === []) {
            return $points;
        }

        $cutoffMs = ((int) now()->timestamp * 1000) - ($seconds * 1000);

        return array_values(array_filter(
            $points,
            static fn (array $point): bool => $point[0] >= $cutoffMs,
        ));
    }

    /**
     * @return list<string>
     */
    public function chartLabels(array $points, string $range): array
    {
        $range = $this->normalizeRange($range);
        $format = match (true) {
            in_array($range, ['1h', '12h', '1d'], true) => 'H:i',
            in_array($range, ['7d', '1m', '3m'], true) => 'M j',
            default => 'M Y',
        };

        $labels = [];

        foreach ($points as [$timestamp]) {
            $labels[] = Carbon::createFromTimestamp((int) floor(((int) $timestamp) / 1000))->format($format);
        }

        return $labels;
    }

    /**
     * @param  list<array{0: int|float, 1: float}>  $points
     */
    public function upsertSeries(Coin $coin, string $series, array $points): CoinChartSeries
    {
        throw_unless(in_array($series, CoinChartSeries::SERIES_KEYS, true), new InvalidArgumentException(
            "Unknown chart series [{$series}]."
        ));

        $normalized = $this->normalizePoints($points);

        $row = CoinChartSeries::query()->updateOrCreate(
            [
                'coin_id' => $coin->id,
                'series' => $series,
            ],
            [
                'points' => $normalized,
                'synced_at' => now(),
            ],
        );

        if ($series === CoinChartSeries::SERIES_SHORT) {
            $this->deriveChart7d($coin, $normalized);
        }

        return $row;
    }

    public function seriesIsMissingOrStale(Coin $coin, string $series): bool
    {
        $row = $coin->relationLoaded('chartSeries')
            ? $coin->chartSeries->firstWhere('series', $series)
            : $coin->chartSeries()->where('series', $series)->first();

        if (! $row instanceof CoinChartSeries) {
            return true;
        }

        return $row->isStale();
    }

    /**
     * @return list<string>
     */
    public function staleSeriesKeys(Coin $coin): array
    {
        $coin->loadMissing('chartSeries');

        return array_values(array_filter(
            CoinChartSeries::SERIES_KEYS,
            fn (string $series): bool => $this->seriesIsMissingOrStale($coin, $series),
        ));
    }

    public function coingeckoExternalId(Coin $coin): ?string
    {
        $mapping = $coin->providerIds()
            ->where('provider', 'coingecko')
            ->first();

        if ($mapping instanceof CoinProviderId && filled($mapping->external_id)) {
            return $mapping->external_id;
        }

        return null;
    }

    /**
     * @return list<array{0: int, 1: float}>
     */
    public function normalizePoints(mixed $points): array
    {
        if (! is_array($points)) {
            return [];
        }

        $normalized = [];

        foreach ($points as $point) {
            if (! is_array($point) || ! is_numeric($point[0] ?? null) || ! is_numeric($point[1] ?? null)) {
                continue;
            }

            $normalized[] = [(int) $point[0], (float) $point[1]];
        }

        return $normalized;
    }

    /**
     * @param  list<array{0: int, 1: float}>  $points
     */
    private function deriveChart7d(Coin $coin, array $points): void
    {
        $cutoffMs = ((int) now()->subDays(7)->timestamp) * 1000;
        $chart7d = array_values(array_filter(
            $points,
            static fn (array $point): bool => $point[0] >= $cutoffMs,
        ));

        $coin->update([
            'chart_7d' => $chart7d !== [] ? $chart7d : $coin->chart_7d,
        ]);
    }
}
