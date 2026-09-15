<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\DexPair;
use App\Services\Seo\SeoService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class DexScan extends Component
{
    use WithPagination;

    private const STATS_CACHE_SECONDS = 60;

    #[Url]
    public string $search = '';

    #[Url]
    public string $tab = 'trending';

    #[Url]
    public string $chain = 'all';

    public bool $verifiedOnly = false;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function setTab(string $tab): void
    {
        if (! in_array($tab, ['trending', 'gainers', 'new', 'liquidity'], true)) {
            return;
        }

        $this->tab = $tab;
        $this->resetPage();
    }

    public function setChain(string $chain): void
    {
        $this->chain = $chain;
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->chain = 'all';
        $this->tab = 'trending';
        $this->search = '';
        $this->verifiedOnly = false;
        $this->resetPage();
    }

    public function render(SeoService $seo)
    {
        $query = DexPair::query();

        if ($this->chain !== 'all') {
            $query->where('chain', $this->chain);
        }

        if ($this->verifiedOnly) {
            $query->notUnverified();
        }

        if (filled($this->search)) {
            $term = '%' . $this->search . '%';
            $query->where(function ($builder) use ($term): void {
                $builder->where('pair', 'like', $term)
                    ->orWhere('base_symbol', 'like', $term)
                    ->orWhere('contract_address', 'like', $term);
            });
        }

        match ($this->tab) {
            'gainers' => $query->where('percent_change_24h', '>', 0)->orderByDesc('percent_change_24h'),
            'new' => $query->whereNotNull('paired_at')->orderByDesc('paired_at'),
            'liquidity' => $query->orderByDesc('liquidity_usd'),
            default => $query->orderByDesc('is_trending')->orderByDesc('volume_24h'),
        };

        $pageSeo = $seo->forStaticPage(
            title: __('seo.dexscan_title', ['site' => config('app.name')]),
            description: __('seo.dexscan_description'),
            canonical: route('dexscan'),
        );

        return view('livewire.dex-scan', [
            'pairs' => $query->paginate(50),
            'chains' => $this->cachedChains(),
            'stats' => $this->cachedStats(),
        ])
            ->title($pageSeo->title)
            ->layoutData(['seo' => $pageSeo]);
    }

    /**
     * @return Collection<int, string>
     */
    private function cachedChains(): Collection
    {
        return Cache::remember('dexscan.chains', self::STATS_CACHE_SECONDS, function () {
            return DexPair::query()->distinct()->orderBy('chain')->pluck('chain');
        });
    }

    /**
     * @return array{volume: float, pairs: int, new_24h: int}
     */
    private function cachedStats(): array
    {
        return Cache::remember('dexscan.stats', self::STATS_CACHE_SECONDS, function () {
            return [
                'volume' => (float) DexPair::query()->sum('volume_24h'),
                'pairs' => DexPair::query()->count(),
                'new_24h' => DexPair::query()->where('paired_at', '>=', now()->subDay())->count(),
            ];
        });
    }
}
