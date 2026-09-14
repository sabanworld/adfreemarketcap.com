# Privacy, cookies, and the legal pages

Public policies are part of the product, not boilerplate. They describe what the code actually does, so a change to data flows is a change to these pages.

The rule these pages follow from is [`docs/principles.md`](principles.md): privacy and honesty break the tie whenever something else pulls the other way.

## Where things live

| Concern | Location |
|---------|----------|
| Page routing and titles | `App\Livewire\LegalPage` (`/legal/{page}`) |
| Page bodies | `resources/views/legal/*.blade.php` |
| Operator identity, retention, processors, authorities | `config/company.php` (`COMPANY_*` in `.env`) |
| Cookie bar | `resources/views/components/afmc/cookie-bar.blade.php` |
| Consent gate and the Google Ads loader | `resources/views/components/afmc/consent.blade.php`, `App\Services\Consent\ConsentService`, `config/consent.php`, `config/google-ads.php` |
| Tests | `tests/Feature/LegalPagesTest.php`, `tests/Feature/ConsentTest.php`, `tests/Feature/CopyStyleTest.php` |

Pages: cookie policy, privacy policy, terms, imprint, risk disclosure, disclosure of interests, accessibility, complaints. All are in the sitemap and indexable.

## What the app actually processes

The policies are written against this list. Keep them in step.

- **Account:** name, email, bcrypt password hash (`users`). Legal basis: contract.
- **Watchlist:** coin references per user (`watchlist_items`). Legal basis: contract.
- **Sessions:** database sessions, `SESSION_LIFETIME` minutes (default 120).
- **Server logs:** IP address, user agent, URL. Claimed retention: `COMPANY_RETENTION_LOG_DAYS` (default 90).
- **Error monitoring:** Sentry (`sentry/sentry-laravel`, `config/sentry.php`, wired in `bootstrap/app.php`). Server-side only when `SENTRY_LARAVEL_DSN` is set. Unhandled exceptions (and optional traces) can include request URL, user agent, and stack traces. `SENTRY_SEND_DEFAULT_PII` defaults to false. Legal basis: legitimate interests.
- **Abuse prevention:** `App\Support\FormRateLimiter` counters and ALTCHA challenge state in Redis, minutes to hours. Legal basis: legitimate interests.
- **Hosting:** DigitalOcean (AMS3, Amsterdam), with servers provisioned and deployed through Ploi. DigitalOcean, LLC is a US company, so the row carries standard contractual clauses plus its EU-US Data Privacy Framework certification even though the data sits in Amsterdam. Ploi is run by WebBuilds B.V. in the Netherlands.
- **Cloudflare** sits in front as reverse proxy and bot filter, so it processes request metadata as a processor.
- **Market data providers** (CoinGecko, CoinPaprika, GeckoTerminal, Bitcoin.com charts, Alternative.me) are called server side by scheduled jobs. No visitor data is sent to them, and public pages never call them per request.
- **Nostr HTTP gateways** (default Divine at `gateway.divine.video`, with Nostr.Band as fallback) are called server side to cache public community notes. Visitors do not contact relays; View opens the note on Primal (`primal.net`).
- **Visitor statistics:** Simple Analytics (`config/analytics.php`, rendered by `resources/views/components/afmc/analytics.blade.php`). Legal basis: legitimate interests. See below.
- **Advert measurement:** the Google Ads conversion tag (`config/google-ads.php`, rendered by `resources/views/components/afmc/consent.blade.php`). Off by default. Legal basis: consent. See below.

Browser storage while the advert tag is off: the session cookie, `XSRF-TOKEN`, the optional `remember_web_*` cookie, and two local storage keys (`afmc-theme`, `afmc-cookies`). Turning the tag on swaps `afmc-cookies` for `afmc-consent` and adds Google's `_gcl_*` and `_gac_*` cookies once accepted. Every item is listed by name in the cookie policy.

## Visitor statistics (Simple Analytics)

The public layout loads `https://scripts.simpleanalyticscdn.com/latest.js`, plus a `noscript` image from `https://queue.simpleanalyticscdn.com/noscript.gif` for browsers without JavaScript. This is the only third-party request a visitor makes unless they accept the advert tag below.

What the choice rests on, and therefore what the policies claim:

