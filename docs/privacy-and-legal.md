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
| Consent gate, Google Ads loader, ChangeNOW connector | `resources/views/components/afmc/consent.blade.php`, `App\Services\Consent\ConsentService`, `config/consent.php`, `config/google-ads.php`, `config/exchange-widget.php` |
| Exchange widget (placeholder + gated iframe) | `resources/views/components/afmc/exchange-widget.blade.php` |
| Tests | `tests/Feature/LegalPagesTest.php`, `tests/Feature/ConsentTest.php`, `tests/Feature/ExchangeWidgetTest.php`, `tests/Feature/CopyStyleTest.php` |

Pages: cookie policy, privacy policy, terms, imprint, risk disclosure, disclosure of interests, accessibility, complaints. All are in the sitemap and indexable.

## What the app actually processes

The policies are written against this list. Keep them in step.

- **Account:** name, email, bcrypt password hash (`users`). Legal basis: contract.
- **Watchlist:** coin references per user (`watchlist_items`). Legal basis: contract.
- **Sessions:** database sessions, `SESSION_LIFETIME` minutes (default 120).
- **Server logs:** IP address, user agent, URL. Claimed retention: `COMPANY_RETENTION_LOG_DAYS` (default 90).
- **Error monitoring:** Sentry (`sentry/sentry-laravel`, `config/sentry.php`, wired in `bootstrap/app.php`). Server-side only when `SENTRY_LARAVEL_DSN` is set. Unhandled exceptions (and optional traces) can include request URL, user agent, and stack traces. `SENTRY_SEND_DEFAULT_PII` defaults to false. Legal basis: legitimate interests.
- **Abuse prevention:** `App\Support\FormRateLimiter` counters and ALTCHA challenge state in Redis, minutes to hours. Legal basis: legitimate interests.
- **Hosting:** OVHcloud (OVH SAS) in Limburg, Germany (`eu-west-lim`), with servers provisioned and deployed through Ploi. Provider and region are both in the EEA, so the hosting row has no third-country transfer safeguard. Ploi is run by WebBuilds B.V. in the Netherlands.
- **Cloudflare** sits in front as reverse proxy and bot filter, so it processes request metadata as a processor.
- **Market data providers** (CoinGecko, CoinPaprika, GeckoTerminal, Bitcoin.com charts, Alternative.me) are called server side by scheduled jobs. No visitor data is sent to them, and public pages never call them per request.
- **Nostr HTTP gateways** (default Divine at `gateway.divine.video`, with Nostr.Band as fallback) are called server side to cache public community notes. Visitors do not contact relays; View opens the note on Primal (`primal.net`).
- **Visitor statistics:** Plausible (`config/analytics.php`, rendered by `resources/views/components/afmc/analytics.blade.php`, tracker in `resources/js/analytics.js`). Legal basis: legitimate interests. See below.
- **Advert measurement:** the Google Ads conversion tag (`config/google-ads.php`, rendered by `resources/views/components/afmc/consent.blade.php`). Off by default. Legal basis: consent. See below.
- **ChangeNOW swap widget:** affiliate embed on the home page and coin pages (`config/exchange-widget.php`, `<x-afmc.exchange-widget />`). On by default when a `link_id` is set. Legal basis: consent. See below.

Browser storage while nothing optional is enabled: the session cookie, `XSRF-TOKEN`, the optional `remember_web_*` cookie, and two local storage keys (`afmc-theme`, `afmc-cookies`). Turning Google Ads or the exchange widget on swaps `afmc-cookies` for `afmc-consent` and, once accepted, may add Google's `_gcl_*` / `_gac_*` on this domain and ChangeNOW cookies on changenow.io. Every item is listed by name in the cookie policy.

## Visitor statistics (Plausible)

The tracker comes from the `@plausible-analytics/tracker` package and is built by Vite as its own entry, so **the browser fetches no file from Plausible**. The hosted `plausible.io/js/pa-….js` snippet is served through Bunny CDN, which would have put a second company in front of every page load for no benefit. The only request that leaves the browser by default is the measurement `POST` to `https://plausible.io/api/event`. Google and ChangeNOW stay off until Accept.

Keeping it a separate Vite entry matters: bundled into `app.js` it would be unblockable without breaking the site, and "block requests to plausible.io" is one of the ways out the privacy policy offers.

