# Instructions for coding agents (this repository)

**Agents:** Before you edit code or give implementation advice for this repo, read this file **end-to-end once per task** (or after a major change of direction). Treat everything below as binding for this workspace; do not rely on memory from other projects or older Filament/Laravel versions.

**Product and domain documentation:** [`docs/index.md`](docs/index.md).

## First principle: privacy and honesty win

**This rule outranks everything else in this file.** When privacy or honesty pulls against growth, revenue, conversion rate, page weight, or the quicker way to ship, the other thing gives way. If a task you have been given requires breaking it, say so before you write the code, and propose the version that does not.

In practice, for any change you make here:

- **A data-flow change is a policy change.** New third-party requests, cookies, storage keys, or personal-data fields mean the privacy page, the cookie page, and their tests move in the same commit. Never in a follow-up.
- **Anything non-essential that writes to a device waits for opt-in**, and refusing stays exactly as easy as accepting. No dark patterns, no pre-ticked anything, no Accept styled louder than Reject.
- **Public claims must be falsifiable.** If a sentence on a public page and the code disagree, one of them is a bug and you fix it now. Prefer a rule, a formula, or a file path over reassurance, and never invent a claim a reader cannot check.
- **Collect nothing that nothing reads**, call providers server side rather than from the visitor's browser, and keep assets self-hosted.
- **Back a promise with a test.** A guarantee no build enforces is a comment.

Full reasoning, the trade-offs already taken, the pre-PR checklist, and the tests that enforce each part: [`docs/principles.md`](docs/principles.md).

## Laravel Sail (required local runtime)

**You are already inside the Sail container.** Your shell runs in the `laravel.test` app container as `root`, with the repository mounted at `/var/www/html`. Check it yourself if you are unsure: `/.dockerenv` exists, the hostname is a container id, and `which php composer yarn` resolves to `/usr/bin/…`.

- **Run PHP/Artisan/Composer/Node commands directly. Never prefix them with `./vendor/bin/sail`.** There is no `docker` binary in this container, so the `sail` wrapper cannot start or shell into anything from here. It fails with a Docker error instead of running your command.
- `./vendor/bin/sail …` is for the **user on the host**. Write it in docs and in instructions you hand back to the user, but do not run it yourself.
- Container commands to run as-is:
  - `php artisan …`
  - `composer …`
  - `php artisan test`
  - `yarn …` / `yarn build`
  - `php artisan marketdata:sync`
  - `php artisan marketdata:sync --only-dex` (DexScan / GeckoTerminal)
  - `php artisan marketdata:sync --only-insights` (Bitcoin treasury + market cycles)
  - `php artisan marketdata:sync --only-tickers` (exchange markets for hot coins)
  - `php artisan marketdata:sync --only-charts` (multi-range charts for hot majors)
  - `php artisan marketdata:sync --only-status` (Fear & Greed, AFMC10, altcoin season)
 - `php artisan marketdata:sync --only-season` (90-day changes the altcoin season index scores; run before `--only-status`)
  - `php artisan marketdata:sync --only-platforms` (coin network platforms for Markets filter)
  - `php artisan marketdata:sync --only-nostr` (Nostr community notes)
  - `php artisan marketdata:call-stats` (this hour / today / this month outbound provider HTTP counts)
  - `php artisan horizon` (if not started via Sail Supervisor)
  - `php artisan schedule:work` (local alternative to container cron)
- Reach sibling services by their **compose service name**, not `localhost` from the host's point of view: `mysql`, `redis`, `meilisearch`, `mailpit`. Host port forwards (`FORWARD_*`) do not apply inside the network.

- **Viewing the running site.** Two different URLs, and which one you want depends on which side of the container you are on:
 - **Browser tools and screenshots: `http://localhost:8080/`.** This is the host's published port for the app container, so it is the URL the browser tooling can actually reach. Use it to check layout, capture screenshots, and drive the UI for real (clicking a consent button, toggling a theme, walking a form). `APP_PORT` in the user's `.env` sets it, which is why it is 8080 here even though `.env.example` ships `8888`.
 - **`curl` and anything you run in this shell: `http://localhost/`** (port 80). The host's published port does not exist inside the container.
 - Do not go port hunting if one fails. The host's own port 80 is a different server and answers `502`, so a wrong guess looks like a broken app. Say what you tried and ask.
 - **Prefer the browser over reasoning about Blade output** for anything visual or interactive. Rendered HTML and a passing test do not tell you whether two buttons look equally weighted, whether a bar is on top of the bottom tab bar, or whether a script only loads after a click.
