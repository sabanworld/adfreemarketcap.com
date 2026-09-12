# Public accounts, Watchlist, DexScan, and legal

## Auth (`web` guard)

- Public accounts use `App\Models\User` on the default `web` guard.
- Filament admin stays on `App\Models\Admin` / `admin` guard, and the two stay separate.
- Routes: `login`, `register` (guest), `logout` (POST + auth), `watchlist` (auth).
- Guests hitting Watchlist are redirected to login (`bootstrap/app.php` `redirectGuestsTo`).
- Login/register are protected with **ALTCHA** (`<x-afmc.altcha />`) and **form rate limits** (`App\Support\FormRateLimiter` + `config/forms.php`). Logout is throttled. Challenge endpoint: `GET /altcha`. Production sits behind Cloudflare; app trusts `X-Forwarded-*` proxies.

## Watchlist

- Table: `watchlist_items` (`user_id`, `coin_id`, unique pair).
- Service: `App\Services\Watchlist\WatchlistService`.
- UI: `/watchlist` Livewire page; star toggle via `App\Livewire\WatchToggle` on Markets (and Watchlist).
- Guests who click the star are sent to login.
- SEO: `noindex` on Watchlist; `/login`, `/register`, `/watchlist`, `/logout` in `config/seo.php` robots disallow.

## DexScan

- Table: `dex_pairs` (upserted by `provider` + `external_id`).
- Live source: **GeckoTerminal** (`App\Services\MarketData\GeckoTerminalProvider`) via `App\Jobs\SyncDexPairs` / `DexSyncService`.
- Cadence: `MARKETDATA_DEX_INTERVAL` minutes (default 5), see [`docs/marketdata-sync.md`](marketdata-sync.md).
- Public page: `/dexscan` reads MySQL only (trending / gainers / new / liquidity tabs).
- `DexPairSeeder` remains for offline demos; production freshness comes from the scheduled job.
- Optional: `php artisan marketdata:sync --only-dex` or `--dex`.

## Legal / company

- Operator details: `config/company.php` (defaults match Saban Company B.V.; override via `COMPANY_*` in `.env`).
- Registered office: Jaap Bijzerweg 19, 3446 CR Woerden, the Netherlands (KvK 91125030).
- Host / creator site: https://the.saban.company/en
- Pages under `/legal/{page}` (`App\Livewire\LegalPage`): cookie, privacy, terms, imprint, risk, interests, accessibility, complaints.
- All legal pages are in the sitemap; imprint surfaces legal form, KvK, VAT (when set), and address from config.
- Policy content, the data-flow inventory, and the EU rules behind each page: [`docs/privacy-and-legal.md`](privacy-and-legal.md).
