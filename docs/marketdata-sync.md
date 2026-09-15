# Market data sync

Public pages read rankings and coin details from MySQL only. Freshness comes from queued sync jobs.

## Cadence

| Job | Default cadence | Config |
|-----|-----------------|--------|
| `App\Jobs\SyncMarketData` | every `MARKETDATA_MARKETS_INTERVAL` minutes (default 10) | `config/marketdata.php` → `sync.markets_interval_minutes` |
| `App\Jobs\SyncGlobalData` | same interval | same; also stores 24h market-cap change |
| `App\Jobs\SyncMarketStatus` | every `MARKETDATA_STATUS_INTERVAL` minutes (default 60) | Fear & Greed (Alternative.me), AFMC10, altcoin season |
| `App\Jobs\SyncNinetyDayChanges` | every `MARKETDATA_NINETY_DAY_INTERVAL` minutes (default 60), but a no-op unless a figure has gone stale | 90-day change per sampled coin from CoinGecko `market_chart`; one request per coin |
| `App\Jobs\SyncCoinPlatforms` | every `MARKETDATA_PLATFORMS_INTERVAL_HOURS` hours (default 24) | CoinGecko `/coins/list?include_platform=true` → `coin_platforms` |
| `App\Jobs\SyncNostrFeed` | every `MARKETDATA_NOSTR_INTERVAL` minutes (default 30) | Server-side Nostr indexer; config in `config/nostr.php` |
| `App\Jobs\SyncDexPairs` | every `MARKETDATA_DEX_INTERVAL` minutes (default 5) | `sync.dex_interval_minutes`, `dex_trending_pages`, `dex_new_pages`, `dex_networks` |
| `App\Jobs\SyncHotCoinTickers` | every `MARKETDATA_HOT_TICKERS_INTERVAL` minutes (default 5) | `MARKETDATA_HOT_COINS` slugs via CoinGecko `/coins/{id}/tickers` |
| `App\Jobs\SyncHotCoinCharts` | every `MARKETDATA_HOT_CHARTS_INTERVAL` minutes (default 60) | hot majors: CoinGecko `market_chart` for `intraday` / `short` / `daily` |
| `App\Jobs\SyncTopCoinTickers` | only when `MARKETDATA_TICKERS_TOP_COINS` > 0 (default 0 = off) | every `MARKETDATA_TICKERS_INTERVAL` minutes (default 15) for the top N by rank |
| `App\Jobs\SyncStaleCoinDetails` | every `MARKETDATA_DETAIL_BACKFILL_INTERVAL` minutes (default 60) | `sync.detail_backfill_coins`, `sync.detail_backfill_batch` (default 3), `sync.coin_detail_stale_hours` |
| `App\Jobs\SyncCoinInsights` | every `MARKETDATA_INSIGHTS_INTERVAL_HOURS` hours (default 6) | treasury (CoinGecko) + Bitcoin Pi Cycle / halvings |
| `App\Jobs\SyncCurrencyRates` | every `CURRENCY_RATES_INTERVAL` minutes (default 30) | `config/currency.php`, see [`docs/currency.md`](currency.md) |
| `horizon:snapshot` | every 5 minutes | Horizon metrics |

Schedule definitions live in [`routes/console.php`](../routes/console.php). Events use `withoutOverlapping()` and `onOneServer()` so a slow run does not stack.

### Approximate CoinGecko call budget (defaults)

Scheduled load is dominated by per-coin tickers. With the defaults above:

| Source | Calls each run | Cadence | Approx / hour |
|--------|----------------|---------|---------------|
| `/coins/markets` | 2 pages | every 10m | 12 |
| `/global` | 1 | every 10m | 6 |
| `/coins/{id}/tickers` (hot list) | 7 coins × 1 page | every 5m | 84 |
| Detail backfill | 3 coins × ~2 | every 60m | ~6 |
| `/exchange_rates` | 1 | every 30m | 2 |

