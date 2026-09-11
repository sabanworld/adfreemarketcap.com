# adfreemarketcap docs

Ad-free cryptocurrency market rankings and coin detail pages.

## Product

- **adfreemarketcap** — original-brand standalone app (not a CoinMarketCap clone or proxy).
- Public UI: homepage rankings + coin detail.
- Admin: Filament panel for coins, sync runs, and settings.

## Architecture

- Laravel 12 + Livewire (v4 via Filament) public UI
- Filament 5 admin
- **Laravel Sail** for local Docker (MySQL, Redis, Mailpit)
- Market data via provider strategy: CoinGecko (primary) → CoinPaprika (failover); optional CMC cross-check
- Public pages read from the database only; scheduled/queued jobs sync providers ([`docs/marketdata-sync.md`](marketdata-sync.md))

## Local runtime

Use **Laravel Sail** (`./vendor/bin/sail`). Default published ports in `.env.example` avoid common host conflicts (`APP_PORT=8888`, Redis `6380`, MySQL `13306`).

## Key paths

- Market data services: `app/Services/MarketData/`
- Sync jobs: `app/Jobs/`
- Livewire: `app/Livewire/`
- Filament: `app/Filament/`
- SEO: `app/Services/Seo/`, [`docs/seo.md`](seo.md)
- Design system source: `claude/AdFreeMarketCap Design System/` (see [`docs/design.md`](design.md))
- Public UI CSS: `resources/css/design-system/`, `resources/css/afmc.css`
- Public UI Blade: `resources/views/components/afmc/`, `resources/views/layouts/app.blade.php`
- Agent workflow: [`AGENTS.md`](../AGENTS.md)

## Domain notes

- **SEO** — sitemap, robots, canonicals, and meta: [`docs/seo.md`](seo.md)
- **Design** — brand tokens and public UI mapping: [`docs/design.md`](design.md)
- Document non-obvious sync, failover, and divergence rules here as they land.
- **Market data sync** — schedule cadence, Horizon, cron: [`docs/marketdata-sync.md`](marketdata-sync.md)
