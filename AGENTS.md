# Instructions for coding agents (this repository)

**Agents:** Before you edit code or give implementation advice for this repo, read this file **end-to-end once per task** (or after a major change of direction). Treat everything below as binding for this workspace; do not rely on memory from other projects or older Filament/Laravel versions.

**Product and domain documentation:** [`docs/index.md`](docs/index.md).

## Laravel Sail (required local runtime)

- **Use Sail for PHP/Artisan/Composer/tests in this repo.** Prefer `./vendor/bin/sail …` (or the `sail` shell alias) over host `php` / `composer` once containers are up.
- Start stack: `./vendor/bin/sail up -d`
- Common commands:
  - `./vendor/bin/sail artisan …`
  - `./vendor/bin/sail composer …`
  - `./vendor/bin/sail test` (or `./vendor/bin/sail artisan test`)
  - `./vendor/bin/sail yarn …` / `./vendor/bin/sail yarn build`
  - `./vendor/bin/sail artisan marketdata:sync`
  - `./vendor/bin/sail artisan horizon` (if not started via Sail Supervisor)
  - `./vendor/bin/sail artisan schedule:work` (local alternative to container cron)
- Do **not** assume host PHP/MySQL matches CI/Sail (Sail uses the project’s Docker PHP image).
- Frontend package manager remains **Yarn** (on the host or via Sail’s Node)—not npm.

## Git

- Prefer short, descriptive branch names (for example `feature/coin-detail-chart`, `fix/sync-failover`). Do not invent ticket-key branch schemes unless the user supplies a name.
- **Do not** create commits or push branches unless the user **explicitly** asks you to commit or push. Resolving merge conflicts, staging files, and leaving a clean working tree for the user to review is fine; finishing with `git commit` or `git push` is not, unless they requested it.

## Environment files (`.env`)

- **Never read, open, search, or rewrite the user’s `.env` file.** It is local-only, may contain secrets, and is not part of the repository. Do not use the Read tool on `.env`; do not `cat`/`grep`/`sed -i`/overwrite it; do not copy another file over it.
- **Append-only is allowed:** you may append new `KEY=value` lines to `.env` (for example `printf '\nKEY=value\n' >> .env`) when a setup step needs local values that are already documented in `.env.example`. Do not modify, reorder, or delete existing lines.
- Before appending, document the key in `.env.example` and wire it through a `config/` file when it is application config. Prefer appending only keys the user has not necessarily set yet (ports, Sail forwards, new feature flags)—never append secrets the user must supply themselves unless they explicitly asked you to write a specific value.
- **Infer configuration from checked-in sources instead of reading `.env`:** `config/*.php`, `.env.example`, and `phpunit.xml`.
- **PHPUnit / feature tests:** run `./vendor/bin/sail artisan test` (or `./vendor/bin/phpunit`). PHPUnit reads `phpunit.xml`, which forces `DB_DATABASE=testing`.
  - **Do not pass `--env=testing` to `artisan test`.** Collision only clears inherited `.env` vars when `--env` is omitted; with `--env=testing`, `RefreshDatabase` can migrate/wipe the local Sail app database.
  - Tests must use the separate MySQL schema `testing` (created by `docker/mysql/create-testing-database.sh`). `Tests\TestCase` refuses to run if the live connection is not `testing`.
  - `phpunit.xml` DB-related `<env … force="true">` entries are required so PHPUnit overrides shell/`.env` values when using `./vendor/bin/phpunit` directly.
- Typical keys for this app include `COINGECKO_API_KEY`, `COINMARKETCAP_API_KEY`, Sail port forwards (`APP_PORT`, `FORWARD_*`), and provider/sync settings under `config/marketdata.php` (or similar).

## Testing

- **Always run PHPUnit via Artisan (without `--env=testing`) or PHPUnit directly:**

  ```bash
  ./vendor/bin/sail artisan test
  ```

  Append paths, filters, or groups as needed (for example `./vendor/bin/sail artisan test tests/Feature/SyncMarketDataTest.php`).

- Do not rely on a default `.env` for test runs; `phpunit.xml` + `Tests\TestCase` keep the suite on the `testing` database.

- **Coverage:** treat **80%** as the minimum bar for the repository, but **aim for near 100%** on new and touched code (especially market-data services, aggregators, and non-trivial branches).

