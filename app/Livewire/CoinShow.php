<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Jobs\SyncCoinCharts;
use App\Jobs\SyncCoinDetail;
use App\Jobs\SyncCoinInsights;
use App\Jobs\SyncCoinTickers;
use App\Jobs\SyncNostrFeed;
use App\Models\Coin;
use App\Models\NostrNote;
use App\Services\Currency\MarketDisplayService;
use App\Services\MarketData\CoinChartService;
use App\Services\Seo\SeoService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Js;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
class CoinShow extends Component
{
    #[Locked]
    public int $coinId;

    #[Url(as: 'range')]
    public string $chartRange = '7d';

    public function mount(Coin $coin, CoinChartService $charts): void
    {
        $this->coinId = $coin->id;
        $this->chartRange = $charts->normalizeRange($this->chartRange);

        if ($coin->detailIsStale()) {
            SyncCoinDetail::dispatch($coin->id);
        }

        if ($coin->tickersAreStale()) {
            SyncCoinTickers::dispatch($coin->id);
        }

        $this->dispatchChartSync($coin, $charts);

        if ($coin->slug === 'bitcoin') {
            $coin->loadMissing(['treasurySnapshot', 'marketCycleSnapshot']);

            if ($this->insightsAreStale($coin)) {
                SyncCoinInsights::dispatch();
            }
        }

        if ($this->hasNostrFeed($coin->slug) && $this->nostrFeedIsStale($coin->slug)) {
            SyncNostrFeed::dispatch($coin->slug);
        }
    }

    public function setChartRange(string $range, CoinChartService $charts): void
    {
        $this->chartRange = $charts->normalizeRange($range);
        $coin = $this->loadCoin();
        $this->dispatchChartSync($coin, $charts, preferRange: $this->chartRange);
        $this->pushChartToClient($coin, $charts);
    }

    public function refreshMarkets(): void
    {
        $coin = $this->loadCoin();

        if ($coin->tickersAreStale()) {
            SyncCoinTickers::dispatch($coin->id);
        }
    }

    public function render(SeoService $seo, CoinChartService $charts)
    {
        $coin = $this->loadCoin();
        $coin->loadMissing('chartSeries');
        $pageSeo = $seo->forCoin($coin);

        $range = $charts->normalizeRange($this->chartRange);
        $chartPoints = $charts->pointsFor($coin, $range);
        $availableRanges = $charts->availableRanges($coin);

        $displayLimit = max(1, (int) config('nostr.display_limit', 8));
        $nostrNotes = $this->hasNostrFeed($coin->slug)
            ? NostrNote::forCoin($coin->slug, $displayLimit)
            : [];

        return view('livewire.coin-show', [
            'coin' => $coin,
            'treasury' => $coin->treasurySnapshot,
            'holders' => $coin->treasuryHolders,
            'cycle' => $coin->marketCycleSnapshot,
            'tickers' => $coin->tickers,
            'chartRange' => $range,
            'chartPoints' => $chartPoints,
            'availableRanges' => $availableRanges,
            'rangeMeta' => CoinChartService::RANGES,
            'spokenRange' => $charts->spokenRange($range),
            'nostrNotes' => $nostrNotes,
            'nostrEventLinkBase' => rtrim((string) config('nostr.event_link_base', 'https://primal.net/e'), '/'),
        ])
            ->title($pageSeo->title)
            ->layoutData(['seo' => $pageSeo]);
    }

    /**
     * Chart canvas is wire:ignore (Chart.js owns the DOM). Livewire @script only
     * runs once, so range switches must push a fresh payload to the browser.
     */
    private function pushChartToClient(Coin $coin, CoinChartService $charts): void
    {
        $range = $charts->normalizeRange($this->chartRange);
        $points = $charts->pointsFor($coin, $range);

        if (count($points) < 2) {
            return;
        }

        $display = app(MarketDisplayService::class);
        $unit = $display->unit();
        $values = array_map(static fn (array $point): float => $point[1], $points);
        $timestamps = array_map(static fn (array $point): int => (int) $point[0], $points);
        $payload = Js::from([
            'timestamps' => $timestamps,
            'range' => $range,
            'values' => $values,
            'up' => ($display->change($coin->percent_change_24h) ?? 0) >= 0,
            'symbol' => $unit->symbol,
            'symbolAfter' => (bool) $unit->symbolAfter,
        ]);

        $this->js("requestAnimationFrame(() => window.afmcMountCoinChart && window.afmcMountCoinChart({$payload}))");
    }

    private function dispatchChartSync(Coin $coin, CoinChartService $charts, ?string $preferRange = null): void
    {
        $preferRange ??= $this->chartRange;
        $seriesNeeded = [$charts->seriesForRange($preferRange)];

        // Warm short-horizon buckets so the default tabs respond quickly.
        foreach (['intraday', 'short'] as $series) {
            if (! in_array($series, $seriesNeeded, true)) {
                $seriesNeeded[] = $series;
            }
        }

        $stale = array_values(array_filter(
            $seriesNeeded,
            fn (string $series): bool => $charts->seriesIsMissingOrStale($coin, $series),
        ));

        if ($stale !== []) {
            SyncCoinCharts::dispatch($coin->id, $stale);
        }
    }

    private function loadCoin(): Coin
    {
        return Coin::query()
            ->with([
                'treasurySnapshot',
                'treasuryHolders' => fn ($query) => $query->orderBy('rank')->limit(10),
                'marketCycleSnapshot',
                'tickers' => fn ($query) => $query->orderBy('rank')->limit(50),
                'chartSeries',
            ])
            ->findOrFail($this->coinId);
    }

    private function insightsAreStale(Coin $coin): bool
    {
        $hours = max(1, (int) config('marketdata.sync.insights_interval_hours', 6));
        $treasuryAt = $coin->treasurySnapshot?->synced_at;
        $cycleAt = $coin->marketCycleSnapshot?->synced_at;

        if ($treasuryAt === null || $cycleAt === null) {
            return true;
        }

        $cutoff = now()->subHours($hours);

        return $treasuryAt->lte($cutoff) || $cycleAt->lte($cutoff);
    }

    private function hasNostrFeed(string $slug): bool
    {
        $feeds = config('nostr.feeds', []);

        return is_array($feeds) && array_key_exists($slug, $feeds);
    }

    private function nostrFeedIsStale(string $slug): bool
    {
        $minutes = max(1, (int) config('nostr.stale_minutes', 45));
        $latest = NostrNote::query()
            ->where('coin_slug', $slug)
            ->orderByDesc('synced_at')
            ->value('synced_at');

        if ($latest === null) {
            return true;
        }

        $syncedAt = $latest instanceof Carbon ? $latest : Carbon::parse($latest);

        return $syncedAt->lte(now()->subMinutes($minutes));
    }
}
