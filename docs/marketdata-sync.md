# Market data sync

Public pages read rankings and coin details from MySQL only. Freshness comes from queued sync jobs.

## Cadence

| Job | Default cadence | Config |
|-----|-----------------|--------|
| `App\Jobs\SyncMarketData` | every `MARKETDATA_MARKETS_INTERVAL` minutes (default 10) | `config/marketdata.php` → `sync.markets_interval_minutes` |
| `App\Jobs\SyncGlobalData` | same interval | same |
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

DexScan uses **GeckoTerminal** (`DexDataProvider` → `GeckoTerminalProvider`). Keep call volume low on the free tier (trending + new pages by default; optional comma-separated `MARKETDATA_DEX_NETWORKS`). Do not call GeckoTerminal from Livewire.

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
```

Every monetary column holds USD. Visitors can display those figures in another currency or in BTC, converted at render time from `currency_rates`: [`docs/currency.md`](currency.md).

Coin detail **Markets** tables (exchange + pair + price + volume) come from CoinGecko tickers. Hot majors (`MARKETDATA_HOT_COINS`, default BTC/ETH/DOGE/LTC/BCH/XRP/ZEC) refresh on the short hot schedule. Visiting any coin also dispatches `SyncCoinTickers` when `tickers_synced_at` is older than `MARKETDATA_TICKERS_STALE_MINUTES` (default 45). The UI `wire:poll`s every 30s from MySQL only.

If CoinGecko returns 404 for a coin id (delisted or remapped), `UnknownProviderCoinCleaner` drops that provider mapping and the coin's ticker rows. When the unknown id belongs to the primary provider (or no provider ids remain), the coin row is deleted (related watchlist and insight rows cascade). The sync run is marked succeeded so Horizon does not retry forever.

The public markets table only lists coins with a non-null `rank`. Unranked rows are excluded because MySQL sorts `NULL` first under `ORDER BY rank ASC`, which pushed demoted coins above Bitcoin.

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

## Production cron

Run the scheduler every minute (Horizon separately under Supervisor):

```cron
* * * * * cd /path/to/app && php artisan schedule:run --no-interaction
```