- **No live HTTP in tests:** PHPUnit must **never** call real third-party APIs (CoinGecko, CoinPaprika, CoinMarketCap, CryptoCompare, Binance, etc.). Use `Http::fake()` with `Http::preventStrayRequests()` when exercising HTTP clients, or mocks/fakes bound in the container for `testing`. Prefer fixture JSON under `tests/Fixtures/marketdata/` for provider responses.

## Code style (Duster)

**Use Duster only** for formatting and bundled lint fixes—it wraps **Pint** plus other tools (e.g. TLint), so it is more complete than running Pint alone.

```bash
./vendor/bin/duster fix --dirty
```

Do not recommend or run `./vendor/bin/pint` by itself for this repo unless you have a specific reason outside normal workflows.

## JavaScript package manager (Yarn only)

- **Use Yarn exclusively** for installing dependencies, adding/removing packages, and running package scripts. Prefer **`./vendor/bin/sail yarn …`** so optional native packages (Rollup, esbuild) match the Sail Linux image.
- **Do not use npm** (`npm install`, `npm ci`, `npm run`, `npx`, …) or other Node package managers (pnpm, bun) in this repository.
- Prefer Yarn equivalents when adapting docs or examples that show npm commands (for example `./vendor/bin/sail yarn add <pkg>` instead of `npm install <pkg>`).

## Laravel and project conventions

- **Application bootstrap (Laravel 12):** Routing, middleware aliases, and exception handling live in `bootstrap/app.php` (there is no HTTP `Kernel.php`). Keep new web/Livewire routes and middleware aligned with that file.

- **Public UI:** Livewire (Filament 5 ships Livewire 4) + Blade + Alpine. Prefer `wire:navigate`, targeted `wire:poll`, and Alpine/Chart.js for local chart/sparkline interaction. **Never call market-data providers from Livewire request handlers**—read from the database; enqueue or schedule sync jobs for freshness.

- **Design system (public UI):** Visual language lives under `claude/AdFreeMarketCap Design System/` (tokens + UI kit). Production CSS tokens are copied to `resources/css/design-system/`; Blade primitives live in `resources/views/components/afmc/` and `resources/css/afmc.css`.
  - **Logo:** four-bar mark + lowercase wordmark via `<x-afmc.brand-mark>` (header/footer). Static assets live in `public/brand/` (`logo.svg` favicon; mono/inverse variants). Bar heights stay 60/100/38/78; only the second bar is amber. Do not invent a different mark.
  - **Warm paper / amber accent / green-up red-down only for price** — do not use amber for market direction or green/red for non-price chrome.
  - **Type:** Archivo (display), Public Sans (body), JetBrains Mono (every figure, tabular-nums).
  - When changing Markets or Coin Detail layout, match `ui_kits/web/MarketsScreen.jsx` / `CoinDetailScreen.jsx` and keep pledge + picks disclosure copy honest (no ad-slot styling).
  - Prefer extending `afmc-*` classes over one-off Tailwind slate/indigo utility stacks on public pages.

- **SEO (required for public pages):** Organic discovery is a product requirement—not an afterthought. Keep crawl/index surfaces accurate whenever public routes or content change. Details: [`docs/seo.md`](docs/seo.md).
  - **Ship with every new indexable page:** named route, entry in the dynamic sitemap (`/sitemap.xml` via `App\Services\Seo\SeoService`), unique title + meta description, Open Graph/Twitter fields through `<x-seo-meta>` / layout data, and a **clean `rel=canonical`** (never filter, sort, or pagination query strings).
  - **Do not index** admin (`/admin`), Livewire internals (`/livewire`), or other non-public endpoints—keep them in `config/seo.php` robots `disallow` and out of the sitemap.
  - **Centralize** titles, descriptions, JSON-LD, sitemap URLs, and `robots.txt` body in `app/Services/Seo/` + `lang/en/seo.php`; do not hardcode divergent meta copy in Blade or Livewire.
  - **Same change:** update `tests/Feature/SeoTest.php` (and unit SEO tests) when sitemap membership, robots rules, or canonical behavior changes.

- **Auth:** Filament admin uses an **`App\Models\Admin`** (or equivalent) authenticatable model wired in the Filament panel provider. Public market pages are unauthenticated in v1. Do not assume Sanctum/JWT for the public UI unless that is explicitly added later.

- **Filament (v5):** This admin panel targets **Filament v5** (`filament/filament` ^5 in Composer). Do **not** paste or adapt snippets from Filament v3/v4 docs or forums—component namespaces, table/form APIs, actions, relation managers, and schema/infolist wiring differ and **v3-era code will break or behave incorrectly**. Prefer matching patterns already in `app/Filament/` and the installed package source under `vendor/filament/`. Admin UI is under `app/Filament/` and wired from `App\Providers\Filament\AdminPanelProvider`.