- Starting or stopping the stack (`sail up -d`, `sail down`) is the user's job on the host. If a service looks down, say so and ask, rather than trying to start Docker from inside.
- The container's PHP is the project's PHP, so it matches CI. Do **not** reason about a host PHP/MySQL version.
- Frontend package manager remains **Yarn**, not npm. Running it in here is what keeps optional native packages (Rollup, esbuild) matched to the Linux image.

## Git

- Prefer short, descriptive branch names (for example `feature/coin-detail-chart`, `fix/sync-failover`). Do not invent ticket-key branch schemes unless the user supplies a name.
- **Do not** create commits or push branches unless the user **explicitly** asks you to commit or push. Resolving merge conflicts, staging files, and leaving a clean working tree for the user to review is fine; finishing with `git commit` or `git push` is not, unless they requested it.

## Environment files (`.env`)

- **Never read, open, search, or rewrite the user’s `.env` file.** It is local-only, may contain secrets, and is not part of the repository. Do not use the Read tool on `.env`; do not `cat`/`grep`/`sed -i`/overwrite it; do not copy another file over it.
- **Append-only is allowed:** you may append new `KEY=value` lines to `.env` (for example `printf '\nKEY=value\n' >> .env`) when a setup step needs local values that are already documented in `.env.example`. Do not modify, reorder, or delete existing lines.
- Before appending, document the key in `.env.example` and wire it through a `config/` file when it is application config. Prefer appending only keys the user has not necessarily set yet (ports, Sail forwards, new feature flags). Never append secrets the user must supply themselves unless they explicitly asked you to write a specific value.
- **Infer configuration from checked-in sources instead of reading `.env`:** `config/*.php`, `.env.example`, and `phpunit.xml`.
- **PHPUnit / feature tests:** run `php artisan test` (or `./vendor/bin/phpunit`) from inside the container. PHPUnit reads `phpunit.xml`, which forces `DB_DATABASE=testing`.
  - **Do not pass `--env=testing` to `artisan test`.** Collision only clears inherited `.env` vars when `--env` is omitted; with `--env=testing`, `RefreshDatabase` can migrate/wipe the local Sail app database.
  - Tests must use the separate MySQL schema `testing` (created by `docker/mysql/create-testing-database.sh`). `Tests\TestCase` refuses to run if the live connection is not `testing`.
  - `phpunit.xml` DB-related `<env … force="true">` entries are required so PHPUnit overrides shell/`.env` values when using `./vendor/bin/phpunit` directly.
- Typical keys for this app include `COINGECKO_API_KEY`, `COINMARKETCAP_API_KEY`, `GECKOTERMINAL_*` / `MARKETDATA_DEX_*`, Sail port forwards (`APP_PORT`, `FORWARD_*`), and provider/sync settings under `config/marketdata.php`.

## Testing

- **Always run PHPUnit via Artisan (without `--env=testing`) or PHPUnit directly:**

  ```bash
  php artisan test
  ```

  Append paths, filters, or groups as needed (for example `php artisan test tests/Feature/SyncMarketDataTest.php`). The host equivalent for the user is `./vendor/bin/sail artisan test`.

- Do not rely on a default `.env` for test runs; `phpunit.xml` + `Tests\TestCase` keep the suite on the `testing` database.

- **Coverage:** treat **80%** as the minimum bar for the repository, but **aim for near 100%** on new and touched code (especially market-data services, aggregators, and non-trivial branches).

- **No live HTTP in tests:** PHPUnit must **never** call real third-party APIs (CoinGecko, CoinPaprika, CoinMarketCap, CryptoCompare, Binance, etc.). Use `Http::fake()` with `Http::preventStrayRequests()` when exercising HTTP clients, or mocks/fakes bound in the container for `testing`. Prefer fixture JSON under `tests/Fixtures/marketdata/` for provider responses.

## Code style (Rector + Duster)

**After every PHP implementation** (feature, fix, refactor), run Rector then Duster before you call the work done. Do not leave either for a follow-up PR.

1. **Rector** applies the configured levels in `rector.php` (dead code, code quality, coding style). Levels start low and rise one at a time across PRs; do not jump the numbers in `rector.php` as part of ordinary feature work.

```bash
vendor/bin/rector
```

Review the diff. Keep intentional PHPStan / Laravel annotations Rector may drop (for example `/** @use HasFactory<…> */`). Re-run until clean, or skip a specific rule in `rector.php` with a short comment when a change is wrong for this codebase.

2. **Duster** for formatting and bundled lint fixes. It wraps **Pint** plus other tools (e.g. TLint), so it is more complete than running Pint alone. Run it after Rector so formatting lands on the refactored code.

```bash
./vendor/bin/duster fix --dirty
```

