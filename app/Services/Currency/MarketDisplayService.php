<?php

declare(strict_types=1);

namespace App\Services\Currency;

use App\Models\Coin;
use App\Services\Currency\DTOs\CurrencyUnit;

/**
 * Expresses USD-denominated market data in the visitor's display unit.
 *
 * Spot figures only need today's rate, so they go through
 * MarketNumberFormatter. History and percentage changes need more care: with a
 * crypto baseline (BTC, satoshis, ETH) we hold that asset's own USD history, so
 * each point is divided by the baseline price of its own timestamp. Bitcoin
 * priced in BTC is then flat at 1.00 with a 0% change, rather than the dollar
 * chart scaled by one current rate. Fiat units keep the current-rate
 * approximation because we store no FX history.
 */
class MarketDisplayService
{
    private ?string $memoizedFor = null;

    private ?Coin $baselineCoin = null;

    /**
     * @var array<int, float>|null
     */
    private ?array $baselineChart = null;

    /**
     * @var list<float>|null
     */
    private ?array $baselineSparkline = null;

    public function __construct(
        private readonly CurrencyService $currency,
    ) {}

    public function unit(): CurrencyUnit
    {
        return $this->currency->active();
    }

    /**
     * True when history is denominated in a baseline asset rather than scaled by
     * the current rate, which is what decides whether the UI needs a caveat.
     */
    public function usesBaselineHistory(): bool
    {
        return $this->baselineChart() !== null;
    }

    public function baselineCode(): ?string
    {
        return $this->baselineCoin() instanceof Coin ? $this->unit()->displayCode() : null;
    }

    /**
     * A percentage change relative to the display unit. Against a crypto
     * baseline this answers "did it gain on BTC", not "did it gain on the
     * dollar", so the baseline asset itself reports 0.
     */
    public function change(mixed $usdPercent, string $window = '24h'): ?float
    {
        $percent = $this->numeric($usdPercent);

        if ($percent === null) {
            return null;
        }

        $column = match ($window) {
            '1h' => 'percent_change_1h',
            '24h' => 'percent_change_24h',
            '7d' => 'percent_change_7d',
            default => null,
        };

        $baseline = $column === null ? null : $this->numeric($this->baselineCoin()?->{$column});

        if ($baseline === null) {
            return $percent;
        }

        $divisor = 1 + $baseline / 100;

        if (abs($divisor) < 1e-9) {
            return $percent;
        }

        return ((1 + $percent / 100) / $divisor - 1) * 100;
    }

    /**
     * @return list<array{0: int, 1: float}>
     */
    public function chart(mixed $usdPoints): array
    {
        $baseline = $this->baselineChart();
        $rate = $this->rate();
        $converted = [];

        foreach (is_array($usdPoints) ? $usdPoints : [] as $point) {
            if (! is_array($point) || ! is_numeric($point[0] ?? null) || ! is_numeric($point[1] ?? null)) {
                continue;
            }

            $timestamp = (int) $point[0];
            $usd = (float) $point[1];

            if ($baseline === null) {
                $converted[] = [$timestamp, $usd * $rate];

                continue;
            }

            $value = $this->againstBaseline($usd, $this->baselineNear($timestamp, $baseline));

            if ($value !== null) {
                $converted[] = [$timestamp, $value];
            }
        }

        return $converted;
    }

    /**
     * @return list<float>
     */
    public function sparkline(mixed $usdPrices): array
    {
        $prices = array_values(array_filter(
            is_array($usdPrices) ? $usdPrices : [],
            static fn (mixed $price): bool => is_numeric($price),
        ));

        $baseline = $this->baselineSparkline();
        $lastIndex = count($prices) - 1;

        if ($baseline === null || $lastIndex < 1) {
            $rate = $this->rate();

            return array_map(static fn (mixed $price): float => (float) $price * $rate, $prices);
        }

        $baselineLast = count($baseline) - 1;
        $converted = [];

        foreach ($prices as $index => $price) {
            // The two series cover the same 7 day window, so map by position.
            $value = $this->againstBaseline(
                (float) $price,
                $baseline[(int) round($index * $baselineLast / $lastIndex)] ?? null,
            );

            if ($value !== null) {
                $converted[] = $value;
            }
        }

        return $converted;
    }

    private function againstBaseline(float $usd, ?float $baselineUsd): ?float
    {
        if ($baselineUsd === null || $baselineUsd <= 0) {
            return null;
        }

        return $usd / $baselineUsd * $this->unit()->baselineMultiplier;
    }

    /**
     * @param  array<int, float>  $baseline
     */
    private function baselineNear(int $timestamp, array $baseline): ?float
    {
        if (isset($baseline[$timestamp])) {
            return $baseline[$timestamp];
        }

        $nearest = null;
        $smallestGap = null;

        foreach ($baseline as $baselineTimestamp => $usd) {
            $gap = abs($baselineTimestamp - $timestamp);

            if ($smallestGap === null || $gap < $smallestGap) {
                $smallestGap = $gap;
                $nearest = $usd;
            }
        }

        return $nearest;
    }

    /**
     * @return array<int, float>|null
     */
    private function baselineChart(): ?array
    {
        // Resolving the coin first clears these memos when the unit changed.
        $coin = $this->baselineCoin();

        if ($this->baselineChart !== null) {
            return $this->baselineChart === [] ? null : $this->baselineChart;
        }

        $points = $coin?->chart_7d;
        $chart = [];

        foreach (is_array($points) ? $points : [] as $point) {
            if (is_array($point) && is_numeric($point[0] ?? null) && is_numeric($point[1] ?? null) && (float) $point[1] > 0) {
                $chart[(int) $point[0]] = (float) $point[1];
            }
        }

        $this->baselineChart = $chart;

        return $chart === [] ? null : $chart;
    }

    /**
     * @return list<float>|null
     */
    private function baselineSparkline(): ?array
    {
        $coin = $this->baselineCoin();

        if ($this->baselineSparkline !== null) {
            return count($this->baselineSparkline) > 1 ? $this->baselineSparkline : null;
        }

        $prices = $coin?->sparkline_7d;

        $this->baselineSparkline = array_values(array_map(
            static fn (mixed $price): float => (float) $price,
            array_filter(
                is_array($prices) ? $prices : [],
                static fn (mixed $price): bool => is_numeric($price) && (float) $price > 0,
            ),
        ));

        return count($this->baselineSparkline) > 1 ? $this->baselineSparkline : null;
    }

    private function baselineCoin(): ?Coin
    {
        $unit = $this->unit();

        if ($this->memoizedFor === $unit->code) {
            return $this->baselineCoin;
        }

        $this->memoizedFor = $unit->code;
        $this->baselineChart = null;
        $this->baselineSparkline = null;
        $slug = $unit->baselineCoin;

        $this->baselineCoin = $slug === null
            ? null
            : Coin::query()->where('slug', $slug)->first([
                'id',
                'slug',
                'price',
                'percent_change_1h',
                'percent_change_24h',
                'percent_change_7d',
                'chart_7d',
                'sparkline_7d',
            ]);

        return $this->baselineCoin;
    }

    private function rate(): float
    {
        return $this->currency->rate($this->unit()->code) ?? 1.0;
    }

    private function numeric(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }
}