- **UI labels and translations:** Prefer translation keys (`__()`) for user-facing Filament and public UI copy. Keep `lang/en/*.php` updated in the same change for every new or changed label. Do not hardcode long copy in PHP classes when a lang key fits.

- **English identifiers (schema, API, code):** New tables, columns, indexes, migration filenames, query/body parameter names, JSON keys we own, FormRequest / validation attribute names, PHP variables, and config keys must be **English** (for example `market_cap`, `volume_24h`, `coingecko_id`, `sparkline_7d`). Map external provider payload keys to English columns in provider/sync code.

- **Eloquent model events:** Prefer a dedicated **observer** class (for example under `app/Observers/` or next to the owning domain) and wire it with `#[ObservedBy([SomeObserver::class])]` on the model. Avoid `boot` / `booted` closures on models for lifecycle logic so behavior stays testable and discoverable in one place.

- **Eloquent morphs:** If polymorphic relations are introduced, turn on `Relation::requireMorphMap()` and centralize alias keys in a service provider. Use those **alias keys** in `*_type` columns, not FQCNs.

- **External integrations:** Market-data providers implement a shared contract (for example `App\Services\MarketData\MarketDataProvider`). Resolve primary/failover through an aggregator service. Bind fakes or use `Http::fake()` in `testing` so CI never hits live APIs. Document new provider env keys in `.env.example` and `config/`.

- **Composer test script:** Prefer a `composer test` script that runs `php artisan test` (parallel `-p` optional once the suite is large enough).

- **PHPStan:** Available as a dev dependency when configured; use it when you need static analysis beyond what Duster covers.