Do not recommend or run `./vendor/bin/pint` by itself for this repo unless you have a specific reason outside normal workflows.

The repo ships a root `pint.json` that mirrors Duster's standard with one change: `php_unit_test_annotation` uses `prefix`, so test methods stay `public function test_something()`. Duster's bundled standard rewrites them into `/** @test */` docblocks, which PHPUnit deprecates and drops in 12. Do not delete that override, and do not reintroduce `@test` annotations.

## Copy and writing style (public UI, legal pages, docs, comments)

Everything a human reads should sound like a person wrote it: product copy, legal pages, `lang/en/*.php`, `docs/`, `README.md`, code comments, and PR descriptions. Machine-sounding copy is treated as a defect, so fix these patterns in text you touch instead of preserving them.

**Punctuation**

- **No em dashes (`—`) in prose.** Use a comma, a colon, parentheses, or two sentences. The same goes for the en dash used as an aside.
- Do not use `—` as a separator in titles, meta descriptions, or headings. Use `·` (see `lang/en/seo.php`).
- **Allowed exception:** `—` as the placeholder for a missing figure in a table or stat (`MarketNumberFormatter::money(null)`). That is typography, not prose, so leave it alone.
- Straight quotes and apostrophes in code; typographic quotes only inside display copy that already uses them.

**Sentence shapes to avoid**

- **Echo clauses that restate the sentence.** Not "Where he has a partnership, the link says so"; write "Any strategic partnership he has is named on the link itself."
- **Terse confirmation fragments as a closer.** Not "Suggested hardware wallet. We use one."; write one sentence that carries the fact.
- **"Not just X, it's Y"**, "more than just", and other setup-payoff constructions.
- **Rhetorical questions as openers**, "Let's …", "dive in", "unlock", "seamless", "effortless", "leverage" as a verb, and similar marketing filler.
- Trailing summaries that repeat the paragraph in different words.
- **Hollow anti-payola slogans and defensive asides.** Do not write "Ranking cannot be bought", "this is not a paid placement", "nothing in the rankings changes because of it", "it changes nothing about the rankings/pages you see", or close variants. Coins are not shopping for a better rank, so those lines read as empty ad-free marketing. State the concrete interest (commission, partnership, off-site advert) and stop. Never invent virtue-signalling taglines that imply a bribe model that does not exist for the assets on this site. Product promises like "no ads, no paid rankings" on the pledge or Why ad-free page are fine; trailing reassurances bolted onto a widget, cookie bar, or affiliate note are not.
- **Other AI-typical nonsense for this product.** Skip claims that sound important but do not map to how crypto market data works (for example that a coin "cannot pay" for rank, that rankings are "earned not bought" as a homepage badge, or other trust theatre that a reader cannot falsify). Prefer a concrete rule, a formula, or silence.

**Preferences**

- Short declarative sentences, active voice, second person for user-facing copy ("you can delete your account").
- **Name the right party.** Product copy (footer, pledge, picks, miners block, Why ad-free) names the person behind the site through `config('company.person')`, rendered as `:person`, and refers to him as "he" afterwards. Legal pages name the operating company through `config('company.legal_name')`, because that is the party a visitor contracts with and complains to. **Never write "the creator" or "our creator" anywhere**, including docs and config comments. `tests/Feature/CopyStyleTest.php` fails the build on both.
- **Register.** Product copy is relaxed: contractions are welcome, and a personal aside ("he can't stand ad-riddled sites") beats a corporate line. Legal pages stay plain and formal, with no contractions and no jokes. Relaxed never means vaguer: the facts still have to match the code.
- Sentence case for headings. No emoji in product copy, legal pages, or docs.
- Say the concrete thing: name the page, the period, the amount, the provider. Vague reassurance is worse than nothing on legal pages.
- Only claim behaviour the code actually has. If a policy sentence and the codebase disagree, one of the two is a bug.

`tests/Feature/CopyStyleTest.php` enforces the punctuation rule and the banned phrases across `resources/views`, `resources/css`, `lang/`, `docs/`, `README.md`, and this file. Extend the list there when you spot a new tell (including hollow slogans like "ranking cannot be bought" and defensive asides like "this is not a paid placement").

## Accessibility (binding, not a polish pass)

A control that a thumb cannot hit, or a label a reader cannot see, is a broken control. Treat it the way you would treat a wrong price: a defect, fixed in the commit that introduced it. The target level is **WCAG 2.2 level AA**, because `resources/views/legal/accessibility.blade.php` claims exactly that in public, and a claim we do not meet is a lie on a legal page.

**Touch targets**

