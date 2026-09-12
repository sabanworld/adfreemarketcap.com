# Market data sync

Public pages read rankings and coin details from MySQL only. Freshness comes from queued sync jobs.

## Cadence

| Job | Default cadence | Config |
|-----|-----------------|--------|
| `App\Jobs\SyncMarketData` | every `MARKETDATA_MARKETS_INTERVAL` minutes (default 5) | `config/marketdata.php` → `sync.markets_interval_minutes` |
| `App\Jobs\SyncGlobalData` | same interval | same |
| `App\Jobs\SyncDexPairs` | every `MARKETDATA_DEX_INTERVAL` minutes (default 5) | `sync.dex_interval_minutes`, `dex_trending_pages`, `dex_new_pages`, `dex_networks` |
| `App\Jobs\SyncTopCoinTickers` | every `MARKETDATA_TICKERS_INTERVAL` minutes (default 5) | top `MARKETDATA_TICKERS_TOP_COINS` coins via CoinGecko `/coins/{id}/tickers` |
| `App\Jobs\SyncStaleCoinDetails` | every `MARKETDATA_DETAIL_BACKFILL_INTERVAL` minutes (default 30) | `sync.detail_backfill_coins`, `sync.detail_backfill_batch`, `sync.coin_detail_stale_hours` |
| `App\Jobs\SyncCoinInsights` | every `MARKETDATA_INSIGHTS_INTERVAL_HOURS` hours (default 6) | treasury (CoinGecko) + Bitcoin Pi Cycle / halvings |
| `App\Jobs\SyncCurrencyRates` | every `CURRENCY_RATES_INTERVAL` minutes (default 30) | `config/currency.php`, see [`docs/currency.md`](currency.md) |
| `horizon:snapshot` | every 5 minutes | Horizon metrics |

Schedule definitions live in [`routes/console.php`](../routes/console.php). Events use `withoutOverlapping()` and `onOneServer()` so a slow run does not stack.

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
```

Every monetary column holds USD. Visitors can display those figures in another currency or in BTC, converted at render time from `currency_rates`: [`docs/currency.md`](currency.md).

Coin detail **Markets** tables (exchange + pair + price + volume) come from CoinGecko tickers. Top coins refresh on a short schedule; visiting a coin also dispatches `SyncCoinTickers` when `tickers_synced_at` is older than `MARKETDATA_TICKERS_STALE_MINUTES` (default 15). The UI `wire:poll`s every 30s from MySQL only.

If CoinGecko returns 404 for a coin id (delisted or remapped), `UnknownProviderCoinCleaner` drops that provider mapping and the coin's ticker rows. When no provider ids remain, the coin row is deleted (related watchlist and insight rows cascade). The sync run is marked succeeded so Horizon does not retry forever.

## Coin detail and the 7 day chart

`coins.chart_7d` and the description come from `SyncCoinDetail`, one provider call per coin. Two things dispatch it:

- Visiting a coin page when `detail_synced_at` is older than `MARKETDATA_DETAIL_STALE_HOURS` (default 6).
- `SyncStaleCoinDetails`, which takes the top `MARKETDATA_DETAIL_BACKFILL_COINS` ranked coins (default 20), picks the `MARKETDATA_DETAIL_BACKFILL_BATCH` stalest of them (default 5, oldest `detail_synced_at` first), and dispatches one `SyncCoinDetail` each.

Without the backfill, a coin that nobody opens never gets a chart, and the coin page falls back to "Chart data will appear after the next detail sync." The batch is deliberately small because each coin costs a separate provider call. Raising `MARKETDATA_DETAIL_BACKFILL_BATCH` or lowering the interval multiplies that call volume against the provider rate limit.

`SyncCoinDetail` is unique per coin id for 5 minutes, so concurrent visitors to the same stale coin queue one job.

## Production cron

Run the scheduler every minute (Horizon separately under Supervisor):

```cron
* * * * * cd /path/to/app && php artisan schedule:run --no-interaction
```
