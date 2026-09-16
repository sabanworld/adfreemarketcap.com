<?php

declare(strict_types=1);

namespace App\Services\MarketData;

use App\Models\DexChartSeries;
use Illuminate\Database\Eloquent\Model;

class DexChartService
{
    /**
     * @var array<string, array{series: string, seconds: int|null, label: string}>
     */
    public const RANGES = [
        '1h' => ['series' => DexChartSeries::SERIES_INTRADAY, 'seconds' => 3600, 'label' => '1H'],
        '12h' => ['series' => DexChartSeries::SERIES_INTRADAY, 'seconds' => 43200, 'label' => '12H'],
        '1d' => ['series' => DexChartSeries::SERIES_INTRADAY, 'seconds' => 86400, 'label' => '1D'],
        '7d' => ['series' => DexChartSeries::SERIES_SHORT, 'seconds' => 604800, 'label' => '7D'],
        '1m' => ['series' => DexChartSeries::SERIES_SHORT, 'seconds' => 2592000, 'label' => '1M'],
        '3m' => ['series' => DexChartSeries::SERIES_SHORT, 'seconds' => 7776000, 'label' => '3M'],
        '1y' => ['series' => DexChartSeries::SERIES_DAILY, 'seconds' => 31536000, 'label' => '1Y'],
        'all' => ['series' => DexChartSeries::SERIES_DAILY, 'seconds' => null, 'label' => 'ALL'],
    ];

    public function normalizeRange(string $range): string
    {
        return array_key_exists($range, self::RANGES) ? $range : '7d';
    }

    /**
     * @return list<string>
     */
    public function rangeKeys(): array
    {
        return array_keys(self::RANGES);
    }

    public function seriesForRange(string $range): string
    {
        $range = $this->normalizeRange($range);

        return self::RANGES[$range]['series'];
    }

    public function labelForRange(string $range): string
    {
        $range = $this->normalizeRange($range);

        return self::RANGES[$range]['label'];
    }

    /**
     * @return list<string>
     */
    public function availableRanges(Model $chartable): array
    {
        $chartable->loadMissing('chartSeries');
        $have = $chartable->chartSeries
            ->filter(fn (DexChartSeries $row): bool => is_array($row->points) && $row->points !== [])
            ->pluck('series')
            ->all();

        return array_values(array_filter(
            $this->rangeKeys(),
            fn (string $range): bool => in_array(self::RANGES[$range]['series'], $have, true),
        ));
    }

    /**
     * @return list<array{0: int, 1: float}>
     */
    public function pointsFor(Model $chartable, string $range): array
    {
        $range = $this->normalizeRange($range);
        $seriesKey = self::RANGES[$range]['series'];
        $seconds = self::RANGES[$range]['seconds'];

        $chartable->loadMissing('chartSeries');
        /** @var DexChartSeries|null $row */
        $row = $chartable->chartSeries->firstWhere('series', $seriesKey);
        $points = is_array($row?->points) ? $row->points : [];

        if ($seconds === null || $points === []) {
            return $this->normalizePoints($points);
        }

        $cutoff = (int) (now()->getTimestampMs() - ($seconds * 1000));

        return $this->normalizePoints(array_values(array_filter(
            $points,
            fn (mixed $point): bool => is_array($point)
                && isset($point[0], $point[1])
                && is_numeric($point[0])
                && (int) $point[0] >= $cutoff,
        )));
    }

    /**
     * Label band for chart axes. Actual strings are formatted in the browser
     * (visitor timezone via Intl); this only names which style to use.
     *
     * @return 'time'|'day'|'month'
     */
    public function chartLabelStyle(string $range): string
    {
        $range = $this->normalizeRange($range);

        return match (true) {
            in_array($range, ['1h', '12h', '1d'], true) => 'time',
            in_array($range, ['7d', '1m', '3m'], true) => 'day',
            default => 'month',
        };
    }

    /**
     * @return list<string>
     */
    public function staleSeriesKeys(Model $chartable): array
    {
        $chartable->loadMissing('chartSeries');

        return array_values(array_filter(
            DexChartSeries::SERIES_KEYS,
            function (string $series) use ($chartable): bool {
                /** @var DexChartSeries|null $row */
                $row = $chartable->chartSeries->firstWhere('series', $series);

                return ! $row instanceof DexChartSeries || $row->isStale();
            },
        ));
    }

    /**
     * @param  array<string, list<array{0: int, 1: float}>>  $seriesMap
     */
    public function upsertSeriesFor(Model $chartable, array $seriesMap): void
    {
        foreach ($seriesMap as $series => $points) {
            if (! in_array($series, DexChartSeries::SERIES_KEYS, true)) {
                continue;
            }

            DexChartSeries::query()->updateOrCreate(
                [
                    'chartable_type' => $chartable->getMorphClass(),
                    'chartable_id' => $chartable->getKey(),
                    'series' => $series,
                ],
                [
                    'points' => $this->normalizePoints($points),
                    'synced_at' => now(),
                ],
            );
        }
    }

    /**
     * @param  list<mixed>  $points
     * @return list<array{0: int, 1: float}>
     */
    private function normalizePoints(array $points): array
    {
        $normalized = [];

        foreach ($points as $point) {
            if (! is_array($point) || ! isset($point[0], $point[1])) {
                continue;
            }

            if (! is_numeric($point[0]) || ! is_numeric($point[1])) {
                continue;
            }

            $normalized[] = [(int) $point[0], (float) $point[1]];
        }

        return $normalized;
    }
}
