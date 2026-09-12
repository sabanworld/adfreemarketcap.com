# Privacy, cookies, and the legal pages

Public policies are part of the product, not boilerplate. They describe what the code actually does, so a change to data flows is a change to these pages.

## Where things live

| Concern | Location |
|---------|----------|
| Page routing and titles | `App\Livewire\LegalPage` (`/legal/{page}`) |
| Page bodies | `resources/views/legal/*.blade.php` |
| Operator identity, retention, processors, authorities | `config/company.php` (`COMPANY_*` in `.env`) |
| Cookie bar | `resources/views/components/afmc/cookie-bar.blade.php` |
| Tests | `tests/Feature/LegalPagesTest.php`, `tests/Feature/CopyStyleTest.php` |

Pages: cookie policy, privacy policy, terms, imprint, risk disclosure, disclosure of interests, accessibility, complaints. All are in the sitemap and indexable.

## What the app actually processes

The policies are written against this list. Keep them in step.

- **Account:** name, email, bcrypt password hash (`users`). Legal basis: contract.
- **Watchlist:** coin references per user (`watchlist_items`). Legal basis: contract.
- **Sessions:** database sessions, `SESSION_LIFETIME` minutes (default 120).
- **Server logs:** IP address, user agent, URL. Claimed retention: `COMPANY_RETENTION_LOG_DAYS` (default 90).
- **Abuse prevention:** `App\Support\FormRateLimiter` counters and ALTCHA challenge state in Redis, minutes to hours. Legal basis: legitimate interests.
- **Cloudflare** sits in front as reverse proxy and bot filter, so it processes request metadata as a processor.
- **Market data providers** (CoinGecko, CoinPaprika, GeckoTerminal, Bitcoin.com charts) are called server side by scheduled jobs. No visitor data is sent to them, and public pages never call them per request.
- **Visitor statistics:** Simple Analytics (`config/analytics.php`, rendered by `resources/views/components/afmc/analytics.blade.php`). Legal basis: legitimate interests. See below.

Browser storage is limited to the session cookie, `XSRF-TOKEN`, the optional `remember_web_*` cookie, and two local storage keys (`afmc-theme`, `afmc-cookies`). Every item is listed by name in the cookie policy.

## Visitor statistics (Simple Analytics)

The public layout loads `https://scripts.simpleanalyticscdn.com/latest.js`, plus a `noscript` image from `https://queue.simpleanalyticscdn.com/noscript.gif` for browsers without JavaScript. This is the only third-party request a visitor makes.

What the choice rests on, and therefore what the policies claim:

- Simple Analytics B.V. is based in Amsterdam and keeps data with EU suppliers.
- It sets no cookie and writes nothing to the device, so [article 5(3)](https://eur-lex.europa.eu/eli/dir/2002/58/oj) consent does not apply and the cookie bar stays a notice.
- It states that it drops every IP address without logging or storing it, derives country from the time zone, and uses no fingerprinting.
- It drops visits from browsers that send Do Not Track. `ANALYTICS_COLLECT_DNT` would turn that off, which the privacy policy currently rules out, so leave it false.

`ANALYTICS_ENABLED` defaults to true only when `APP_ENV=production`, which keeps local page loads and the test suite off the network and out of the figures. Tests cover both states in `tests/Feature/AnalyticsTest.php`.

## Rules that are easy to break

- **One third-party request, and it is the counter.** Fonts are self-hosted through Vite (`resources/css/design-system/fonts.css`) and the Material Symbols subset is built by `scripts/build-icon-font.mjs`. Both policy pages state that Simple Analytics is the only company a visitor's browser contacts, so adding a CDN, a font host, an embed, or a second tag makes that sentence false.
- **No advertising, and no storage-based analytics.** If anything that writes to the device is ever added, it stays off until the visitor accepts, refusing stays as easy as accepting, and both policy pages plus the cookie bar change in the same release.
- **The EU ODR platform is gone.** Regulation (EU) 2024/3228 closed it on 20 July 2025 and removed the duty to link to it. Consumers are pointed at national ADR bodies and ACM ConsuWijzer instead.
- **MiCA position.** We publish information and provide no crypto-asset service, so we are not a CASP under Regulation (EU) 2023/1114 and not supervised by AFM or DNB. The terms and risk pages say so, and nothing on the site may read as a marketing communication for an offer of a crypto-asset.
- **Imprint completeness.** Article 5 of the e-Commerce Directive needs legal name, legal form, address, trade register number, and email. `COMPANY_VAT` renders only when set, so fill it in once the company is VAT registered.
- **Processor table.** `config/company.php` leaves `name` empty for vendors that are not appointed yet and the page then shows the category only. Do not invent a vendor to fill a gap.

## When policy text changes

1. Update the page under `resources/views/legal/`.
2. Bump `COMPANY_POLICIES_UPDATED_AT` (or the `policies_updated_at` default) so the "Last updated" line moves.
3. Tell account holders by email if the change affects their rights, as the privacy policy promises.
4. Extend `tests/Feature/LegalPagesTest.php` with the fact you added.
