# Public UI design

The production front-end follows the **AdFreeMarketCap Design System** under `claude/AdFreeMarketCap Design System (2)/` (reference kit).

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
- At and below 700px: search collapses behind an icon button; currency picker uses a bottom sheet.
- Market tables pan horizontally with a sticky identity column; they do not reflow into cards. Columns marked `hide-narrow` drop below 700px.

## Non-negotiables

- Logo: four-bar mark + lowercase `adfreemarketcap` / `afmc` via `<x-afmc.brand-mark>`; favicon `public/brand/logo.svg`. Under 560px the header hides the wordmark and keeps the bars.
- Amber is action/attention only; green/red only for price direction.
- Figures use JetBrains Mono + tabular nums.
- Pledge band + picks disclose funding and interests; never style picks as ad inventory.
- DexScan and Watchlist are live in nav. Exchanges stays disabled until that surface ships. Watchlist requires `auth` on the `web` guard (see [`docs/public-accounts.md`](public-accounts.md)).

## Theme + cookie notice

Alpine on `<body>` persists `afmc-theme` in `localStorage` and toggles `data-theme="light|dark"` for token overrides. It also owns `moreOpen` and `searchOpen` for the mobile shell.

The cookie notice (`resources/views/components/afmc/cookie-bar.blade.php`) owns its own state: it opens when `afmc-cookies` is missing from `localStorage`, and "Got it" writes the key. Nothing here is optional, so the footer offers no preference panel. Its "Cookie notice" button dispatches the `afmc-cookie-notice` window event, which clears the key, reopens the notice, and moves focus to it. On narrow viewports the bar sits above the tab bar.
