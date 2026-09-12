# Public UI design

The production front-end follows the **AdFreeMarketCap Design System** under `claude/AdFreeMarketCap Design System/` (gitignored reference kit).

## Mapping

| Kit | Production |
|-----|------------|
| `tokens/*.css` | `resources/css/design-system/` |
| Component look (React kit) | `resources/css/afmc.css` + `resources/views/components/afmc/` |
| `MarketsScreen.jsx` | `resources/views/livewire/home.blade.php` + `App\Livewire\Home` |
| `CoinDetailScreen.jsx` | `resources/views/livewire/coin-show.blade.php` + `App\Livewire\CoinShow` |
| `App.jsx` shell | `resources/views/layouts/app.blade.php` (header, ticker, footer, cookie bar, theme) |

## Non-negotiables

- Logo: four-bar mark + lowercase `adfreemarketcap` / `afmc` via `<x-afmc.brand-mark>`; favicon `public/brand/logo.svg`. Under 560px the header hides the wordmark and keeps the bars.
- Amber is action/attention only; green/red only for price direction.
- Figures use JetBrains Mono + tabular nums.
- Pledge band + picks disclose funding and interests; never style picks as ad inventory.
- DexScan and Watchlist are live in nav. Exchanges stays disabled until that surface ships. Watchlist requires `auth` on the `web` guard (see [`docs/public-accounts.md`](public-accounts.md)).

## Theme + cookie notice

Alpine on `<body>` persists `afmc-theme` in `localStorage` and toggles `data-theme="light|dark"` for token overrides.

The cookie notice (`resources/views/components/afmc/cookie-bar.blade.php`) owns its own state: it opens when `afmc-cookies` is missing from `localStorage`, and "Got it" writes the key. Nothing here is optional, so the footer offers no preference panel. Its "Cookie notice" button dispatches the `afmc-cookie-notice` window event, which clears the key, reopens the notice, and moves focus to it.