Roughly **~110 scheduled calls/hour**, plus on-visit detail/ticker jobs for coins outside the hot list. Homepage prices and ranks for the top 200 still refresh via `/coins/markets`. Raise `MARKETDATA_HOT_TICKERS_INTERVAL` to 10 if ticker volume needs another cut.

DexScan uses **GeckoTerminal** (`DexDataProvider` → `GeckoTerminalProvider`). Keep call volume low on the free tier (trending + new pages by default; optional comma-separated `MARKETDATA_DEX_NETWORKS`). Do not call GeckoTerminal from Livewire. Fresh meme pools can report 24h moves far past `decimal(12, 4)` on `dex_pairs.percent_change_24h`; `DexSyncService` clamps those to ±99,999,999.9999 and continues the run if a single row still fails to write.

Pair and token detail pages dispatch `SyncDexPairDetail` / `SyncDexTokenDetail` when charts, trades, or holders are missing or older than `MARKETDATA_DEX_*_STALE_MINUTES`. After each list sync, `MARKETDATA_DEX_DETAIL_PREWARM` (default 5) trending pairs also get a detail job. Top holders call CoinGecko’s Pro onchain `top_holders` endpoint and soft-fail when the key lacks access.

## Queue workers (Horizon)

Horizon processes the Redis queue (`QUEUE_CONNECTION=redis`). Dashboard: `/horizon` (disallowed in `robots.txt`; local open, elsewhere a Filament admin session). The admin panel sidebar links to it under System → Horizon.

Local Sail runs Horizon and cron inside the app container via Supervisor after a image rebuild (`./vendor/bin/sail build --no-cache && ./vendor/bin/sail up -d`).

Manual one-shot:

```bash
./vendor/bin/sail artisan marketdata:sync
./vendor/bin/sail artisan marketdata:sync --queue
./vendor/bin/sail artisan marketdata:sync --only-dex
./vendor/bin/sail artisan marketdata:sync --only-tickers
./vendor/bin/sail artisan marketdata:sync --only-tickers --coin=bitcoin
./vendor/bin/sail artisan marketdata:sync --only-insights
./vendor/bin/sail artisan marketdata:sync --only-currencies
./vendor/bin/sail artisan marketdata:sync --only-charts
./vendor/bin/sail artisan marketdata:sync --only-charts --coin=bitcoin
./vendor/bin/sail artisan marketdata:sync --only-status
./vendor/bin/sail artisan marketdata:sync --only-platforms
./vendor/bin/sail artisan marketdata:sync --only-nostr
./vendor/bin/sail artisan marketdata:sync --only-nostr --coin=bitcoin
```

### Market status (Fear & Greed, AFMC10, altcoin season)

`SyncMarketStatus` writes `market_status_snapshots` for the homepage Market Status card:

- **Fear & Greed** from Alternative.me (`ALTERNATIVE_ME_BASE_URL`). Credit Alternative.me in the widget. Server-side only.
- **AFMC10** is a market-cap-weighted index of the configured basket (`MARKETDATA_AFMC10`, default BTC/ETH/DOGE/LTC/BCH/XRP/BNB/HBAR/NEAR/SUI). The first successful basket sum is the base so the level starts near 100. Period returns (24h, 7d, 1m/30d, 6m via CoinGecko’s 200d, 1y) are market-cap-weighted averages of each constituent’s matching `percent_change_*` column from the markets sync.
- **Altcoin season** = share of the sampled alts whose `percent_change_90d` beats Bitcoin's. `App\Services\MarketData\AltcoinSeasonSampler` owns membership (top `MARKETDATA_ALTCOIN_SEASON_TOP_N` ranked coins, default 50, excluding Bitcoin and the `MARKETDATA_ALTCOIN_SEASON_EXCLUDE` symbols, which are stables and wrapped or staked derivatives). Thresholds in the UI: below 25 leans Bitcoin season, above 75 leans altcoin season.

