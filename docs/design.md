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
- DexScan / Exchanges / Watchlist remain disabled in nav until those surfaces ship.

## Theme + consent

Alpine on the document root persists `afmc-theme` and `afmc-cookies` in `localStorage` and toggles `data-theme="light|dark"` for token overrides.
