# Market data sync

Public pages read rankings and coin details from MySQL only. Freshness comes from queued sync jobs.

## Cadence

| Job | Default cadence | Config |
|-----|-----------------|--------|
| `App\Jobs\SyncMarketData` | every `MARKETDATA_MARKETS_INTERVAL` minutes (default 5) | `config/marketdata.php` → `sync.markets_interval_minutes` |
| `App\Jobs\SyncGlobalData` | same interval | same |
| `horizon:snapshot` | every 5 minutes | Horizon metrics |

Schedule definitions live in [`routes/console.php`](../routes/console.php). Events use `withoutOverlapping()` and `onOneServer()` so a slow run does not stack.

## Queue workers (Horizon)

Horizon processes the Redis queue (`QUEUE_CONNECTION=redis`). Dashboard: `/horizon` (disallowed in `robots.txt`; local open, elsewhere Filament admin session).

Local Sail runs Horizon and cron inside the app container via Supervisor after a image rebuild (`./vendor/bin/sail build --no-cache && ./vendor/bin/sail up -d`).

Manual one-shot:

```bash
./vendor/bin/sail artisan marketdata:sync
./vendor/bin/sail artisan marketdata:sync --queue
```

## Production cron

Run the scheduler every minute (Horizon separately under Supervisor):

```cron
* * * * * cd /path/to/app && php artisan schedule:run --no-interaction
```
