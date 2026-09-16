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
        // Shared Pro minute budget with Dex onchain can still 429 under a burst;
        // retry briefly so chart and detail jobs recover instead of failing hard.
        'retry_times' => (int) env('COINGECKO_RETRY_TIMES', 4),
        'retry_sleep_ms' => (int) env('COINGECKO_RETRY_SLEEP_MS', 250),
    ],

    'coinpaprika' => [
        'base_url' => env('COINPAPRIKA_BASE_URL', 'https://api.coinpaprika.com/v1'),
    ],

    'coinmarketcap' => [
        'base_url' => env('COINMARKETCAP_BASE_URL', 'https://pro-api.coinmarketcap.com'),
        'api_key' => env('COINMARKETCAP_API_KEY'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Crypto APIs (percent change precision)
    |--------------------------------------------------------------------------
    |
    | CoinGecko rounds the 1h and 7d percentages on /coins/markets to 0.1, so a
    | quiet hour renders as 0.00%. Crypto APIs serves CoinMarketCap figures at
    | full precision, and only those two columns are taken from it. Leave the
    | key empty to keep the CoinGecko values.
    |
    */

    'cryptoapis' => [
        'base_url' => env('CRYPTO_APIS_IO_BASE_URL', 'https://rest.cryptoapis.io'),
        'api_key' => env('CRYPTO_APIS_IO_KEY'),
        'per_page' => (int) env('CRYPTO_APIS_IO_PER_PAGE', 50),
        'max_pages' => (int) env('CRYPTO_APIS_IO_MAX_PAGES', 5),
        // Credits are metered per second; space pages and retry 429s so a burst
        // does not trip throughput_limit_reached and drop the overlay.
        'page_delay_ms' => (int) env('CRYPTO_APIS_IO_PAGE_DELAY_MS', 250),
        'retry_times' => (int) env('CRYPTO_APIS_IO_RETRY_TIMES', 4),
        'retry_sleep_ms' => (int) env('CRYPTO_APIS_IO_RETRY_SLEEP_MS', 250),
    ],

    'geckoterminal' => [
        // Public free host. Used only when no API key is set.
        'base_url' => env('GECKOTERMINAL_BASE_URL', 'https://api.geckoterminal.com/api/v2'),
        // Falls back to COINGECKO_API_KEY. When set, every Dex call (lists, detail,
        // trades, OHLCV, holders) goes to onchain_base_url so the Pro rate limit applies.
        'api_key' => env('GECKOTERMINAL_API_KEY', env('COINGECKO_API_KEY')),
        'api_key_header' => str_replace('_', '-', (string) env('GECKOTERMINAL_API_KEY_HEADER', 'x-cg-pro-api-key')),
        'onchain_base_url' => env(
            'GECKOTERMINAL_ONCHAIN_BASE_URL',
            'https://pro-api.coingecko.com/api/v3/onchain',
        ),
        // Shared CoinGecko Pro quota can still 429 under a detail burst; retry briefly.
        'retry_times' => (int) env('GECKOTERMINAL_RETRY_TIMES', 4),
        'retry_sleep_ms' => (int) env('GECKOTERMINAL_RETRY_SLEEP_MS', 250),
    ],

    'bitcoin_charts' => [
        'base_url' => env('BITCOIN_CHARTS_BASE_URL', 'https://charts.bitcoin.com/api/v1'),
        'pi_cycle_timespan' => env('BITCOIN_CHARTS_PI_CYCLE_TIMESPAN', '1y'),
        'pi_cycle_limit' => (int) env('BITCOIN_CHARTS_PI_CYCLE_LIMIT', 365),
    ],

    'alternative_me' => [
        'base_url' => env('ALTERNATIVE_ME_BASE_URL', 'https://api.alternative.me'),
    ],

    /*
    |--------------------------------------------------------------------------
    | AFMC10 basket
    |--------------------------------------------------------------------------
    |
    | Market-cap-weighted index of these CoinGecko slug ids (also our coin slugs
    | when the primary provider is CoinGecko). First successful sync stores the
    | basket market-cap sum as the base so the level starts near 100.
    |
    */

    'afmc10' => array_values(array_filter(array_map(
        trim(...),
        explode(',', (string) env(
            'MARKETDATA_AFMC10',
            'bitcoin,ethereum,dogecoin,litecoin,bitcoin-cash,ripple,binancecoin,hedera-hashgraph,near,sui',
        )),
    ))),

    'altcoin_season' => [
        'top_n' => (int) env('MARKETDATA_ALTCOIN_SEASON_TOP_N', 50),

        /*
         * A coin needs roughly a full window of history before its 90-day return means anything.
         * Below this many days of price data it is left out of the index rather than compared on
         * a shorter period than everything around it.
         */
        'minimum_history_days' => (int) env('MARKETDATA_ALTCOIN_SEASON_MIN_HISTORY_DAYS', 80),

        'exclude_symbols' => array_values(array_filter(array_map(
            strtoupper(...),
            array_map(trim(...), explode(',', (string) env(
                'MARKETDATA_ALTCOIN_SEASON_EXCLUDE',
                'USDT,USDC,DAI,FDUSD,USDE,USDS,BUSD,TUSD,USDD,PYUSD,EURC,WBTC,WETH,STETH,WSTETH,WEETH,CBETH,RETH,TBTC,BTCB',
            ))),
        ))),
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
        'dex_detail_stale_minutes' => (int) env('MARKETDATA_DEX_DETAIL_STALE_MINUTES', 5),
        'dex_trades_stale_minutes' => (int) env('MARKETDATA_DEX_TRADES_STALE_MINUTES', 5),
        'dex_holders_stale_minutes' => (int) env('MARKETDATA_DEX_HOLDERS_STALE_MINUTES', 60),
        'dex_chart_intraday_stale_minutes' => (int) env('MARKETDATA_DEX_CHART_INTRADAY_STALE_MINUTES', 30),
        'dex_chart_short_stale_minutes' => (int) env('MARKETDATA_DEX_CHART_SHORT_STALE_MINUTES', 120),
        'dex_chart_daily_stale_minutes' => (int) env('MARKETDATA_DEX_CHART_DAILY_STALE_MINUTES', 720),
        'dex_detail_prewarm' => (int) env('MARKETDATA_DEX_DETAIL_PREWARM', 5),
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
        'chart_intraday_stale_minutes' => (int) env('MARKETDATA_CHART_INTRADAY_STALE_MINUTES', 30),
        'chart_short_stale_minutes' => (int) env('MARKETDATA_CHART_SHORT_STALE_MINUTES', 120),
        'chart_daily_stale_minutes' => (int) env('MARKETDATA_CHART_DAILY_STALE_MINUTES', 720),
        'hot_charts_interval_minutes' => (int) env('MARKETDATA_HOT_CHARTS_INTERVAL', 60),
        'market_status_interval_minutes' => (int) env('MARKETDATA_STATUS_INTERVAL', 60),
        /*
         * The 90-day change behind the altcoin season index costs one chart request per sampled
         * coin. The job runs hourly but only touches coins whose figure is older than the stale
         * window, so in practice one run a day does the work and the rest are no-ops. The budget
         * caps how long a single run may spend fetching: it must stay clear of the queue's
         * retry_after (130s for redis) or a slow run gets handed to a second worker as well.
         * Whatever a run does not reach stays stale and is picked up an hour later.
         */
        'ninety_day_interval_minutes' => (int) env('MARKETDATA_NINETY_DAY_INTERVAL', 60),
        'ninety_day_stale_hours' => (int) env('MARKETDATA_NINETY_DAY_STALE_HOURS', 20),
        'ninety_day_budget_seconds' => (int) env('MARKETDATA_NINETY_DAY_BUDGET_SECONDS', 90),
        'platforms_interval_hours' => (int) env('MARKETDATA_PLATFORMS_INTERVAL_HOURS', 24),
        'nostr_interval_minutes' => (int) env('MARKETDATA_NOSTR_INTERVAL', 30),
    ],

];
