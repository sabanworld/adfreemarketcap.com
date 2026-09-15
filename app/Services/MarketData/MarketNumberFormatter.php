<?php

declare(strict_types=1);

namespace App\Services\MarketData;

use App\Services\Currency\CurrencyService;
use App\Services\Currency\DTOs\CurrencyUnit;

final class MarketNumberFormatter
{
    /**
     * Formats a stored USD figure in the visitor's display currency.
     *
     * Callers pass USD because that is what every monetary column holds; the
     * active unit and its rate are resolved here so public views do not repeat
     * the conversion. Use moneyUsd() for copy that must not vary per session.
     */
    public static function money(?float $usdValue, int $maxDecimals = 2): string
    {
        $currency = app(CurrencyService::class);

        return self::format($currency->convert($usdValue), $currency->active(), $maxDecimals);
    }

    /**
     * The precision a phone row uses, where a price shares one line with the coin's name.
     *
     * Cents on a five-figure price are three characters the name needs more: with them,
     * "Bitcoin" reads "Bit…" at 390px. They are dropped from a thousand up, which is the same
     * figure at one less decimal, and kept below it where they carry the price.
     */
    public static function moneyRow(?float $usdValue): string
    {
        $currency = app(CurrencyService::class);
        $value = $currency->convert($usdValue);

        return self::format($value, $currency->active(), $value !== null && abs($value) >= 1000 ? 0 : 8);
    }

    /**
     * Always renders USD, for crawlable meta copy and other session-independent text.
     */
    public static function moneyUsd(?float $usdValue, int $maxDecimals = 2): string
    {
        return self::format($usdValue, app(CurrencyService::class)->usd(), $maxDecimals);
    }

    public static function format(?float $value, CurrencyUnit $unit, int $maxDecimals = 2): string
    {
        if ($value === null) {
            return '—';
        }

        $abs = abs($value);

        if ($abs >= 1_000_000_000_000) {
            return $unit->wrap(number_format($value / 1_000_000_000_000, 2) . 'T');
        }

        if ($abs >= 1_000_000_000) {
            return $unit->wrap(number_format($value / 1_000_000_000, 2) . 'B');
        }

        if ($abs >= 1_000_000) {
            return $unit->wrap(number_format($value / 1_000_000, 2) . 'M');
        }

        if ($abs >= 1) {
            return $unit->wrap(number_format($value, min(2, $maxDecimals, $unit->decimals)));
        }

        if ($abs >= 0.01) {
            return $unit->wrap(number_format($value, 4));
        }

        return $unit->wrap(rtrim(rtrim(number_format($value, 8, '.', ''), '0'), '.'));
    }

    public static function percent(?float $value): string
    {
        if ($value === null) {
            return '—';
        }

        return number_format($value, 2) . '%';
    }

    public static function compact(?float $value): string
    {
        if ($value === null) {
            return '—';
        }

        if (abs($value) >= 1_000_000) {
            return number_format($value / 1_000_000, 2) . 'M';
        }

        if (abs($value) >= 1_000) {
            return number_format($value / 1_000, 2) . 'K';
        }

        return number_format($value, 2);
    }
}
