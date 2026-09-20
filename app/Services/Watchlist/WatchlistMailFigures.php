<?php

declare(strict_types=1);

namespace App\Services\Watchlist;

use App\Services\MarketData\MarketNumberFormatter;

final class WatchlistMailFigures
{
    public static function price(mixed $value): string
    {
        return MarketNumberFormatter::moneyUsd(self::numeric($value));
    }

    public static function percent(mixed $value): string
    {
        return MarketNumberFormatter::percent(self::numeric($value));
    }

    public static function absolutePercent(mixed $value): string
    {
        $numeric = self::numeric($value);

        return MarketNumberFormatter::percent($numeric === null ? null : abs($numeric));
    }

    /**
     * The sign carries the direction, because the caret the site uses is an icon font and an
     * email cannot load one. A change that rounds to 0.00% is not a direction, so it keeps
     * neutral ink and no sign, the same rule `<x-afmc.price-change>` follows.
     */
    public static function signedPercent(mixed $value): string
    {
        $numeric = self::numeric($value);

        if ($numeric === null) {
            return MarketNumberFormatter::percent(null);
        }

        $direction = self::direction($numeric);
        $figure = MarketNumberFormatter::percent(abs($numeric));

        return match ($direction) {
            'up' => '+' . $figure,
            'down' => '-' . $figure,
            default => $figure,
        };
    }

    /**
     * @return 'up'|'down'|'flat'|'empty'
     */
    public static function direction(mixed $value): string
    {
        $numeric = self::numeric($value);

        if ($numeric === null) {
            return 'empty';
        }

        if (abs($numeric) < 0.005) {
            return 'flat';
        }

        return $numeric > 0 ? 'up' : 'down';
    }

    private static function numeric(mixed $value): ?float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        if (is_string($value) && is_numeric($value)) {
            return (float) $value;
        }

        return null;
    }
}
