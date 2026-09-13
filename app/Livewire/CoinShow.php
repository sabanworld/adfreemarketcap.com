<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Jobs\SyncCoinDetail;
use App\Jobs\SyncCoinInsights;
use App\Jobs\SyncCoinTickers;
use App\Models\Coin;
use App\Services\Seo\SeoService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('layouts.app')]
class CoinShow extends Component
{
    #[Locked]
    public int $coinId;

    public function mount(Coin $coin): void
    {
        $this->coinId = $coin->id;

        if ($coin->detailIsStale()) {
            SyncCoinDetail::dispatch($coin->id);
        }

        if ($coin->tickersAreStale()) {
            SyncCoinTickers::dispatch($coin->id);
        }

        if ($coin->slug === 'bitcoin') {
            $coin->loadMissing(['treasurySnapshot', 'marketCycleSnapshot']);

            if ($this->insightsAreStale($coin)) {
                SyncCoinInsights::dispatch();
            }
        }
    }

    public function refreshMarkets(): void
    {
        $coin = $this->loadCoin();

        if ($coin->tickersAreStale()) {
            SyncCoinTickers::dispatch($coin->id);
        }
    }

    public function render(SeoService $seo)
    {
        $coin = $this->loadCoin();
        $pageSeo = $seo->forCoin($coin);

        return view('livewire.coin-show', [
            'coin' => $coin,
            'treasury' => $coin->treasurySnapshot,
            'holders' => $coin->treasuryHolders,
            'cycle' => $coin->marketCycleSnapshot,
            'tickers' => $coin->tickers,
        ])
            ->title($pageSeo->title)
            ->layoutData(['seo' => $pageSeo]);
    }

    private function loadCoin(): Coin
    {
        return Coin::query()
            ->with([
                'treasurySnapshot',
                'treasuryHolders' => fn ($query) => $query->orderBy('rank')->limit(10),
                'marketCycleSnapshot',
                'tickers' => fn ($query) => $query->orderBy('rank')->limit(50),
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
}