### Where the 90-day change comes from

**No ranking endpoint reports a 90-day window.** CoinGecko's `/coins/markets` accepts `1h,24h,7d,14d,30d,200d,1y` in `price_change_percentage` and **silently omits anything else** rather than erroring, so an earlier version that requested `90d` left `coins.percent_change_90d` null for every coin and the altcoin season index read `—` indefinitely. `/coins/{id}` has no 90d field either. Do not put `90d` (or `60d`) back into that request.

`App\Services\MarketData\NinetyDayChangeSyncService` derives the column instead: one `market_chart?days=90` request per sampled coin, oldest point against newest. Consequences worth knowing:

- **It costs one HTTP request per coin** (about 51 for Bitcoin plus a top-50 sample), so it only fetches coins whose figure is older than `MARKETDATA_NINETY_DAY_STALE_HOURS` (default 20). The job is scheduled hourly and nearly every run does nothing: in practice one run a day refreshes the sample and the other 23 are a couple of DB queries.
- **A run works to a time budget** (`MARKETDATA_NINETY_DAY_BUDGET_SECONDS`, default 90) and leaves whatever it did not reach for the next hour. That budget and the job's `$timeout` of 110s must both stay under the queue's `retry_after` (130s for redis in `config/queue.php`). A job that outruns `retry_after` is re-reserved and run by a second worker while the first is still going, which here would mean two workers making the same fifty requests. `tests/Feature/NinetyDayChangeSyncTest.php` asserts that ordering. Setting the budget to 0 pauses fetching without touching the schedule.
- **A coin needs a near-full window to qualify.** Under `MARKETDATA_ALTCOIN_SEASON_MIN_HISTORY_DAYS` days of history (default 80) it is left out of the index rather than compared on a shorter period. Recent listings therefore shrink `altcoin_season_sample_size`, which the card states in its caption.
- **The markets sync must never write this column.** It fills every other `percent_change_*` field, so listing this one would overwrite the derived figure with null every ten minutes. `tests/Feature/NinetyDayChangeSyncTest.php` guards both that and the request parameter.
- Order matters when running by hand: `--only-season` before `--only-status`, or the snapshot scores yesterday's numbers. Passing both to one command already runs them in that order.

### Network filter

`SyncCoinPlatforms` maps CoinGecko platform contracts onto coins that already have a `coin_provider_ids` row. Markets (`Home`) filters with `?network=` via `whereHas('platforms')`. This is not DexScan's `dex_pairs.chain`.