What the choice rests on, and therefore what the policies claim:

- Plausible Insights OÜ is registered in Tartu, Estonia, and processes and stores the figures in Germany ([data policy](https://plausible.io/data-policy), [DPA](https://plausible.io/dpa)). It acts as our processor, so the privacy policy names it as one rather than claiming it receives nothing.
- It sets no cookie and writes nothing to the device, so [article 5(3)](https://eur-lex.europa.eu/eli/dir/2002/58/oj) consent does not apply and the cookie bar stays a notice. The one thing it touches is a **read** of `localStorage.plausible_ignore`, a flag the visitor sets themselves to opt out. The cookie policy names that key.
- Country, region, and city are derived from the IP address, which is then discarded. Daily uniqueness comes from `hash(daily_salt + domain + ip + user_agent)` with the salt deleted every 24 hours.
- Query strings are discarded apart from campaign parameters (`ref`, `utm_*`).
- **Plausible has no Do Not Track check of its own.** We used to skip the tracker when the browser sent Do Not Track or Global Privacy Control. That is off by default now (`ANALYTICS_COLLECT_DNT=true`): the counter runs anyway, and the privacy policy states that. The remaining ways out are `localStorage.plausible_ignore` and blocking `plausible.io`. Set the flag false only if you deliberately want those browser signals to skip the tracker again, and rewrite the policy in the same change.
- `config('analytics.capture')` switches outbound link clicks, file downloads, and form submissions. All three are on, and the privacy policy names each one, so a change here rewrites that paragraph. Scroll depth and time on the page ship with the tracker and cannot be switched off, so they are disclosed too.
- Form capture sends an event when a visitor submits login or register. It carries the event name and the page address, never a field value.

`ANALYTICS_ENABLED` defaults to true only when `APP_ENV=production`, which keeps local page loads and the test suite off the network and out of the figures. Tests cover both states in `tests/Feature/AnalyticsTest.php`.

## Advert measurement (Google Ads)

`AW-…` conversion measurement, off unless `GOOGLE_ADS_ENABLED=true` **and** `GOOGLE_ADS_CONVERSION_ID` is filled. It shares the consent gate with the ChangeNOW widget: both stay off until Accept.

How the gate works, and therefore what the policies claim:

- `resources/views/components/afmc/consent.blade.php` runs in `<head>`. When Google Ads ships, it sets Google consent mode v2 to `denied` for `ad_storage`, `ad_user_data`, `ad_personalization`, and `analytics_storage`, then does nothing else until Accept.
- The `gtag.js` script tag is built in JavaScript and appended only after Accept. **There is no `<script src>` for googletagmanager.com in the markup**, so a visitor who never answers, or who rejects, makes no request to Google at all.
- The answer lives in the `afmc-consent` local storage key as `{version, optional, at}`. `CONSENT_VERSION` invalidates stored answers when the purposes change, because consent is specific to what it was given for.
- Reject expires every `_gcl_*` and `_gac_*` cookie across the host and the registrable domain. Withdrawal that only stops the next measurement is not withdrawal.
- Accept and Reject are the same size, the same variant, and sit side by side. Do not make Accept primary.
- The `advertising` row in `config('company.processors')` names Google Ireland Limited with Google LLC, and renders only while the tag ships.

Two conversions are reported, both by name rather than by label, so a component never holds a Google identifier:

| Conversion | Earned in | Reaches the browser through |
|------------|-----------|-----------------------------|
| `registration` | `App\Livewire\Auth\Register::register()` | `afmc-conversion` session flash, replayed by `layouts/app.blade.php` because the redirect ends the component |
| `watchlist` | `App\Livewire\Home::toggleWatch()`, only when `WatchlistService::hasAny()` was false beforehand | `$this->dispatch('afmc-conversion', name: 'watchlist')`, caught by the window listener |

`GOOGLE_ADS_CONVERSION_REGISTRATION` and `GOOGLE_ADS_CONVERSION_WATCHLIST` hold the label only; the account ID is prepended in `ConsentService::conversions()`. An empty label drops that conversion from the map, so the browser cannot report against an action that does not exist. Firing is gated twice over: the label has to be configured and the visitor has to have accepted.

`App\Services\Consent\ConsentService::required()` is the one answer every surface reads for "does the cookie bar ask a question". `advertisingEnabled()` and `exchangeWidgetEnabled()` decide which optional contacts ship. Read them through `@inject` rather than re-checking config in a view.

Both states are covered: `tests/Feature/ConsentTest.php` for the mechanics, `tests/Feature/LegalPagesTest.php` for the copy that appears and the copy that has to disappear.

### Checking Google Ads in a real browser

1. Before answering, the page loads no `script[src*="googletagmanager"]` and sets no `_gcl_*` cookie.
2. Accept injects `gtag/js?id=AW-…`, writes `_gcl_au`, and pushes a `consent` `update` with all four purposes granted after the `default` denied.
3. "Cookie preferences" in the footer reopens the bar with the same two buttons.
4. Reject removes every `_gcl_*` cookie, flips the stored answer to `optional: false`, and pushes a denied `update`.
5. A fresh page load as a rejected visitor requests nothing from any Google host.

## ChangeNOW swap widget

Affiliate embed on the home page and every coin page, on unless `EXCHANGE_WIDGET_ENABLED=false` or `EXCHANGE_WIDGET_LINK_ID` is empty. Completing a swap may earn a commission; the widget badge and the Disclosure of interests page name that interest.

How the gate works:

- `<x-afmc.exchange-widget />` always renders a labelled section while enabled. Until Accept, it shows a placeholder and a "Cookie preferences" button. **There is no `<iframe src="https://changenow.io…">` and no connector `<script src>` in the markup.**
- After Accept, Alpine builds the iframe and `ConsentService` loads `stepper-connector.js` once.
- Reject removes every exchange host's children and the connector script. Cookies on changenow.io stay until the visitor clears them; the cookie policy says we cannot delete another site's cookies.
- The `exchange` row in `config('company.processors')` renders only while the widget ships.

Covered by `tests/Feature/ExchangeWidgetTest.php` and the legal-page branches in `tests/Feature/LegalPagesTest.php`.

### Checking the widget in a real browser

1. Before answering, Network shows no request to changenow.io and the placeholder is visible.
2. Accept mounts an iframe whose `src` starts with the configured widget base URL and includes `link_id`.
3. Reject empties the host and the placeholder returns.

## Rules that are easy to break

- **Third-party browser contact is either Plausible (default) or opt-in.** Fonts are self-hosted through Vite (`resources/css/design-system/fonts.css`), the Material Symbols subset is built by `scripts/build-icon-font.mjs`, and the Plausible tracker is bundled rather than pulled from a CDN. Adding a CDN, a font host, or an ungated embed makes the policy pages false.
- **Anything new that writes to the device or contacts another company from the browser goes behind the same gate.** It stays off until the visitor accepts, refusing stays as easy as accepting, and both policy pages plus the cookie bar change in the same release.
- **Adverts on the site and adverts pointing at the site are different things.** The pledge is about the first: no ad slots, no sponsored rows, no paid rankings. Buying Google adverts to bring people here does not breach it, but measuring them on the visitor's device does need consent, and the copy has to keep the two apart rather than blur them.
- **Affiliate interest is labelled, not buried.** The ChangeNOW widget and pick card carry a commission badge; the Disclosure of interests page lists the same relationship.
- **The EU ODR platform is gone.** Regulation (EU) 2024/3228 closed it on 20 July 2025 and removed the duty to link to it. Consumers are pointed at national ADR bodies and ACM ConsuWijzer instead.
- **MiCA position.** We publish information and provide no crypto-asset service, so we are not a CASP under Regulation (EU) 2023/1114 and not supervised by AFM or DNB. The terms and risk pages say so, and nothing on the site may read as a marketing communication for an offer of a crypto-asset.
- **Imprint completeness.** Article 5 of the e-Commerce Directive needs legal name, legal form, address, trade register number, and email. `COMPANY_VAT` renders only when set, so fill it in once the company is VAT registered.
- **Processor table.** `config/company.php` leaves `name` empty for vendors that are not appointed yet and the page then shows the category only. Do not invent a vendor to fill a gap.

## When policy text changes

1. Update the page under `resources/views/legal/`.
2. Bump `COMPANY_POLICIES_UPDATED_AT` (or the `policies_updated_at` default) so the "Last updated" line moves.
3. Tell account holders by email if the change affects their rights, as the privacy policy promises.
4. Extend `tests/Feature/LegalPagesTest.php` with the fact you added.
