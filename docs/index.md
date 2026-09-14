# adfreemarketcap docs

Ad-free cryptocurrency market rankings and coin detail pages.

**Start here:** [`docs/principles.md`](principles.md). Privacy and honesty break every tie in this codebase, and that page is the rule the rest of these docs are downstream of.

## Product

- **adfreemarketcap** is an original-brand standalone app (not a CoinMarketCap clone or proxy).
- Public UI: homepage rankings, coin detail, DexScan, Watchlist (auth), Why ad-free, and legal pages.
- Admin: Filament panel for coins, sync runs, and settings.

## Architecture

- Laravel 12 + Livewire (v4 via Filament) public UI
- Filament 5 admin
- **Laravel Sail** for local Docker (MySQL, Redis, Mailpit)
- Market data via provider strategy: CoinGecko (primary) → CoinPaprika (failover); Crypto APIs supplies the 1h and 7d percentages, which CoinGecko rounds to 0.1
- Homepage Market Status (Fear & Greed, AFMC10, altcoin season), Markets network filter, and optional Nostr community notes on coin pages; see [`docs/marketdata-sync.md`](marketdata-sync.md)
- Public pages read from the database only; scheduled/queued jobs sync providers ([`docs/marketdata-sync.md`](marketdata-sync.md))

## Local runtime

Use **Laravel Sail** (`./vendor/bin/sail`). Default published ports in `.env.example` avoid common host conflicts (`APP_PORT=8888`, Redis `6380`, MySQL `13306`).

Browse the running site from the host at `http://localhost:<APP_PORT>/`, which is `http://localhost:8080/` in the maintainer's setup. Inside the app container the same site is `http://localhost/` on port 80, because published ports do not exist in there. Agents: [`AGENTS.md`](../AGENTS.md) has the longer note on which one to use when.

## Key paths

- Market data services: `app/Services/MarketData/`
- Sync jobs: `app/Jobs/`
- Livewire: `app/Livewire/`
- Filament: `app/Filament/`
- SEO: `app/Services/Seo/`, [`docs/seo.md`](seo.md)
- Design system source: `claude/AdFreeMarketCap Design System (5)/` (see [`docs/design.md`](design.md))
- Public UI CSS: `resources/css/design-system/`, `resources/css/afmc.css`
- Public UI Blade: `resources/views/components/afmc/`, `resources/views/layouts/app.blade.php`
- Agent workflow: [`AGENTS.md`](../AGENTS.md)

## Domain notes

- **First principle**, why privacy and honesty outrank the rest and what that has cost: [`docs/principles.md`](principles.md)
- **SEO**, sitemap, robots, canonicals, and meta: [`docs/seo.md`](seo.md)
- **Design**, brand tokens and public UI mapping: [`docs/design.md`](design.md)
- **Public accounts, DexScan, legal**, watchlist auth, admin profile and MFA, and company imprint: [`docs/public-accounts.md`](public-accounts.md)
- **Privacy, cookies, legal pages**, what we process and the EU rules behind each page: [`docs/privacy-and-legal.md`](privacy-and-legal.md)
- Document non-obvious sync, failover, and divergence rules here as they land.
- **Market data sync**, schedule cadence, Horizon, cron, and the jobs a new environment has to seed by hand: [`docs/marketdata-sync.md`](marketdata-sync.md)
- **Display currency**, USD storage, exchange rates, the header selector: [`docs/currency.md`](currency.md)