**It runs once a day (`30 */24 * * *`, so 00:30 UTC), and an empty `coin_platforms` table hides the filter completely** rather than showing an empty chip row. A new environment therefore has no network filter until that slot arrives: run `php artisan marketdata:sync --only-platforms` once after deploying, per [First deploy into a new environment](#first-deploy-into-a-new-environment). To check rather than guess, count `coin_platforms` rows and look for the newest `sync_runs` row of type `coin_platforms`.

The chip row is **derived, never hand-listed**: `App\Services\MarketData\NetworkCatalogService` counts ranked coins per `platform_id` (cached 5 minutes, cleared by the platform sync), so a chain reaches the filter only once a coin in the table maps to it, and each option carries that count. `MARKETDATA_NETWORK_PINNED` leads the row in the order given; everything else falls in behind, largest first, and lands in the More menu. `MARKETDATA_NETWORK_HIDDEN` drops a chain entirely.

A `platform_id` is a storage key, not a label, so ids are title-cased for display and `networks.names` in `config/networks.php` only carries the ones that do not convert cleanly (`the-open-network` → TON, `zksync` → zkSync, `xdai` → Gnosis). Add a name there when a new chain shows up reading like a slug. `chipRow()` promotes the chain a reader picked in the More menu into the visible chips, so the active filter is never hidden behind an untouched button.

### Nostr community remarks

`config/nostr.php` lists curated authors (NIP-05 verified Bitcoin voices where possible) plus one feed per AFMC10 coin slug with topic terms (`btc` / `bitcoin`, `eth`, …). `SyncNostrFeed` fetches each author's recent kind-1 notes once through configured HTTP backends (default: Divine gateway, then Nostr.Band), then applies per-coin topic filters. A note is kept only when it matches a topic term as a `#hashtag` / `t` tag, or as a whole word in the leading characters of the note (so a late "Bitcoin" mention in an off-topic rant does not qualify). Notes older than `NOSTR_MAX_AGE_DAYS` are dropped. A single author timeout is reported and skipped; the run fails only when every author request errors. Empty feeds after filtering clear the prior cache so off-topic notes do not linger. Coin detail reads MySQL only; visiting a coin with a configured feed may dispatch a sync when the cache is stale. The visitor browser never opens a relay.

Every monetary column holds USD. Visitors can display those figures in another currency or in BTC, converted at render time from `currency_rates`: [`docs/currency.md`](currency.md).

Coin detail **Markets** tables (exchange + pair + price + volume) come from CoinGecko tickers. Hot majors (`MARKETDATA_HOT_COINS`, default BTC/ETH/DOGE/LTC/BCH/XRP/ZEC) refresh on the short hot schedule. Visiting any coin also dispatches `SyncCoinTickers` when `tickers_synced_at` is older than `MARKETDATA_TICKERS_STALE_MINUTES` (default 45). The UI `wire:poll`s every 30s from MySQL only.

If CoinGecko returns 404 for a coin id (delisted or remapped), `UnknownProviderCoinCleaner` drops that provider mapping and the coin's ticker rows. When the unknown id belongs to the primary provider (or no provider ids remain), the coin row is deleted (related watchlist and insight rows cascade). The sync run is marked succeeded so Horizon does not retry forever.

The public markets table only lists coins with a non-null `rank`. Unranked rows are excluded because MySQL sorts `NULL` first under `ORDER BY rank ASC`, which pushed demoted coins above Bitcoin.

## Percent change precision (1h and 7d)

CoinGecko rounds `price_change_percentage_1h_in_currency` and the 7d field on `/coins/markets` to 0.1. A quiet hour therefore arrives as `0.0` and the table reads `0.00%` for most coins, which is not what CoinMarketCap or the CoinGecko site shows. The 24h figure keeps five decimals, so only 1h and 7d need help.

`App\Services\MarketData\CryptoApisProvider` reads those two percentages from Crypto APIs (`GET /market-data/metadata/assets`, CoinMarketCap-sourced, ranked by market cap) and `MarketSyncService` writes them over the CoinGecko values. Everything else on the row, including rank, price, market cap, volume, 24h, and the sparkline, stays with the provider that owns the ranking.

Rules that keep the overlay honest:

- A ticker that more than one coin in the ranking uses, or that Crypto APIs lists twice, is skipped for every coin involved. A shared symbol never lands on the wrong coin.
- Rows written by the failover provider are left alone, because CoinPaprika already reports both percentages at usable precision.
- A Crypto APIs error is reported and the run continues on the CoinGecko values. The sync never fails because of this call.
- An empty `CRYPTO_APIS_IO_KEY` disables the call entirely.

- A coin Crypto APIs does not list, or one that sits past the requested window, keeps the CoinGecko value. Tokenized funds and several newer listings fall in this group, and a page that comes back short can end the window early.

Settings live under `marketdata.cryptoapis` in [`config/marketdata.php`](../config/marketdata.php): `CRYPTO_APIS_IO_KEY`, `CRYPTO_APIS_IO_PER_PAGE` (50, the endpoint maximum), and `CRYPTO_APIS_IO_MAX_PAGES` (5). The window runs one page deeper than the ranking being synced, because the two market-cap orders drift apart: at the default 200 coins, CoinGecko's top 200 reaches to about index 240 on the Crypto APIs list. That costs 5 calls per markets sync and no CoinGecko credits.

Crypto APIs meters credits per second and answers `429 throughput_limit_reached` when a second burns past the plan hard cap (Starter is 5000). Pages wait `CRYPTO_APIS_IO_PAGE_DELAY_MS` (default 250) between calls, and a 429 is retried up to `CRYPTO_APIS_IO_RETRY_TIMES` with a growing sleep of `CRYPTO_APIS_IO_RETRY_SLEEP_MS` × attempt. If a later page still fails after retries, earlier pages are kept so the top of the ranking still gets precise percentages.

## Coin detail and multi-range charts

Coin descriptions still come from `SyncCoinDetail` (visit + `SyncStaleCoinDetails` backfill).

Price charts live in `coin_chart_series` as three CoinGecko buckets:

| Series | CoinGecko call | UI ranges |
|--------|----------------|-----------|
| `intraday` | `days=1` | 1h, 12h, 1d |
| `short` | `days=90` | 7d, 1m, 3m |
| `daily` | `days=max&interval=daily` | 6m, 1y, 5y, 10y, all |

`SyncCoinCharts` fetches stale series for one coin. Visiting a coin page dispatches it for the active range (and warms `intraday` + `short`). Hot majors (`MARKETDATA_HOT_COINS`) also run on `SyncHotCoinCharts` so deep history is warm without a visit.

Stale windows: `MARKETDATA_CHART_INTRADAY_STALE_MINUTES` (30), `MARKETDATA_CHART_SHORT_STALE_MINUTES` (120), `MARKETDATA_CHART_DAILY_STALE_MINUTES` (720). Syncing `short` also refreshes `coins.chart_7d` for sparklines and currency baselines.

`SyncCoinCharts` is unique per coin + series set for 5 minutes.

## First deploy into a new environment

**Long cadences mean a fresh database has gaps until each job's first slot comes round, and some UI hides itself rather than showing an empty state.** Nothing is wrong when that happens, but nothing fixes it either until the job runs, so seed the slow ones by hand after migrating:

```bash
php artisan migrate --force
php artisan optimize                                  # new config keys
php artisan marketdata:sync --queue                   # rankings + global
php artisan marketdata:sync --only-platforms --queue   # network filter
php artisan marketdata:sync --only-insights --queue    # treasury + Pi Cycle
php artisan marketdata:sync --only-season --only-status --queue  # 90d changes, then the snapshot that scores them
```

Which ones actually need it:

| Surface | Job | First slot | Symptom until then |
|---------|-----|-----------|--------------------|
| Markets network filter | `SyncCoinPlatforms` | `30 */24 * * *`, so **once a day at 00:30 UTC** | The chip row is absent entirely. `NetworkCatalogService` derives chains from `coin_platforms`, and `home.blade.php` renders the filter only when that yields something, so an empty table means no control at all rather than an empty one. |
| Altcoin season | `SyncNinetyDayChanges` then `SyncMarketStatus` | hourly each | The card reads `—` with a zero sample size, because the index needs a 90-day change per coin. Two ticks, so allow up to two hours. |
| Bitcoin treasury / market cycles | `SyncCoinInsights` | `15 */6 * * *`, every 6 hours | Those coin-detail cards are missing. |

Everything else (rankings, global, tickers, charts, currency rates, Nostr) runs at least hourly and fills itself in without help.

The `--queue` flag hands the work to Horizon rather than holding the deploy shell, so Horizon has to be up for those to do anything. Drop it to run inline.

## Production cron

Run the scheduler every minute (Horizon separately under Supervisor):

```cron
* * * * * cd /path/to/app && php artisan schedule:run --no-interaction
```

Scheduled jobs call `->sentryMonitor()` only when `config('sentry.cron_monitoring')` is true (default: production). Local Sail leaves it off so `schedule:work` does not overwrite Sentry monitor schedules or raise missed check-ins. Set `SENTRY_CRON_MONITORING=true` to opt in.
