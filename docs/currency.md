# Display currency

Visitors can read every figure on the public site in a fiat currency or with BTC (or satoshis, or ETH) as the baseline. Nothing about storage changes: the database stays USD, and the chosen unit is applied when a page renders.

## What is stored where

| Layer | Unit |
|-------|------|
| Provider calls (`vs_currency=usd`, `quotes.USD`, `converted_last.usd`) | USD |
| Columns: `coins.price`, `coins.market_cap`, `coin_tickers.price_usd`, `dex_pairs.liquidity_usd`, `market_globals.*` | USD |
| `currency_rates.rate_per_usd` | multiplier, "units per one USD" |
| What the visitor sees | the unit picked in the header |

Storing one canonical unit keeps rankings, divergence checks, and admin figures comparable. A conversion never round-trips back into a column.

## Rates

`App\Services\Currency\CurrencyRateSyncService` reads CoinGecko `/exchange_rates` through `App\Services\Currency\ExchangeRateProvider` (implemented by `CoinGeckoProvider`) and writes one row per configured unit into `currency_rates`.

CoinGecko quotes every rate against BTC, so each value is divided by the USD value to get the USD multiplier we store. Units listed in `config/currency.php` but missing from the payload are skipped, and a payload without a usable USD rate fails the run instead of writing nonsense.

| Job | Default cadence | Config |
|-----|-----------------|--------|
| `App\Jobs\SyncCurrencyRates` | every `CURRENCY_RATES_INTERVAL` minutes (default 30) | `config/currency.php` |

```bash
./vendor/bin/sail artisan marketdata:sync --only-currencies
```

Rates are cached for `CURRENCY_RATES_CACHE_SECONDS` (default 300) under the `currency.rates` cache key, and the sync flushes that key when it finishes.

## Choosing a unit

`App\Livewire\CurrencySelector` sits in the header and writes the code into the session key `display_currency` (`config/currency.php`). It is rate limited through `App\Support\FormRateLimiter` with the `currency` entry in `config/forms.php`, like every other public mutating action.

Two guards matter:

- A code that is not in `config/currency.php`, or that has no rate row yet, is ignored and the default (`CURRENCY_DEFAULT`, normally USD) stays active. A tampered value cannot force an unconvertible unit.
- Units without a synced rate never appear in the dropdown, so an empty `currency_rates` table degrades to USD only, with the selector hidden.

Changing the unit reloads the page, because prices render in the page component, the market ticker, and layout chrome alike.

## Rendering

`App\Services\MarketData\MarketNumberFormatter::money()` takes a USD figure, asks `App\Services\Currency\CurrencyService` for the active unit and its rate, and formats the result. Public views keep passing USD and need no currency awareness of their own.

- `money()` follows the visitor's choice.
- `moneyUsd()` always renders USD. Meta descriptions and other crawlable copy use it (see below).
- `format()` renders a value that is already in a given unit, which is what the two helpers above call.

Per unit, `config/currency.php` carries the symbol, whether the symbol goes after the figure (`120 sats`), and a decimal floor, so JPY and satoshis render as whole numbers while BTC keeps its precision.

## Crypto baselines: history and percentages

A spot figure only needs today's rate. History does not: scaling a week of dollar prices by one current rate keeps the dollar shape, which would show Bitcoin fluctuating against Bitcoin instead of sitting at 1.00.

Crypto units therefore name a baseline coin in `config/currency.php` (BTC and satoshis use `bitcoin`, ETH uses `ethereum`). We already store that coin's own USD history, so `App\Services\Currency\MarketDisplayService` denominates against it:

- **Chart series:** each point is divided by the baseline price at its own timestamp (the nearest stored point), so `/coins/bitcoin` in BTC is flat at exactly 1.00.
- **Sparklines:** the same, mapped by position, because both series cover the same 7 day window at the same cadence.
- **Percentage changes:** `(1 + coin%) / (1 + baseline%) - 1`, which answers "did it gain on BTC" instead of "did it gain on the dollar". The baseline asset reports 0, and a coin up 10% while BTC is up 5% shows 4.76%.

Views call `MarketDisplayService` (`change()`, `chart()`, `sparkline()`, and `usesBaselineHistory()` for whether a caveat is needed) rather than reading `percent_change_*` and `sparkline_7d` directly.

Sorting the rankings table by a percentage column still sorts on the stored USD values. That ordering holds for any baseline, because dividing every coin by the same baseline factor is monotonic.

## Deliberate limits

- **Fiat units keep USD percentages, and their history is scaled by the current rate.** We store no FX history, so there is no per timestamp euro rate to divide by. The coin page notes that under the chart, and the note disappears for crypto baselines because those are converted properly.
- **BTC dominance stays a USD share percentage**, which is what it means.
- **The Pi Cycle card stays USD** (labelled "Pi Cycle · USD"). The indicator is defined on Bitcoin's dollar moving averages, and the same numbers in BTC would say nothing.
- **Admin (Filament) stays USD**, because it shows the stored columns.

## SEO

Titles, meta descriptions, JSON-LD, canonicals, and the sitemap must not vary per session: a crawler and a visitor have to get the same document for the same URL. `App\Services\Seo\SeoService` therefore formats with `moneyUsd()`, and the unit lives in the session rather than in a query string, so no currency variant of a URL exists to be indexed. See [`docs/seo.md`](seo.md).

## Privacy

The choice is kept in the existing Laravel session, so picking a currency sets no additional cookie. The cookie policy names it under the session cookie entry.

## Tests

- `tests/Feature/CurrencySelectorTest.php`: rate sync arithmetic, failure without a USD rate, session persistence, rejection of unknown or unrated codes, converted figures on the home page, and meta copy staying USD.
- `tests/Feature/CurrencyBaselineTest.php`: an asset priced against itself is flat, relative percentages, satoshi multiplier, fiat fallback, and missing baseline data falling back to the current rate.
- `tests/Unit/MarketNumberFormatterTest.php`: per unit formatting, symbol placement, decimal floors.
