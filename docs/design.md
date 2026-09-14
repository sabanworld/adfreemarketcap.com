# Public UI design

The production front-end follows the **AdFreeMarketCap Design System** under `claude/AdFreeMarketCap Design System (5)/` (reference kit).

## Mapping

| Kit | Production |
|-----|------------|
| `tokens/*.css` | `resources/css/design-system/` (keep self-hosted `fonts.css`; do not copy the kit’s Google Fonts CDN) |
| Component look (React kit) | `resources/css/afmc.css` + `resources/views/components/afmc/` |
| `App.jsx` shell | `resources/views/layouts/app.blade.php` (skip link, header, ticker, `#afmc-main`, footer, bottom tab bar, nav drawer, cookie bar, theme) |
| `MarketsScreen.jsx` | `resources/views/livewire/home.blade.php` + `App\Livewire\Home` |
| `CoinDetailScreen.jsx` | `resources/views/livewire/coin-show.blade.php` + `App\Livewire\CoinShow` |
| `DexScanScreen.jsx` | `resources/views/livewire/dex-scan.blade.php` + `App\Livewire\DexScan` |
| `WatchlistScreen.jsx` | `resources/views/livewire/watchlist.blade.php` + `App\Livewire\Watchlist` (account-backed; keep production copy) |

## Responsive shell

- Above 820px: sticky header with inline primary nav.
- At and below 820px: header nav hides; `BottomTabBar` (`bottom-tab-bar.blade.php`) takes over. Sign-in and secondary links live in `NavDrawer` (`nav-drawer.blade.php`) via More.
- At and below 700px: search collapses behind an icon button; currency picker uses a bottom sheet, teleported to `<body>` (see [`docs/currency.md`](currency.md)).
- Market tables pan horizontally with a sticky identity column; they do not reflow into cards. Columns marked `hide-narrow` drop below 700px.

## Non-negotiables

