<?php

declare(strict_types=1);

namespace App\Services\MarketData;

final class MarketNumberFormatter
{
    public static function money(?float $value, int $maxDecimals = 2): string
    {
        if ($value === null) {
            return '—';
        }

        $abs = abs($value);

        if ($abs >= 1_000_000_000_000) {
            return '$'.number_format($value / 1_000_000_000_000, 2).'T';
        }

        if ($abs >= 1_000_000_000) {
            return '$'.number_format($value / 1_000_000_000, 2).'B';
        }

        if ($abs >= 1_000_000) {
            return '$'.number_format($value / 1_000_000, 2).'M';
        }

        if ($abs >= 1) {
            return '$'.number_format($value, min(2, $maxDecimals));
        }

        if ($abs >= 0.01) {
            return '$'.number_format($value, 4);
        }

        return '$'.rtrim(rtrim(number_format($value, 8, '.', ''), '0'), '.');
    }

    public static function percent(?float $value): string
    {
        if ($value === null) {
            return '—';
        }

        return number_format($value, 2).'%';
    }

    public static function compact(?float $value): string
    {
        if ($value === null) {
            return '—';
        }

        if (abs($value) >= 1_000_000) {
            return number_format($value / 1_000_000, 2).'M';
        }

        if (abs($value) >= 1_000) {
            return number_format($value / 1_000, 2).'K';
        }

        return number_format($value, 2);
    }
}
