<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Primary and failover providers
    |--------------------------------------------------------------------------
    |
    | Primary owns live rankings written to the coins table. Failover is used
    | only when the primary errors or is rate-limited.
    |
    */

    'primary' => env('MARKETDATA_PRIMARY', 'coingecko'),

    'failover' => env('MARKETDATA_FAILOVER', 'coinpaprika'),

    /*
    |--------------------------------------------------------------------------
    | CoinGecko authentication
    |--------------------------------------------------------------------------
    |
    | Demo (free) keys only work on https://api.coingecko.com/api/v3 with the
    | x-cg-demo-api-key header; paid keys only work on
    | https://pro-api.coingecko.com/api/v3 with x-cg-pro-api-key. CoinGecko's
    | sample URLs spell the query-string form (x_cg_pro_api_key) with
    | underscores, so header names are normalized to their hyphenated form.
    |
    */

    'coingecko' => [
        'base_url' => env('COINGECKO_BASE_URL', 'https://api.coingecko.com/api/v3'),
        'api_key' => env('COINGECKO_API_KEY'),
        'api_key_header' => str_replace('_', '-', (string) env('COINGECKO_API_KEY_HEADER', 'x-cg-demo-api-key')),
    ],

    'coinpaprika' => [
        'base_url' => env('COINPAPRIKA_BASE_URL', 'https://api.coinpaprika.com/v1'),
    ],

    'coinmarketcap' => [
        'base_url' => env('COINMARKETCAP_BASE_URL', 'https://pro-api.coinmarketcap.com'),
        'api_key' => env('COINMARKETCAP_API_KEY'),
    ],

    'geckoterminal' => [
        'base_url' => env('GECKOTERMINAL_BASE_URL', 'https://api.geckoterminal.com/api/v2'),
        'api_key' => env('GECKOTERMINAL_API_KEY', env('COINGECKO_API_KEY')),
        'api_key_header' => str_replace('_', '-', (string) env('GECKOTERMINAL_API_KEY_HEADER', 'x-cg-pro-api-key')),
    ],

    'bitcoin_charts' => [
        'base_url' => env('BITCOIN_CHARTS_BASE_URL', 'https://charts.bitcoin.com/api/v1'),
        'pi_cycle_timespan' => env('BITCOIN_CHARTS_PI_CYCLE_TIMESPAN', '1y'),
        'pi_cycle_limit' => (int) env('BITCOIN_CHARTS_PI_CYCLE_LIMIT', 365),
    ],

    'sync' => [
        'markets_pages' => (int) env('MARKETDATA_MARKETS_PAGES', 2),
        'per_page' => (int) env('MARKETDATA_PER_PAGE', 100),
        'markets_interval_minutes' => (int) env('MARKETDATA_MARKETS_INTERVAL', 10),
        'coin_detail_stale_hours' => (int) env('MARKETDATA_DETAIL_STALE_HOURS', 6),
        'detail_backfill_coins' => (int) env('MARKETDATA_DETAIL_BACKFILL_COINS', 20),
        'detail_backfill_batch' => (int) env('MARKETDATA_DETAIL_BACKFILL_BATCH', 3),
        'detail_backfill_interval_minutes' => (int) env('MARKETDATA_DETAIL_BACKFILL_INTERVAL', 60),
        'divergence_threshold_percent' => (float) env('MARKETDATA_DIVERGENCE_THRESHOLD', 5),
        'dex_interval_minutes' => (int) env('MARKETDATA_DEX_INTERVAL', 5),
        'dex_trending_pages' => (int) env('MARKETDATA_DEX_TRENDING_PAGES', 1),
        'dex_new_pages' => (int) env('MARKETDATA_DEX_NEW_PAGES', 1),
        'dex_networks' => array_values(array_filter(array_map(
            trim(...),
            explode(',', (string) env('MARKETDATA_DEX_NETWORKS', '')),
        ))),
        'insights_interval_hours' => (int) env('MARKETDATA_INSIGHTS_INTERVAL_HOURS', 6),
        'tickers_stale_minutes' => (int) env('MARKETDATA_TICKERS_STALE_MINUTES', 45),
        'tickers_pages' => (int) env('MARKETDATA_TICKERS_PAGES', 1),
        // 0 disables the broad scheduled top-N ticker job; hot coins cover majors.
        'tickers_top_coins' => (int) env('MARKETDATA_TICKERS_TOP_COINS', 0),
        'tickers_interval_minutes' => (int) env('MARKETDATA_TICKERS_INTERVAL', 15),
        'hot_coins' => array_values(array_filter(array_map(
            trim(...),
            explode(',', (string) env(
                'MARKETDATA_HOT_COINS',
                'bitcoin,ethereum,dogecoin,litecoin,bitcoin-cash,ripple,zcash',
            )),
        ))),
        'hot_tickers_interval_minutes' => (int) env('MARKETDATA_HOT_TICKERS_INTERVAL', 5),
    ],

];