- **Laravel helpers preference:** Prefer Laravel helper methods when they fit the use case (for example helpers documented at https://laravel.com/docs/12.x/helpers#available-methods) instead of introducing custom helper functions or wrappers.

- **Readability first:** Optimize for quick recognition by developers. Prefer expressions that read naturally at a glance (for example `! filled($coin)` or `! $coin`) over more verbose checks like `$coin === null` when behavior is equivalent.

- **Strict types + native string APIs:** This codebase uses `declare(strict_types=1);`. Native PHP functions that require a `string` subject—especially `preg_match`, `preg_match_all`, `preg_replace`, `strlen` / `mb_strlen`, `strpos` / `mb_strpos`, and similar—**throw `TypeError`** when given `false`, `null`, or other non-strings. Provider JSON often sends unexpected types for missing fields. In **new and touched code**, never pass untrusted / untyped values into those functions without an explicit guard or a deliberate cast:
  - Prefer Laravel rules (`string`, `regex:…`) which reject non-strings as validation failures instead of 500s.
  - In custom validator closures / `sometimes` callbacks, **early-return** (or `$fail(...)`) when `! is_string($value)` before calling `preg_match`.
  - Only use `(string) $value` when empty-string semantics are intentional; do not cast blindly before regex that must validate format.
  - When fixing or adding such paths, add a regression test with a boolean `false` (or other non-string) payload so CI catches TypeErrors.

### Laravel idioms (Carbon, guard helpers, query ordering)

Apply these conventions in **new and touched code**. Do not revert them when editing nearby lines.

#### Carbon imports

- **Use `Illuminate\Support\Carbon`** for the Carbon class—not `Carbon\Carbon`. Laravel’s subclass respects the app timezone and `Carbon::setTestNow()` in tests.
- **Keep nesbot/carbon types** when the API is interface- or period-based: `Carbon\CarbonInterface` (type hints), `Carbon\CarbonPeriod` (date ranges). Only the concrete `Carbon` class moves to `Illuminate\Support\Carbon`.
- **Name clashes:** if a file needs both the class and `CarbonInterface`, alias sparingly (for example `use Illuminate\Support\Carbon as SupportCarbon`).

#### Calendar dates vs datetimes (serialization)

- **Calendar-only fields** (when introduced) must be serialized as **`Y-m-d`** (`$date->toDateString()` / `->format('Y-m-d')`), never as ISO-8601 timestamps that can shift the calendar day across timezones.
- **Do not** rely on Eloquent `toArray()` / model JSON alone for those fields: the `date` cast becomes a midnight Carbon, and JSON encoding can shift it to UTC.
- Prefer explicit payload maps when building outbound HTTP/API bodies.
- True **datetimes** (`created_at`, `synced_at`, etc.) may still use ISO-8601 / Carbon JSON serialization when a point in time is intended.

#### Guard helpers (`abort_*`, `throw_*`)

Replace early-exit `if` blocks with Laravel’s guard helpers when the `if` body is only `abort(...)` or `throw ...`:

```php
// Prefer
abort_unless($coin, 404, 'Coin not found');
throw_if($run->failed(), new RuntimeException('Sync already failed.'));

// Avoid
if (! $coin) {
    abort(404, 'Coin not found');
}
```

**Signatures:**

| Helper | When condition is… | Arguments after condition |
|--------|-------------------|---------------------------|
| `abort_if` | true | HTTP status, optional message |
| `abort_unless` | false | HTTP status, optional message |
| `throw_if` | true | exception instance |
| `throw_unless` | false | exception instance |

**Pitfalls (check after every conversion):**

- **Operator precedence:** `! $a === $b` parses as `(!$a) === $b`, not `$a !== $b`. Use an explicit operator or parentheses: `abort_if($a !== $b, 403)` or `abort_if(! ($a === $b), 403)`—never `abort_if(! $a === $b, 403)`.
- **String literals:** keep closing quotes **before** trailing parentheses in messages.
- **Multi-statement bodies:** do not collapse `if` blocks that do more than abort/throw.
- **`instanceof` checks:** parenthesize complex conditions when readability helps.

#### Eloquent `orderBy` direction

- **Descending:** `orderByDesc('column')`—not `orderBy('column', 'desc')` / `'DESC'`.
- **Ascending:** `orderBy('column')`—asc is the default; drop a redundant second `'asc'` / `'ASC'` argument.
- Apply in query builders, relationship definitions, and Filament `relationship()` / `options()` closures consistently.

## Admin authorization

- Keep Filament admin authorization explicit: policies and/or panel auth gates for anything that mutates data (manual sync, coin edits, settings).
- **Every button, table action, bulk action, and admin-triggered job** should be authorized on the server—not only hidden in the UI.
- If Filament Shield / Spatie Permission is added later, document the generate + seed sequence here and cover it with feature tests. Until then, use Filament policies and a seeded admin user.

## Architecture: services and strategy

- **Prefer services for domain logic** that is reused, non-trivial, or crosses layers (sync rules, ranking normalization, divergence checks). Livewire components, Filament resources, and controllers should stay thin and call injected or resolved services.

- **Use the strategy pattern** for market-data providers: one interface, one class per provider (`CoinGeckoProvider`, `CoinPaprikaProvider`, …), selected by an aggregator/orchestrator. Avoid large `match` / `if-else` chains for provider-specific HTTP mapping in jobs or Livewire.

- **Placement:** put market-data code under `app/Services/MarketData/` (providers, aggregator, DTOs). Put sync orchestration in jobs that call those services. Put public SEO (titles, canonicals, sitemap, robots) under `app/Services/Seo/`.

- **Avoid duplicating** the same query or business rule in Livewire, Filament, and tests—centralize in a service and call it from each consumer.

- **Centralize derived market values** (formatted dominance, % change helpers, divergence thresholds) in a service—never re-implement the formula in Filament `getStateUsing` closures or Livewire views.

- **Before adding a computed column or JSON field**, search the codebase for an existing service method or model accessor. If none exists, add one in the owning `Services/` directory first, then wire the UI to it.

- **Accuracy vs failover:** one **primary** provider owns live rankings written to `coins`; a **failover** provider is used only when the primary errors or rate-limits; optional cross-check providers feed divergence reports—do **not** average ranks or market caps across aggregators.

## Repository documentation (`docs/`)

- **`docs/index.md`** is the navigation root for product and domain documentation. Tooling and workflow for agents stay in **this file** (`AGENTS.md`).
- **Always start docs work from the index:** before adding/changing behavior, check `docs/index.md` and follow links to the relevant pages.
- **When to update:** If you change **non-obvious domain rules** (sync cadence, failover order, divergence thresholds, stale-on-visit refresh, **SEO/sitemap/canonical rules**), update or add the relevant page under **`docs/`** in the same change when practical.
- **Duplication:** Do not paste large blocks of application source into docs—use **file paths** and links to **tests** and **services**.
- **Agents:** For “what does X mean / why” questions, consult **`docs/`** before inferring from Filament or Livewire alone.