- Simple Analytics B.V. is based in Amsterdam and keeps data with EU suppliers.
- It sets no cookie and writes nothing to the device, so [article 5(3)](https://eur-lex.europa.eu/eli/dir/2002/58/oj) consent does not apply and the cookie bar stays a notice.
- It states that it drops every IP address without logging or storing it, derives country from the time zone, and uses no fingerprinting.
- It drops visits from browsers that send Do Not Track. `ANALYTICS_COLLECT_DNT` would turn that off, which the privacy policy currently rules out, so leave it false.

`ANALYTICS_ENABLED` defaults to true only when `APP_ENV=production`, which keeps local page loads and the test suite off the network and out of the figures. Tests cover both states in `tests/Feature/AnalyticsTest.php`.

## Advert measurement (Google Ads)

`AW-…` conversion measurement, off unless `GOOGLE_ADS_ENABLED=true` **and** `GOOGLE_ADS_CONVERSION_ID` is filled. It is the only thing on the site that writes to a visitor's device without being strictly necessary, so it is the only thing behind a consent gate.

How the gate works, and therefore what the policies claim:

- `resources/views/components/afmc/consent.blade.php` runs in `<head>`. It sets Google consent mode v2 to `denied` for `ad_storage`, `ad_user_data`, `ad_personalization`, and `analytics_storage`, then does nothing else.
- The `gtag.js` script tag is built in JavaScript and appended only after Accept. **There is no `<script src>` for googletagmanager.com in the markup**, so a visitor who never answers, or who rejects, makes no request to Google at all.
- The answer lives in the `afmc-consent` local storage key as `{version, advertising, at}`. `CONSENT_VERSION` invalidates stored answers when the purposes change, because consent is specific to what it was given for.
- Reject expires every `_gcl_*` and `_gac_*` cookie across the host and the registrable domain. Withdrawal that only stops the next measurement is not withdrawal.
- Accept and Reject are the same size, the same variant, and sit side by side. Do not make Accept primary.
- The `advertising` row in `config('company.processors')` names Google Ireland Limited with Google LLC, and renders only while the tag ships.

Two conversions are reported, both by name rather than by label, so a component never holds a Google identifier:

| Conversion | Earned in | Reaches the browser through |
|------------|-----------|-----------------------------|
| `registration` | `App\Livewire\Auth\Register::register()` | `afmc-conversion` session flash, replayed by `layouts/app.blade.php` because the redirect ends the component |
| `watchlist` | `App\Livewire\Home::toggleWatch()`, only when `WatchlistService::hasAny()` was false beforehand | `$this->dispatch('afmc-conversion', name: 'watchlist')`, caught by the window listener |

`GOOGLE_ADS_CONVERSION_REGISTRATION` and `GOOGLE_ADS_CONVERSION_WATCHLIST` hold the label only; the account ID is prepended in `ConsentService::conversions()`. An empty label drops that conversion from the map, so the browser cannot report against an action that does not exist. Firing is gated twice over: the label has to be configured and the visitor has to have accepted.

`App\Services\Consent\ConsentService::advertisingEnabled()` is the one answer every surface reads: the head loader, the cookie bar, the footer control, the privacy policy, the cookie policy, and the Why ad-free page. Read it through `@inject` rather than re-checking config in a view, otherwise the pages can describe a site the visitor is not on.

Both states are covered: `tests/Feature/ConsentTest.php` for the mechanics, `tests/Feature/LegalPagesTest.php` for the copy that appears and the copy that has to disappear.

### Checking it in a real browser

PHPUnit can prove the markup is right. It cannot prove that Reject and Accept *look* equally weighted, or that Google is genuinely absent from the network tab. Open `http://localhost:8080/` and walk it after any change to the gate:

1. Before answering, the page loads no `script[src*="googletagmanager"]` and sets no `_gcl_*` cookie.
2. Accept injects `gtag/js?id=AW-…`, writes `_gcl_au`, and pushes a `consent` `update` with all four purposes granted after the `default` denied.
3. "Cookie preferences" in the footer reopens the bar with the same two buttons.
4. Reject removes every `_gcl_*` cookie, flips the stored answer to `advertising: false`, and pushes a denied `update`.
5. A fresh page load as a rejected visitor requests nothing from any Google host.

## Rules that are easy to break

- **Two third-party requests, and the second one is opt-in.** Fonts are self-hosted through Vite (`resources/css/design-system/fonts.css`) and the Material Symbols subset is built by `scripts/build-icon-font.mjs`. Adding a CDN, a font host, or an embed makes the policy pages false.
- **Anything new that writes to the device goes behind the same gate.** It stays off until the visitor accepts, refusing stays as easy as accepting, and both policy pages plus the cookie bar change in the same release.
- **Adverts on the site and adverts pointing at the site are different things.** The pledge is about the first: no ad slots, no sponsored rows, no paid rankings. Buying Google adverts to bring people here does not breach it, but measuring them on the visitor's device does need consent, and the copy has to keep the two apart rather than blur them.
- **The EU ODR platform is gone.** Regulation (EU) 2024/3228 closed it on 20 July 2025 and removed the duty to link to it. Consumers are pointed at national ADR bodies and ACM ConsuWijzer instead.
- **MiCA position.** We publish information and provide no crypto-asset service, so we are not a CASP under Regulation (EU) 2023/1114 and not supervised by AFM or DNB. The terms and risk pages say so, and nothing on the site may read as a marketing communication for an offer of a crypto-asset.
- **Imprint completeness.** Article 5 of the e-Commerce Directive needs legal name, legal form, address, trade register number, and email. `COMPANY_VAT` renders only when set, so fill it in once the company is VAT registered.
- **Processor table.** `config/company.php` leaves `name` empty for vendors that are not appointed yet and the page then shows the category only. Do not invent a vendor to fill a gap.

## When policy text changes

1. Update the page under `resources/views/legal/`.
2. Bump `COMPANY_POLICIES_UPDATED_AT` (or the `policies_updated_at` default) so the "Last updated" line moves.
3. Tell account holders by email if the change affects their rights, as the privacy policy promises.
4. Extend `tests/Feature/LegalPagesTest.php` with the fact you added.