- **44px minimum on a coarse pointer**, height and width. The absolute floor is 24px (WCAG 2.2 criterion 2.5.8), and going below 44 needs a reason recorded in `tests/Feature/TouchTargetTest::EXCEPTIONS`. Today there are three: two sort headers inside a table pan region, and a clear button that sits inside a 44px field.
- **Every interactive class declares its own floor**, in the coarse-pointer block at the **end** of `resources/css/afmc.css`. Do not rely on the `button, a[href], select, summary` rule in `resources/css/design-system/base.css`, for two reasons that both bite silently: an element selector loses to any class that sets its own `min-height`, and most controls here open with `all: unset`, which resets `min-height` at class specificity and therefore wins no matter which file loads last. That block stays last in the file for the same reason.
- **A size modifier changes a target, it does not create one.** `.afmc-btn` carries its own padding and `min-height`, because `class="afmc-btn afmc-btn--primary"` with no size modifier once shipped 17px tall: readable, and impossible to tap.
- `min-height` does nothing on a **non-replaced inline** element. If a control is a `<label>`, a `<span>`, or anything you reset, give it `inline-flex`/`flex` first.
- New control means a line in that block and a green `TouchTargetTest`, which fails when an interactive rule has no floor.

**Contrast**

- **4.5:1 for text** (1.4.3), **3:1 for a control's own graphics** (1.4.11), **in both themes**. `tests/Unit/DesignTokenContrastTest.php` computes every pair the UI paints straight from `resources/css/design-system/colors.css`, so a ratio written in a comment is not evidence.
- **There is no disabled grey.** Any value light enough to read as inactive is under the floor for a control (`--ink-300` measures 2.42:1). A disabled control keeps readable ink and carries the state some other way: the `.afmc-soon` marker, a hairline strikethrough, plus a `title` and an `aria-label` naming it.
- `--text-disabled` and `--chart-axis` are **non-text only** (rules, axes, an icon whose meaning is also in text). Never a label. The test fails on `--text-disabled` anywhere in `resources/views` or `afmc.css`.
- An **interactive glyph** needs 3:1, which is why an active watch star is `--amber-600` and not `--amber-500`.

**Controls and semantics**

- **A control that cannot work does not pretend.** Either hide it (the share action hides itself when the browser has no share sheet and no clipboard) or ship it visibly unavailable with the reason attached (the alert action). A dead button that looks live is the UI equivalent of an unfalsifiable claim.
- Keep an unavailable control **focusable** (`aria-disabled="true"`, not `disabled`) so a screen reader reaches the reason.
- **Icon-only means labelled:** `aria-label` on the control, `aria-hidden` on the glyph. `<x-afmc.icon>` already sets the latter.
- **A disclosure is a disclosure:** `aria-expanded` plus `aria-controls` pointing at the panel's `id`, and a server-rendered `aria-expanded="false"` before Alpine binds. A market row opens in place; it is never a link, or a tap costs the reader their place in the ranking.
- **State that only shows as a colour or an icon needs a live region.** The copy and share confirmations announce through a `role="status"` element, not through the tick alone.
- Any **horizontally scrolling region** keeps `tabindex="0"` and a label (`data-afmc-tablescroll`), so a keyboard can pan it. Visible focus stays visible: no `outline: none` without a replacement ring.

**Checking it**

- `php artisan test tests/Feature/TouchTargetTest.php tests/Unit/DesignTokenContrastTest.php tests/Feature/LegalPagesTest.php` covers the floors, the ratios, and the public statement.
- **Then look at it in the browser at 390px and 340px** (see the Sail section for which URL). A passing test does not tell you whether two buttons look equally weighted or whether a label clips at the right edge.
- Changing responsive behaviour changes a public claim: update the accessibility statement and its test in the same commit, the way a data-flow change updates the privacy pages.

## JavaScript package manager (Yarn only)

- **Use Yarn exclusively** for installing dependencies, adding/removing packages, and running package scripts. Run **`yarn …`** inside this container (the host equivalent is `./vendor/bin/sail yarn …`) so optional native packages (Rollup, esbuild) match the Sail Linux image.
- **Do not use npm** (`npm install`, `npm ci`, `npm run`, `npx`, …) or other Node package managers (pnpm, bun) in this repository.
- Prefer Yarn equivalents when adapting docs or examples that show npm commands (for example `yarn add <pkg>` instead of `npm install <pkg>`).

## Laravel and project conventions

- **Application bootstrap (Laravel 12):** Routing, middleware aliases, and exception handling live in `bootstrap/app.php` (there is no HTTP `Kernel.php`). Keep new web/Livewire routes and middleware aligned with that file.

