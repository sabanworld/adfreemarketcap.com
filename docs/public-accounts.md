# Public accounts, Watchlist, DexScan, and legal

## Auth (`web` guard)

- Public accounts use `App\Models\User` on the default `web` guard.
- Filament admin stays on `App\Models\Admin` / `admin` guard, and the two stay separate.
- Routes: `login`, `register` (guest), `logout` (POST + auth), `watchlist` (auth).
- Guests hitting Watchlist are redirected to login (`bootstrap/app.php` `redirectGuestsTo`).
- Login/register are protected with **ALTCHA** (`<x-afmc.altcha />`) and **form rate limits** (`App\Support\FormRateLimiter` + `config/forms.php`). Logout is throttled. Challenge endpoint: `GET /altcha`. Production sits behind Cloudflare; app trusts `X-Forwarded-*` proxies.

## Admin profile (`admin` guard)

`/admin/profile` lets the signed-in admin change name, email address, and password. It is Filament's own `Filament\Auth\Pages\EditProfile`, enabled with `->profile(isSimple: false)` in `App\Providers\Filament\AdminPanelProvider` so it renders inside the panel rather than as a standalone card, and reachable from the user menu.

What that page gives us, so we do not rebuild it:

- **The current password is required** before an email or password change is applied (a name change is not gated).
- New passwords go through `Illuminate\Validation\Rules\Password::default()`, the same defaults the public register form uses, and must be confirmed.
- The email must be unique among `admins`.
- Saving is rate limited per admin (5 attempts) by Filament.
- After a password change Filament refreshes the session password hash, so the current session survives while other sessions are dropped by `AuthenticateSession` (already in the panel middleware).

Admins are created by `Database\Seeders\AdminSeeder`; there is no public admin registration and no password reset route on the panel. Covered by `tests/Feature/AdminProfileTest.php`.

## Admin MFA (authenticator app)

Filament app authentication (TOTP) is enabled on the admin panel via `AppAuthentication::make()->recoverable()` in `AdminPanelProvider`. Admins set it up from `/admin/profile` (or from the forced set-up page when enforcement is on). Recovery codes are stored hashed; the secret is encrypted on `admins.app_authentication_secret`.

Enforcement:

- `config('admin.mfa_required')` defaults to **on when `APP_ENV=production`**, off otherwise. Override with `ADMIN_MFA_REQUIRED=true|false`.
- When required, `App\Http\Middleware\EnsureAdminMultiFactorAuthenticationIsEnabled` sends admins without an authenticator to `/admin/multi-factor-authentication/set-up` before any panel page.
- Login always challenges for a code (or recovery code) once MFA is enabled on the account, in every environment.
- QR generation needs `bacon/bacon-qr-code` (required in `composer.json`).

Covered by `tests/Feature/AdminMfaTest.php`. PHPUnit forces `ADMIN_MFA_REQUIRED=false` so the suite can exercise both open and gated paths.

## Watchlist

- Table: `watchlist_items` (`user_id`, `coin_id`, unique pair).
- Service: `App\Services\Watchlist\WatchlistService`.
- UI: `/watchlist` Livewire page; star toggle via parent Livewire actions (`Home` / `Watchlist`) and `<x-afmc.watch-star>` on Markets and Watchlist.
- Guests who click the star are sent to login.
- SEO: `noindex` on Watchlist; `/login`, `/register`, `/watchlist`, `/logout` in `config/seo.php` robots disallow.

## DexScan

- Tables: `dex_pairs` (upserted by `provider` + `external_id`), `dex_tokens`, `dex_chart_series`, `dex_trades`, `dex_token_holders`.
- Live source: **GeckoTerminal** (`App\Services\MarketData\GeckoTerminalProvider`) via `App\Jobs\SyncDexPairs` / `DexSyncService` for the list, and visit-driven `SyncDexPairDetail` / `SyncDexTokenDetail` for charts, trades, related pools, and holders.
- Cadence: `MARKETDATA_DEX_INTERVAL` minutes (default 5), see [`docs/marketdata-sync.md`](marketdata-sync.md). After each list sync, up to `MARKETDATA_DEX_DETAIL_PREWARM` trending pairs get a detail prewarm job.
- Public pages (MySQL only):
  - `/dexscan` list (trending / gainers / new / liquidity)
  - `/dexscan/pairs/{slug}` pair detail
  - `/dexscan/{network}/{address}` token detail (GeckoTerminal network ids)
- Hero stats on detail pages come from the list sync; charts / trades / holders warm via queued jobs and `wire:poll` while stale.
- Trades are a recent snapshot (about the last 24 hours from GeckoTerminal), not a full history.
- Top holders need CoinGecko onchain access (`GECKOTERMINAL_API_KEY` / `COINGECKO_API_KEY` on the Pro onchain host). Without it the Holders tab stays empty and the rest of the page still works.
- `DexPairSeeder` remains for offline demos; it writes `provider=seed`, network ids, and demo chart series so pair/token detail pages render without calling GeckoTerminal. Production freshness for live pairs comes from the scheduled job.
- Optional: `php artisan marketdata:sync --only-dex` or `--dex`.

### Quality column

`App\Services\MarketData\DexQualityAssessor` derives the tier shown in the Quality column, and it is the only place that decides it. Never re-derive a tier in a Blade view or a Livewire component.

It reads three stored fields, and nothing else:

| Input | Column |
|-------|--------|
| Pool liquidity | `liquidity_usd` |
| How long the pair has existed | `paired_at` |
| Contract / listing check | `audit_status` (`verified` = Markets platform match, `partial` = CoinGecko id only) |

Rules worth knowing before you change a threshold:

- An unverified base token (not on Markets, no CoinGecko id) caps the pair at **high risk** no matter how deep the pool is.
- A `partial` audit (CoinGecko id only) can reach **speculative** at best, never established or blue chip.
- **verified** is set automatically when the base token contract matches a `coin_platforms` row for a ranked Markets coin (`DexAuditStatusResolver`). A CoinGecko id alone stays `partial`.
- A missing `paired_at` or `liquidity_usd` is treated as the worst case, so an absent field can never promote a pair into a calmer tier.
- The tier ignores price performance entirely. Nothing a project can pay for is an input.

The footnote under the table names those three inputs. If you add or drop an input, change that sentence in the same commit, and update `tests/Unit/DexQualityAssessorTest.php` plus the footnote assertions in `tests/Feature/DexScanTest.php`.

## Legal / company

- Operator details: `config/company.php` (defaults match The Saban Company B.V.; override via `COMPANY_*` in `.env`).
- Registered office: Jaap Bijzerweg 19, 3446 CR Woerden, the Netherlands (KvK 91125030).
- Operating company site (linked from the footer and the More drawer under `config('company.person')`): https://the.saban.company/en
- Pages under `/legal/{page}` (`App\Livewire\LegalPage`): cookie, privacy, terms, imprint, risk, interests, accessibility, complaints.
- All legal pages are in the sitemap; imprint surfaces legal form, KvK, VAT (when set), and address from config.
- Policy content, the data-flow inventory, and the EU rules behind each page: [`docs/privacy-and-legal.md`](privacy-and-legal.md).
