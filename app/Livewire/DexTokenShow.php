<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Jobs\SyncDexTokenDetail;
use App\Models\Coin;
use App\Models\DexToken;
use App\Services\Currency\MarketDisplayService;
use App\Services\MarketData\DexChartService;
use App\Services\Seo\SeoService;
use Illuminate\Support\Js;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
class DexTokenShow extends Component
{
    #[Locked]
    public int $tokenId;

    #[Url(as: 'range')]
    public string $chartRange = '7d';

    #[Url]
    public string $tab = 'trades';

    public function mount(string $network, string $address, DexChartService $charts): void
    {
        $token = DexToken::query()
            ->where('network_id', $network)
            ->whereRaw('LOWER(address) = ?', [strtolower($address)])
            ->firstOrFail();

        $this->tokenId = $token->id;
        $this->chartRange = $charts->normalizeRange($this->chartRange);
        $this->normalizeTab();
        $this->dispatchDetailSync($token, $charts);
    }

    public function setChartRange(string $range, DexChartService $charts): void
    {
        $this->chartRange = $charts->normalizeRange($range);
        $token = $this->loadToken();
        $this->dispatchDetailSync($token, $charts);
        $this->pushChartToClient($token, $charts);
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
        $this->dispatchDetailSync($this->loadToken(), app(DexChartService::class));
    }

    public function render(SeoService $seo, DexChartService $charts)
    {
        $token = $this->loadToken();
        $token->loadMissing([
            'chartSeries',
            'pairs' => fn ($q) => $q->orderByDesc('liquidity_usd')->limit(50),
            'trades' => fn ($q) => $q->orderByDesc('traded_at')->limit(50),
            'holders' => fn ($q) => $q->orderBy('rank')->limit(50),
        ]);

        $range = $charts->normalizeRange($this->chartRange);
        $chartPoints = $charts->pointsFor($token, $range);
        $availableRanges = $charts->availableRanges($token);
        $pageSeo = $seo->forDexToken($token);

        $marketsCoin = null;
        if (filled($token->coingecko_coin_id)) {
            $marketsCoin = Coin::query()
                ->where(function ($query) use ($token): void {
                    $query->whereHas('providerIds', function ($providerQuery) use ($token): void {
                        $providerQuery->where('provider', 'coingecko')
                            ->where('external_id', $token->coingecko_coin_id);
                    })->orWhere('slug', $token->coingecko_coin_id);
                })
                ->first();
        }

        $onlySeed = $token->pairs()->exists()
            && ! $token->pairs()->where('provider', '!=', 'seed')->exists();

        $needsPoll = ! $onlySeed && (
            $token->detailIsStale()
            || $token->tradesAreStale()
            || $token->holdersAreStale()
            || $charts->staleSeriesKeys($token) !== []
        );

        return view('livewire.dex-token-show', [
            'token' => $token,
            'pools' => $token->pairs,
            'trades' => $token->trades,
            'holders' => $token->holders,
            'chartRange' => $range,
            'chartPoints' => $chartPoints,
            'availableRanges' => $availableRanges,
            'rangeMeta' => DexChartService::RANGES,
            'spokenRange' => $charts->labelForRange($range),
            'marketsCoin' => $marketsCoin,
            'needsPoll' => $needsPoll,
            'holdersWarming' => $token->holdersAreStale() && $token->holders->isEmpty(),
        ])
            ->title($pageSeo->title)
            ->layoutData(['seo' => $pageSeo]);
    }

    private function dispatchDetailSync(DexToken $token, DexChartService $charts): void
    {
        $onlySeed = $token->pairs()->exists()
            && ! $token->pairs()->where('provider', '!=', 'seed')->exists();

        if ($onlySeed) {
            return;
        }

        if (
            $token->detailIsStale()
            || $token->tradesAreStale()
            || $token->holdersAreStale()
            || $charts->staleSeriesKeys($token) !== []
        ) {
            SyncDexTokenDetail::dispatch($token->id);
        }
    }

    private function pushChartToClient(DexToken $token, DexChartService $charts): void
    {
        $display = app(MarketDisplayService::class);
        $unit = $display->unit();
        $range = $charts->normalizeRange($this->chartRange);
        $points = $charts->pointsFor($token, $range);
        $values = array_map(static fn (array $point): float => $point[1], $points);
        $timestamps = array_map(static fn (array $point): int => (int) $point[0], $points);

        $payload = Js::from([
            'timestamps' => $timestamps,
            'range' => $range,
            'values' => $values,
            'up' => ($display->change($token->percent_change_24h) ?? 0) >= 0,
            'symbol' => $unit->symbol,
            'symbolAfter' => (bool) $unit->symbolAfter,
        ]);

        $this->js("requestAnimationFrame(() => window.afmcMountDexChart && window.afmcMountDexChart({$payload}))");
    }

    private function loadToken(): DexToken
    {
        return DexToken::query()->findOrFail($this->tokenId);
    }

    private function normalizeTab(): void
    {
        if (! in_array($this->tab, ['trades', 'liquidity', 'holders'], true)) {
            $this->tab = 'trades';
        }
    }
}
