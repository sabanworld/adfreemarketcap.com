<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Jobs\SyncCoinDetail;
use App\Jobs\SyncCoinInsights;
use App\Jobs\SyncCoinTickers;
use App\Models\Coin;
use App\Services\Seo\SeoService;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class CoinShow extends Component
{
    public Coin $coin;

    public function mount(Coin $coin): void
    {
        $this->coin = $coin;
        $this->refreshRelations();

        if ($this->coin->detailIsStale()) {
            SyncCoinDetail::dispatch($this->coin->id);
        }

        if ($this->coin->tickersAreStale()) {
            SyncCoinTickers::dispatch($this->coin->id);
        }

        if ($this->coin->slug === 'bitcoin' && $this->insightsAreStale()) {
            SyncCoinInsights::dispatch();
        }
    }

    public function refreshMarkets(): void
    {
        $this->coin->refresh();
        $this->refreshRelations();

        if ($this->coin->tickersAreStale()) {
            SyncCoinTickers::dispatch($this->coin->id);
        }
    }

    public function render(SeoService $seo)
    {
        $pageSeo = $seo->forCoin($this->coin);

        return view('livewire.coin-show', [
            'treasury' => $this->coin->treasurySnapshot,
            'holders' => $this->coin->treasuryHolders,
            'cycle' => $this->coin->marketCycleSnapshot,
            'tickers' => $this->coin->tickers,
        ])
            ->title($pageSeo->title)
            ->layoutData(['seo' => $pageSeo]);
    }

    private function refreshRelations(): void
    {
        $this->coin->load([
            'treasurySnapshot',
            'treasuryHolders' => fn ($query) => $query->orderBy('rank')->limit(10),
            'marketCycleSnapshot',
            'tickers' => fn ($query) => $query->orderBy('rank')->limit(50),
        ]);
    }

    private function insightsAreStale(): bool
    {
        $hours = max(1, (int) config('marketdata.sync.insights_interval_hours', 6));
        $treasuryAt = $this->coin->treasurySnapshot?->synced_at;
        $cycleAt = $this->coin->marketCycleSnapshot?->synced_at;

        if ($treasuryAt === null || $cycleAt === null) {
            return true;
        }

        $cutoff = now()->subHours($hours);

        return $treasuryAt->lte($cutoff) || $cycleAt->lte($cutoff);
    }
}