- Logo: four-bar mark + lowercase `adfreemarketcap` / `afmc` via `<x-afmc.brand-mark>`; favicon `public/brand/logo.svg`. Under 560px the header hides the wordmark and keeps the bars.
- Amber is action/attention only; green/red only for price direction. **Sentiment is not a direction:** the Fear and greed dial (`<x-afmc.gauge-dial>`) runs warm grey for cold to amber for hot, and intensity rises toward both ends with the faintest step at the midpoint. Colouring greed green would read as a gain.
- Figures use JetBrains Mono + tabular nums. **Zero is not a direction:** `<x-afmc.price-change>` drops the caret and uses neutral ink when a change rounds to 0.00%, which is the normal state for a stablecoin.
- **A chart never contradicts its number.** `<x-afmc.sparkline>` takes its colour and its slope from the series it is handed, so the seven-day cell is not tinted from the 24h column, and a flat line (a stablecoin) draws in `--chart-axis` instead of green. Pass `:up` only when the caller genuinely owns the direction.
- **A chart says what it plots.** A canvas is opaque to a screen reader, so the coin chart carries `role="img"` and a name on `.afmc-chart-frame`, naming the asset, the period and the currency. The name lives on the frame rather than the canvas because `wire:ignore` stops at the canvas, which is the part Chart.js owns; putting it inside that boundary froze the label on whichever range loaded first. Period wording comes from `CoinChartService::spokenRange()`, since the buttons say `7D` and a screen reader needs "7 days".
- **A modal surface owns focus.** `<x-afmc.nav-drawer>` uses `x-trap.noscroll` (Alpine's focus plugin ships with Livewire), which traps Tab inside the drawer, locks the body, and hands focus back to the tab-bar button that opened it. Escape closing a drawer that then drops focus to the top of the document is a defect, not a detail.
- **Confirm in text, not only in colour.** The donate copy button swaps to a tick, so it also writes into an `aria-live="polite"` status. It only confirms when the copy actually succeeded: both the async clipboard and the selection fallback are refused when the document is not focused, and claiming success there would be a lie.
- **An undefined token fails silently.** `var(--type-caption)` or `var(--amber-400)` does not throw, it resolves to nothing, and the rule is dropped or the element renders invisible. The amber ramp is 50/100/300/500/600/700 and the type roles are the ones in `resources/css/design-system/typography.css`; check a step exists, and that it exists in **both** themes, before using it.
- **Market status is one grid, not four boxes.** `<x-afmc.market-status>` puts sentiment, total market cap and AFMC10 in three equal panels (`[data-afmc-status]` in `design-system/base.css`, never `auto-fit`, which strands a panel on its own row), each with the same label / figure / caption silhouette. The altcoin-season scale (`<x-afmc.threshold-bar>`) gets its own full-width row underneath, because its zone labels are unreadable in a third of the width.
- **AFMC10 explains itself on the page.** A number nobody can look up elsewhere carries its method in its caption. Keep that sentence true to `App\Services\MarketData\MarketStatusCalculator` (it is market-cap weighted, not equal-weighted).
- **Every number has one owner.** The global figures belong to `<x-afmc.ticker>`; on the markets home page the status strip owns total market cap and 24h volume, so the ticker drops both there rather than repeating a figure 110px above itself.
- **SVG carrying no type is still drawn at its measured size.** The gauge sets `width`/`height` in pixels rather than scaling a viewBox to a percentage, because a scaled viewBox shrinks everything inside it.
- Pledge band + picks disclose funding and interests; never style picks as ad inventory. The band itself is `<x-afmc.pledge-band>`, shared by the home page and `/why-ad-free`, so the four claims are written once. Pass `:detail` to change the paragraph under the statement.
- **Why ad-free** (`route('why-ad-free')`, `App\Livewire\WhyAdFree`) is the long-form version of the pledge: what we refuse to ship, what the page loads, and who pays. Footer and More drawer point at it. Every claim on it has to match `config/analytics.php`, the cookie policy, and the disclosure of interests, so treat it as policy-adjacent copy. Tone is looser here than on a policy page, but the facts still have to line up.
- **Who to name.** Product copy names the person behind the site through `config('company.person')` (`COMPANY_PERSON`, "Anees®"), never `the creator`. Legal pages name the operating company (`config('company.legal_name')`), because that is the party a visitor deals with. `tests/Feature/CopyStyleTest.php` fails the build on `the creator` and `our creator`, and a pick note may carry a `:person` placeholder that the card fills in.
- DexScan and Watchlist are live in nav. Exchanges stays disabled until that surface ships. Watchlist requires `auth` on the `web` guard (see [`docs/public-accounts.md`](public-accounts.md)). Primary nav carries pages only, so the home page anchors (`#pledge`, `#picks`, `#mining`) are reached by scrolling.
- The miners block (`<x-afmc.mining-block>`, `config/mining.php`) links to a company `config('company.person')` is partnered with, so it carries the partner badge and the payout warning next to the link. Same rule as picks: it is a disclosure, not ad inventory. It sits at `#mining` on the home page, and under the Markets table on the coin pages listed in `MINING_COINS` (Bitcoin by default; pass `:coin` and the block hides itself elsewhere). Covered by `tests/Feature/MiningBlockTest.php`.

## Theme + cookie notice

Alpine on `<body>` persists `afmc-theme` in `localStorage` and toggles `data-theme="light|dark"` for token overrides. It also owns `moreOpen` and `searchOpen` for the mobile shell.

The cookie notice (`resources/views/components/afmc/cookie-bar.blade.php`) owns its own state: it opens when `afmc-cookies` is missing from `localStorage`, and "Got it" writes the key. Nothing here is optional, so the footer offers no preference panel. Its "Cookie notice" button dispatches the `afmc-cookie-notice` window event, which clears the key, reopens the notice, and moves focus to it. On narrow viewports the bar sits above the tab bar.