- **Public UI:** Livewire (Filament 5 ships Livewire 4) + Blade + Alpine. Prefer `wire:navigate`, targeted `wire:poll`, and Alpine/Chart.js for local chart/sparkline interaction. **Never call market-data providers from Livewire request handlers**. Read from the database; enqueue or schedule sync jobs for freshness.

- **Design system (public UI):** Visual language lives under `claude/AdFreeMarketCap Design System (8)/` (tokens + UI kit). Production CSS tokens are copied to `resources/css/design-system/`; Blade primitives live in `resources/views/components/afmc/` and `resources/css/afmc.css`. Below 820px, primary nav moves to the bottom tab bar and More drawer (horizontal scrolling header nav is a defect). Below 700px a market table is replaced by expandable rows inside `.afmc-board` (`<x-afmc.market-list>` for coins, `<x-afmc.pair-list>` for DexScan), so a phone gets rows that open in place rather than a sideways scroll: details in [`docs/design.md`](docs/design.md). A filter with more than a handful of options is the chip row plus More menu (`<x-afmc.network-filter>`), never a wrapping field of pills.
  - **Logo:** four-bar mark + lowercase wordmark via `<x-afmc.brand-mark>` (header/footer). Static assets live in `public/brand/` (`logo.svg` favicon; mono/inverse variants). Bar heights stay 60/100/38/78; only the second bar is amber. Do not invent a different mark.
  - **White ground / amber accent / green-up red-down only for price**. Do not use amber for market direction or green/red for non-price chrome.
  - **A card is a rule, not a tint.** `--paper-0` and `--paper-1` are both `#FFFFFF`, so a surface painted with `--surface-card` and no `--border-card` is invisible. Dark mode is OLED first (page `#000000`, card `#0B0C0A`, separation by rule, elevation as a 1px lit ring), which is why `--edge-sticky` and every `--shadow-*` step have a dark-theme value of their own.
  - **A surface grounded on `--ink-900` inverts with the theme**, because that token is near-black in light mode and white in dark. Its text comes from `--text-inverse` and `--text-inverse-muted`, never from a fixed ink step: the kit paints `--ink-300` on the pledge band, which measures 2.6:1 once the band turns white. `tests/Unit/DesignTokenContrastTest.php` fails the build on it.
  - **The kit's tokens are not gospel where they contradict a measured floor or a policy.** We keep `--warn-700` / `--text-warn` (the kit has no text step for a warning), the self-hosted `fonts.css` and its variable-font family names in `typography.css`, and the flipping inverse roles above. Each divergence is written down in the token comment and in [`docs/design.md`](docs/design.md); do not "resync" one away without reading why it is there.
  - **Type:** Archivo (display), Public Sans (body), JetBrains Mono (every figure, tabular-nums).
  - **Fonts are self-hosted and stay that way.** `resources/css/design-system/fonts.css` imports the fontsource packages from `node_modules`; no font, style, or image may load from a third-party CDN. A remote request would send visitor IPs to another party and would contradict the privacy and cookie policies.
  - **Opt-in third-party requests are permitted only behind the consent gate.**
  - `<x-afmc.analytics />` in `resources/views/layouts/app.blade.php` starts the Plausible counter from `config/analytics.php`, gated on `ANALYTICS_ENABLED` (on by default only when `APP_ENV=production`). It writes nothing to the device, so it runs for everyone. The tracker itself is the `@plausible-analytics/tracker` package bundled as its own Vite entry (`resources/js/analytics.js`), so no script is fetched from Plausible and only the measurement request leaves the browser. Do not swap it for the hosted `plausible.io/js/…` snippet, which is served through Bunny CDN.
  - `<x-afmc.consent />` in the same layout carries Google Ads (`config/google-ads.php`) and/or the ChangeNOW exchange widget (`config/exchange-widget.php`). Google Ads is gated on `GOOGLE_ADS_ENABLED` plus a filled `GOOGLE_ADS_CONVERSION_ID` (both off by default). The exchange widget is gated on `EXCHANGE_WIDGET_ENABLED` plus a filled `EXCHANGE_WIDGET_LINK_ID`. Neither may appear as a `<script src>` or `<iframe src>` in the markup: the loader only injects them after the visitor accepts. `App\Services\Consent\ConsentService` is the single answer to "does this build need consent", and every surface reads it.
  - **Do not add an ungated third party.** No tag manager, session recorder, heat map, font host, or CDN asset that runs without Accept. Anything new that writes to the device or contacts another company from the browser stays behind the same consent gate, and refusing stays exactly as easy as accepting (same button size, same treatment, no dark patterns).
  - Turning `GOOGLE_ADS_ENABLED` or `EXCHANGE_WIDGET_ENABLED` on switches the privacy policy, the cookie policy, the Why ad-free page, and the cookie bar to the matching set of sentences. Those branches are the policy, so keep them true to the code and cover both states in tests.
  - **Icons:** `<x-afmc.icon name="search" />` (`App\Support\Icons` + `resources/fonts/material-symbols.json`). Never type a Material Symbols ligature into a view, because the shipped font is subset to the icons in that manifest. Adding an icon means adding the name to the manifest and running `yarn icons:build`.
  - When changing Markets or Coin Detail layout, match `ui_kits/web/MarketsScreen.jsx` / `CoinDetailScreen.jsx` and keep pledge + picks disclosure copy honest (no ad-slot styling).
  - Prefer extending `afmc-*` classes over one-off Tailwind slate/indigo utility stacks on public pages.

