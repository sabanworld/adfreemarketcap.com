# adfreemarketcap

Ad-free cryptocurrency market rankings (homepage + coin detail) with a Filament admin. Local development runs on **Laravel Sail**.

## Requirements

- Docker Desktop (or compatible engine)
- Composer (host) only to bootstrap Sail if needed

## Setup

```bash
cp .env.example .env
composer install
# If host ports collide, keep the APP_PORT / FORWARD_* values from .env.example
./vendor/bin/sail up -d
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate --seed
./vendor/bin/sail yarn install
./vendor/bin/sail yarn build
./vendor/bin/sail artisan marketdata:sync
```

App: [http://localhost:8888](http://localhost:8888) (or your `APP_PORT`)  
Admin: [http://localhost:8888/admin](http://localhost:8888/admin) with `admin@adfreemarketcap.com` / `password`  
Horizon: [http://localhost:8888/horizon](http://localhost:8888/horizon) (requires `QUEUE_CONNECTION=redis`)

If default host ports are busy, copy the `APP_PORT` / `FORWARD_*` values from `.env.example`.

Set `QUEUE_CONNECTION=redis` in `.env` (see `.env.example`). Optional: `COINGECKO_API_KEY` (and `COINMARKETCAP_API_KEY`).

### Horizon + scheduled sync

After pulling Sail Docker customizations (`docker/8.5`), rebuild so Supervisor starts Horizon and cron:

```bash
./vendor/bin/sail build --no-cache
./vendor/bin/sail up -d
```

Cron runs `schedule:run` every minute; scheduled jobs dispatch `SyncMarketData` / `SyncGlobalData` on the Redis queue (default every 5 minutes). Details: [`docs/marketdata-sync.md`](docs/marketdata-sync.md).

One-shot sync without the queue:

```bash
./vendor/bin/sail artisan marketdata:sync
```

## Tests

```bash
./vendor/bin/sail artisan test
```

## Frontend

Use **Yarn** only, not npm, and prefer `./vendor/bin/sail yarn …` so native deps match the Sail Linux image.

Fonts and icons are self-hosted so no visitor request reaches a third-party CDN. The icon font is a subset of Material Symbols Rounded; after adding an icon name to `resources/fonts/material-symbols.json`, rebuild it:

```bash
./vendor/bin/sail yarn icons:build
```

## Docs

- Agent rules: [`AGENTS.md`](AGENTS.md)
- Product docs: [`docs/index.md`](docs/index.md)
- Privacy, cookies, and legal pages: [`docs/privacy-and-legal.md`](docs/privacy-and-legal.md)
