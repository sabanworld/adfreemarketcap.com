<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Jobs\SyncDexPairDetail;
use App\Jobs\SyncDexTokenDetail;
use App\Models\Coin;
use App\Models\DexPair;
use App\Services\Currency\MarketDisplayService;
use App\Services\MarketData\DexChartService;
use App\Services\MarketData\DexQualityAssessor;
use App\Services\Seo\SeoService;
use Illuminate\Support\Js;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
class DexPairShow extends Component
{
    #[Locked]
    public int $pairId;

    #[Url(as: 'range')]
    public string $chartRange = '7d';

    #[Url]
    public string $tab = 'trades';

    public function mount(DexPair $pair, DexChartService $charts): void
    {
        $this->pairId = $pair->id;
        $this->chartRange = $charts->normalizeRange($this->chartRange);
        $this->normalizeTab();

        $this->dispatchDetailSync($pair, $charts);
    }

    public function setChartRange(string $range, DexChartService $charts): void
    {
        $this->chartRange = $charts->normalizeRange($range);
        $pair = $this->loadPair();
        $this->dispatchDetailSync($pair, $charts);
        $this->pushChartToClient($pair, $charts);
    }

    public function setTab(string $tab): void
    {
        if (! in_array($tab, ['trades', 'liquidity', 'holders'], true)) {
            return;
        }

        $this->tab = $tab;
    }

    public function refreshDetail(): void
    {
        $pair = $this->loadPair();
        $this->dispatchDetailSync($pair, app(DexChartService::class));
    }

    public function render(SeoService $seo, DexChartService $charts, DexQualityAssessor $quality)
    {
        $pair = $this->loadPair();
        $pair->loadMissing(['token', 'chartSeries', 'trades' => fn ($q) => $q->orderByDesc('traded_at')->limit(50)]);
        $token = $pair->token;

        if ($token !== null) {
            $token->loadMissing(['holders' => fn ($q) => $q->orderBy('rank')->limit(50)]);
        }

        $range = $charts->normalizeRange($this->chartRange);
        $chartPoints = $charts->pointsFor($pair, $range);
        $availableRanges = $charts->availableRanges($pair);
        $pageSeo = $seo->forDexPair($pair);

        $marketsCoin = null;
        if (filled($token?->coingecko_coin_id)) {
            $marketsCoin = Coin::query()
                ->where(function ($query) use ($token): void {
                    $query->whereHas('providerIds', function ($providerQuery) use ($token): void {
                        $providerQuery->where('provider', 'coingecko')
                            ->where('external_id', $token->coingecko_coin_id);
                    })->orWhere('slug', $token->coingecko_coin_id);
                })
                ->first();
        }

        $needsPoll = $pair->provider !== 'seed' && (
            $pair->detailIsStale()
            || $pair->tradesAreStale()
            || $charts->staleSeriesKeys($pair) !== []
            || ($token !== null && $token->holdersAreStale() && $this->tab === 'holders')
        );

        return view('livewire.dex-pair-show', [
            'pair' => $pair,
            'token' => $token,
            'trades' => $pair->trades,
            'holders' => $token?->holders ?? collect(),
            'quality' => $quality->describe($pair),
            'chartRange' => $range,
            'chartPoints' => $chartPoints,
            'chartLabels' => array_map(
                static fn (array $point): string => (string) $point[0],
                $chartPoints,
            ),
            'availableRanges' => $availableRanges,
            'rangeMeta' => DexChartService::RANGES,
            'spokenRange' => $charts->labelForRange($range),
            'marketsCoin' => $marketsCoin,
            'needsPoll' => $needsPoll,
        ])
            ->title($pageSeo->title)
            ->layoutData(['seo' => $pageSeo]);
    }

    private function dispatchDetailSync(DexPair $pair, DexChartService $charts): void
    {
        // Offline seed rows ship chart/trade fixtures; calling GeckoTerminal would 404.
        if ($pair->provider === 'seed') {
            return;
        }

        $needsCharts = $charts->staleSeriesKeys($pair) !== [];
        if ($pair->detailIsStale() || $pair->tradesAreStale() || $needsCharts) {
            SyncDexPairDetail::dispatch($pair->id);
        }

        if ($pair->dex_token_id && $pair->token?->holdersAreStale()) {
            SyncDexTokenDetail::dispatch($pair->dex_token_id);
        }
    }

    private function pushChartToClient(DexPair $pair, DexChartService $charts): void
    {
        $display = app(MarketDisplayService::class);
        $unit = $display->unit();
        $points = $charts->pointsFor($pair, $this->chartRange);
        $labels = array_map(static fn (array $point): string => (string) $point[0], $points);
        $values = array_map(static fn (array $point): float => $point[1], $points);

        $payload = Js::from([
            'labels' => $labels,
            'values' => $values,
            'up' => ($display->change($pair->percent_change_24h) ?? 0) >= 0,
            'symbol' => $unit->symbol,
            'symbolAfter' => (bool) $unit->symbolAfter,
        ]);

        $this->js("requestAnimationFrame(() => window.afmcMountDexChart && window.afmcMountDexChart({$payload}))");
    }

    private function loadPair(): DexPair
    {
        return DexPair::query()->with('token')->findOrFail($this->pairId);
    }

    private function normalizeTab(): void
    {
        if (! in_array($this->tab, ['trades', 'liquidity', 'holders'], true)) {
            $this->tab = 'trades';
        }
    }
}