- **SEO (required for public pages):** Organic discovery is a product requirement, not an afterthought. Keep crawl/index surfaces accurate whenever public routes or content change. Details: [`docs/seo.md`](docs/seo.md).
  - **Ship with every new indexable page:** named route, entry in the dynamic sitemap (`/sitemap.xml` via `App\Services\Seo\SeoService`), unique title + meta description, Open Graph/Twitter fields through `<x-seo-meta>` / layout data, and a **clean `rel=canonical`** (never filter, sort, or pagination query strings).
  - **Do not index** admin (`/admin`), Livewire internals (`/livewire`), or other non-public endpoints. Keep them in `config/seo.php` robots `disallow` and out of the sitemap.
  - **Centralize** titles, descriptions, JSON-LD, sitemap URLs, and `robots.txt` body in `app/Services/Seo/` + `lang/en/seo.php`; do not hardcode divergent meta copy in Blade or Livewire.
  - **Same change:** update `tests/Feature/SeoTest.php` (and unit SEO tests) when sitemap membership, robots rules, or canonical behavior changes.

- **Legal pages and EU compliance:** Public policies live in `resources/views/legal/` behind `App\Livewire\LegalPage`, and every operator detail (legal name, KvK, VAT, addresses, retention periods, processors, supervisory authorities, policy date) comes from `config/company.php`. Details and the audit trail: [`docs/privacy-and-legal.md`](docs/privacy-and-legal.md).
  - **Do not hardcode** company details, dates, or vendor names in a Blade file, and do not invent a processor, certification, or audit we cannot evidence.
  - **A data-flow change is a policy change.** New cookies, storage keys, third-party requests, analytics, trackers, or personal-data fields require the privacy and cookie pages plus `tests/Feature/LegalPagesTest.php` to be updated in the same change.
  - Keep the GDPR structure intact: purpose, legal basis, retention, and recipients per category of data. Keep the ePrivacy line honest, so any non-essential storage needs consent first and refusing stays as easy as accepting.
  - **Visitor statistics:** Plausible is disclosed in the privacy policy (what it measures, IP handling, legal basis, the ways out) and in the cookie policy (why a cookieless counter needs no consent). Keep those sentences true to `config/analytics.php`: the `capture` flags are named one by one in the copy, and `ANALYTICS_COLLECT_DNT` defaults to true so Do Not Track and Global Privacy Control do not skip the counter (the policy says so, and names `plausible_ignore` plus blocking `plausible.io` as the ways out). Name the vendor in `config('company.processors')` rather than in a Blade file.
  - **Optional third parties:** Google Ads measurement and the ChangeNOW swap widget. Legal basis: consent. Neither may load before Accept; Reject is as easy as Accept. Withdrawal deletes Google `_gcl_*` cookies on this domain and removes the ChangeNOW embed (ChangeNOW cookies on changenow.io cannot be cleared from here; the cookie policy states that limit). Processor rows in `config('company.processors')` render only while each contact ships. `tests/Feature/ConsentTest.php` guards the mechanics; `tests/Feature/LegalPagesTest.php` and `tests/Feature/ExchangeWidgetTest.php` guard the copy and the placeholder.
 - **Reporting a conversion:** report it **by name** (`registration`, `watchlist`) through the `afmc-conversion` browser event or the session flash of the same name. Never put a `send_to` value or a conversion label in a Livewire component, a Blade view, or JavaScript you write by hand: labels live in `config('google-ads.conversions')` and are resolved by `ConsentService::conversions()`. Adding a conversion means a new label key there, a name dispatched from the action that earns it, and a test for both the configured and the unconfigured label.
  - **Never reference the EU ODR platform.** It closed on 20 July 2025, so point consumers at national ADR bodies and `ACM ConsuWijzer` instead. Bump `COMPANY_POLICIES_UPDATED_AT` when policy text changes.

