<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Display currency
    |--------------------------------------------------------------------------
    |
    | Market data is stored in USD (providers are queried with vs_currency=usd),
    | so every other unit below is a display conversion applied at render time
    | using the latest rate in the currency_rates table. Rates come from
    | CoinGecko /exchange_rates via App\Services\Currency\CurrencyRateSyncService.
    |
    | Keys must match CoinGecko's exchange-rate keys (lowercase). A unit without
    | a synced rate is hidden from the selector and falls back to the default.
    |
    */

    'default' => env('CURRENCY_DEFAULT', 'usd'),

    'session_key' => 'display_currency',

    'rates_cache_seconds' => (int) env('CURRENCY_RATES_CACHE_SECONDS', 300),

    'rates_interval_minutes' => (int) env('CURRENCY_RATES_INTERVAL', 30),

    /*
    | Rates older than this are still used (stale figures beat no figures) but
    | the selector discloses the age so nobody mistakes them for live rates.
    */
    'rates_stale_minutes' => (int) env('CURRENCY_RATES_STALE_MINUTES', 180),

    /*
    | symbol_after: render the symbol as a suffix ("120 sats") instead of a
    | prefix ("$120"). decimals: floor for sub-1 values, so BTC and ETH keep
    | meaningful digits where fiat would round to zero.
    |
    | baseline: the coin a crypto unit is denominated in. Because we store that
    | coin's own USD history, percentage changes and chart series can be
    | expressed against it point by point (Bitcoin priced in BTC is then flat at
    | 1.00, as it should be) instead of scaled by today's rate. Fiat units have
    | no baseline: we hold no FX history, so their series use the current rate.
    */
    'units' => [
        'usd' => ['label' => 'US Dollar', 'symbol' => '$'],
        'eur' => ['label' => 'Euro', 'symbol' => '€'],
        'gbp' => ['label' => 'British Pound', 'symbol' => '£'],
        'chf' => ['label' => 'Swiss Franc', 'symbol' => 'CHF '],
        'jpy' => ['label' => 'Japanese Yen', 'symbol' => '¥', 'decimals' => 0],
        'cny' => ['label' => 'Chinese Yuan', 'symbol' => 'CN¥'],
        'inr' => ['label' => 'Indian Rupee', 'symbol' => '₹'],
        'krw' => ['label' => 'South Korean Won', 'symbol' => '₩', 'decimals' => 0],
        'cad' => ['label' => 'Canadian Dollar', 'symbol' => 'CA$'],
        'aud' => ['label' => 'Australian Dollar', 'symbol' => 'A$'],
        'brl' => ['label' => 'Brazilian Real', 'symbol' => 'R$'],
        'try' => ['label' => 'Turkish Lira', 'symbol' => '₺'],
        'zar' => ['label' => 'South African Rand', 'symbol' => 'R'],
        'btc' => [
            'label' => 'Bitcoin',
            'symbol' => '₿',
            'crypto' => true,
            'decimals' => 8,
            'baseline' => ['coin' => 'bitcoin', 'multiplier' => 1],
        ],
        'eth' => [
            'label' => 'Ether',
            'symbol' => 'Ξ',
            'crypto' => true,
            'decimals' => 6,
            'baseline' => ['coin' => 'ethereum', 'multiplier' => 1],
        ],
        'sats' => [
            'label' => 'Satoshi',
            'symbol' => ' sats',
            'symbol_after' => true,
            'crypto' => true,
            'decimals' => 0,
            'baseline' => ['coin' => 'bitcoin', 'multiplier' => 100000000],
        ],
    ],

];
