<?php

declare(strict_types=1);

namespace App\Services\MarketData;

/**
 * Market-cap-weighted AFMC10 index and Blockchain Center–style altcoin season.
 */
final class MarketStatusCalculator
{
    /**
     * @param  list<array{
     *     market_cap: float|null,
     *     percent_change_24h: float|null,
     *     percent_change_7d?: float|null,
     *     percent_change_30d?: float|null,
     *     percent_change_200d?: float|null,
     *     percent_change_1y?: float|null
     * }>  $constituents
     * @return array{
     *     value: float|null,
     *     change_24h: float|null,
     *     change_7d: float|null,
     *     change_30d: float|null,
     *     change_200d: float|null,
     *     change_1y: float|null,
     *     base_sum: float|null,
     *     basket_sum: float|null
     * }
     */
    public function afmc10(array $constituents, ?float $existingBaseSum): array
    {
        $basketSum = 0.0;

        foreach ($constituents as $row) {
            $marketCap = $row['market_cap'];
            if ($marketCap !== null && $marketCap > 0) {
                $basketSum += $marketCap;
            }
        }

        if ($basketSum <= 0) {
            return [
                'value' => null,
                'change_24h' => null,
                'change_7d' => null,
                'change_30d' => null,
                'change_200d' => null,
                'change_1y' => null,
                'base_sum' => $existingBaseSum,
                'basket_sum' => null,
            ];
        }

        $baseSum = ($existingBaseSum !== null && $existingBaseSum > 0)
            ? $existingBaseSum
            : $basketSum;

        return [
            'value' => 100.0 * ($basketSum / $baseSum),
            'change_24h' => $this->weightedChange($constituents, 'percent_change_24h'),
            'change_7d' => $this->weightedChange($constituents, 'percent_change_7d'),
            'change_30d' => $this->weightedChange($constituents, 'percent_change_30d'),
            'change_200d' => $this->weightedChange($constituents, 'percent_change_200d'),
            'change_1y' => $this->weightedChange($constituents, 'percent_change_1y'),
            'base_sum' => $baseSum,
            'basket_sum' => $basketSum,
        ];
    }

    /**
     * @param  list<float|null>  $altNinetyDayChanges
     * @return array{index: float|null, sample_size: int}
     */
    public function altcoinSeason(?float $btcNinetyDayChange, array $altNinetyDayChanges): array
    {
        if ($btcNinetyDayChange === null) {
            return ['index' => null, 'sample_size' => 0];
        }

        $eligible = [];
        foreach ($altNinetyDayChanges as $change) {
            if ($change !== null) {
                $eligible[] = $change;
            }
        }

        $sampleSize = count($eligible);
        if ($sampleSize === 0) {
            return ['index' => null, 'sample_size' => 0];
        }

        $beatingBtc = 0;
        foreach ($eligible as $change) {
            if ($change > $btcNinetyDayChange) {
                $beatingBtc++;
            }
        }

        return [
            'index' => 100.0 * ($beatingBtc / $sampleSize),
            'sample_size' => $sampleSize,
        ];
    }

    /**
     * @param  list<array<string, float|null>>  $constituents
     */
    private function weightedChange(array $constituents, string $field): ?float
    {
        $numerator = 0.0;
        $denominator = 0.0;

        foreach ($constituents as $row) {
            $marketCap = $row['market_cap'] ?? null;
            $change = $row[$field] ?? null;
            if ($marketCap === null || $marketCap <= 0 || $change === null) {
                continue;
            }

            $numerator += $marketCap * $change;
            $denominator += $marketCap;
        }

        if ($denominator <= 0) {
            return null;
        }

        return $numerator / $denominator;
    }
}