- **Auth:** Filament admin uses an **`App\Models\Admin`** on the `admin` guard. Public accounts use **`App\Models\User`** on the `web` guard (login/register/watchlist). Keep those guards separate, and never put public users into the Filament panel.

- **Spam / bot protection (ALTCHA + Cloudflare + form rate limits):**
  - **Cloudflare** sits in front as reverse proxy / WAF / bot fight. Trust forwarded client IPs in `bootstrap/app.php` (`trustProxies`) so rate limits and logs use the real visitor IP, not Cloudflare’s edge.
  - **ALTCHA** is the self-hosted proof-of-work captcha on public mutating forms (login/register today). Challenge: `GET /altcha` (`App\Http\Controllers\AltchaChallengeController`). Verify with `App\Rules\ValidAltcha` / `App\Services\Altcha\AltchaService`. Widget: `<x-afmc.altcha />` + `altcha` npm import in `resources/js/app.js`. Secrets: `ALTCHA_HMAC_SECRET` (+ optional `ALTCHA_HMAC_KEY_SECRET`) in `.env` / `config/altcha.php`. Do **not** use third-party captcha SaaS (Turnstile/reCAPTCHA/hCaptcha) for these forms unless product direction changes.
  - **Rate-limit every public mutating form** (Livewire actions and classic POST controllers). Use `App\Support\FormRateLimiter` with a named key from `config/forms.php` (and/or Laravel `throttle` middleware on HTTP routes). New forms **must** add a limiter entry and call `ensureIsNotRateLimited` / `hit` (clear on success when appropriate). Document limits in `config/forms.php`. Challenge endpoint and logout are throttled too. Idempotent GET search is exempt.
  - Tests: `ALTCHA_TESTING_BYPASS` in `phpunit.xml` lets feature tests submit a fixed payload without solving PoW. Keep `/altcha` in robots `disallow`.

- **Filament (v5):** This admin panel targets **Filament v5** (`filament/filament` ^5 in Composer). Do **not** paste or adapt snippets from Filament v3/v4 docs or forums, because component namespaces, table/form APIs, actions, relation managers, and schema/infolist wiring differ and **v3-era code will break or behave incorrectly**. Prefer matching patterns already in `app/Filament/` and the installed package source under `vendor/filament/`. Admin UI is under `app/Filament/` and wired from `App\Providers\Filament\AdminPanelProvider`.

- **UI labels and translations:** Prefer translation keys (`__()`) for user-facing Filament and public UI copy. Keep `lang/en/*.php` updated in the same change for every new or changed label. Do not hardcode long copy in PHP classes when a lang key fits.

- **English identifiers (schema, API, code):** New tables, columns, indexes, migration filenames, query/body parameter names, JSON keys we own, FormRequest / validation attribute names, PHP variables, and config keys must be **English** (for example `market_cap`, `volume_24h`, `coingecko_id`, `sparkline_7d`). Map external provider payload keys to English columns in provider/sync code.

- **Eloquent model events:** Prefer a dedicated **observer** class (for example under `app/Observers/` or next to the owning domain) and wire it with `#[ObservedBy([SomeObserver::class])]` on the model. Avoid `boot` / `booted` closures on models for lifecycle logic so behavior stays testable and discoverable in one place.

- **Eloquent morphs:** If polymorphic relations are introduced, turn on `Relation::requireMorphMap()` and centralize alias keys in a service provider. Use those **alias keys** in `*_type` columns, not FQCNs.

- **External integrations:** Market-data providers implement a shared contract (for example `App\Services\MarketData\MarketDataProvider`). Resolve primary/failover through an aggregator service. Bind fakes or use `Http::fake()` in `testing` so CI never hits live APIs. Document new provider env keys in `.env.example` and `config/`.

- **Composer test script:** Prefer a `composer test` script that runs `php artisan test` (parallel `-p` optional once the suite is large enough).

- **PHPStan:** Available as a dev dependency when configured; use it when you need static analysis beyond what Duster covers.

- **Laravel helpers preference:** Prefer Laravel helper methods when they fit the use case (for example helpers documented at https://laravel.com/docs/12.x/helpers#available-methods) instead of introducing custom helper functions or wrappers.

- **Readability first:** Optimize for quick recognition by developers. Prefer expressions that read naturally at a glance (for example `! filled($coin)` or `! $coin`) over more verbose checks like `$coin === null` when behavior is equivalent.

- **Strict types + native string APIs:** This codebase uses `declare(strict_types=1);`. Native PHP functions that require a `string` subject, especially `preg_match`, `preg_match_all`, `preg_replace`, `strlen` / `mb_strlen`, `strpos` / `mb_strpos`, and similar, **throw `TypeError`** when given `false`, `null`, or other non-strings. Provider JSON often sends unexpected types for missing fields. In **new and touched code**, never pass untrusted / untyped values into those functions without an explicit guard or a deliberate cast:
  - Prefer Laravel rules (`string`, `regex:…`) which reject non-strings as validation failures instead of 500s.
  - In custom validator closures / `sometimes` callbacks, **early-return** (or `$fail(...)`) when `! is_string($value)` before calling `preg_match`.
  - Only use `(string) $value` when empty-string semantics are intentional; do not cast blindly before regex that must validate format.
  - When fixing or adding such paths, add a regression test with a boolean `false` (or other non-string) payload so CI catches TypeErrors.

### Laravel idioms (Carbon, guard helpers, query ordering)

Apply these conventions in **new and touched code**. Do not revert them when editing nearby lines.

#### Carbon imports

- **Use `Illuminate\Support\Carbon`** for the Carbon class, not `Carbon\Carbon`. Laravel’s subclass respects the app timezone and `Carbon::setTestNow()` in tests.
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

- **Operator precedence:** `! $a === $b` parses as `(!$a) === $b`, not `$a !== $b`. Use an explicit operator or parentheses: `abort_if($a !== $b, 403)` or `abort_if(! ($a === $b), 403)`. Never write `abort_if(! $a === $b, 403)`.
- **String literals:** keep closing quotes **before** trailing parentheses in messages.
- **Multi-statement bodies:** do not collapse `if` blocks that do more than abort/throw.
- **`instanceof` checks:** parenthesize complex conditions when readability helps.

#### Eloquent `orderBy` direction

- **Descending:** `orderByDesc('column')`, not `orderBy('column', 'desc')` / `'DESC'`.
- **Ascending:** `orderBy('column')`, because asc is the default; drop a redundant second `'asc'` / `'ASC'` argument.
- Apply in query builders, relationship definitions, and Filament `relationship()` / `options()` closures consistently.

## Admin authorization

- Keep Filament admin authorization explicit: policies and/or panel auth gates for anything that mutates data (manual sync, coin edits, settings).
- **Every button, table action, bulk action, and admin-triggered job** should be authorized on the server, not only hidden in the UI.
- If Filament Shield / Spatie Permission is added later, document the generate + seed sequence here and cover it with feature tests. Until then, use Filament policies and a seeded admin user.

## Architecture: services and strategy

- **Prefer services for domain logic** that is reused, non-trivial, or crosses layers (sync rules, ranking normalization, divergence checks). Livewire components, Filament resources, and controllers should stay thin and call injected or resolved services.

- **Use the strategy pattern** for market-data providers: one interface, one class per provider (`CoinGeckoProvider`, `CoinPaprikaProvider`, …), selected by an aggregator/orchestrator. Avoid large `match` / `if-else` chains for provider-specific HTTP mapping in jobs or Livewire.

- **Placement:** put market-data code under `app/Services/MarketData/` (providers, aggregator, DTOs). Put sync orchestration in jobs that call those services. Put public SEO (titles, canonicals, sitemap, robots) under `app/Services/Seo/`.

- **Avoid duplicating** the same query or business rule in Livewire, Filament, and tests. Centralize in a service and call it from each consumer.

- **Centralize derived market values** (formatted dominance, % change helpers, divergence thresholds) in a service, and never re-implement the formula in Filament `getStateUsing` closures or Livewire views.

- **Before adding a computed column or JSON field**, search the codebase for an existing service method or model accessor. If none exists, add one in the owning `Services/` directory first, then wire the UI to it.

- **Accuracy vs failover:** one **primary** provider owns live rankings written to `coins`; a **failover** provider is used only when the primary errors or rate-limits; optional cross-check providers feed divergence reports. Do **not** average ranks or market caps across aggregators.

## Repository documentation (`docs/`)

- **`docs/index.md`** is the navigation root for product and domain documentation. Tooling and workflow for agents stay in **this file** (`AGENTS.md`).
- **Always start docs work from the index:** before adding/changing behavior, check `docs/index.md` and follow links to the relevant pages.
- **When to update:** If you change **non-obvious domain rules** (sync cadence, failover order, divergence thresholds, stale-on-visit refresh, **SEO/sitemap/canonical rules**), update or add the relevant page under **`docs/`** in the same change when practical.
- **Duplication:** Do not paste large blocks of application source into docs. Use **file paths** and links to **tests** and **services**.
- **Agents:** For “what does X mean / why” questions, consult **`docs/`** before inferring from Filament or Livewire alone.
