<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\Coin;
use App\Models\MarketGlobal;
use App\Services\Seo\SeoService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class Home extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $sort = 'rank';

    #[Url]
    public string $direction = 'asc';

    #[Url]
    public string $tab = 'all';

    public bool $dense = false;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingTab(): void
    {
        $this->resetPage();
    }

    public function setTab(string $tab): void
    {
        if (! in_array($tab, ['all', 'gainers', 'losers'], true)) {
            return;
        }

        $this->tab = $tab;
        $this->resetPage();
    }

    public function sortBy(string $column): void
    {
        $allowed = ['rank', 'name', 'price', 'percent_change_1h', 'percent_change_24h', 'percent_change_7d', 'market_cap', 'volume_24h'];

        if (! in_array($column, $allowed, true)) {
            return;
        }

        if ($this->sort === $column) {
            $this->direction = $this->direction === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sort = $column;
            $this->direction = $column === 'rank' ? 'asc' : 'desc';
        }

        $this->resetPage();
    }

    public function render(SeoService $seo)
    {
        // Markets is a ranked list. Unranked rows (null rank) must not appear:
        // MySQL ASC puts NULLs first, which looked like a broken A-Z page.
        $query = Coin::query()->whereNotNull('rank');

        if (filled($this->search)) {
            $term = '%' . $this->search . '%';
            $query->where(function ($builder) use ($term): void {
                $builder->where('name', 'like', $term)
                    ->orWhere('symbol', 'like', $term);
            });
        }

        if ($this->tab === 'gainers') {
            $query->where('percent_change_24h', '>', 0);
        } elseif ($this->tab === 'losers') {
            $query->where('percent_change_24h', '<', 0);
        }

        $allowed = ['rank', 'name', 'price', 'percent_change_1h', 'percent_change_24h', 'percent_change_7d', 'market_cap', 'volume_24h'];
        $sort = in_array($this->sort, $allowed, true) ? $this->sort : 'rank';
        $direction = $this->direction === 'desc' ? 'desc' : 'asc';

        if ($direction === 'desc') {
            $query->orderByDesc($sort);
        } else {
            $query->orderBy($sort);
        }

        $featured = Coin::query()
            ->whereNotNull('rank')
            ->whereIn('symbol', ['BTC', 'ETH'])
            ->orderBy('rank')
            ->limit(2)
            ->get();

        if ($featured->count() < 2) {
            $featured = Coin::query()->whereNotNull('rank')->orderBy('rank')->limit(2)->get();
        }

        $pageSeo = $seo->forHome();

        return view('livewire.home', [
            'coins' => $query->paginate(50),
            'global' => MarketGlobal::latestSnapshot(),
            'featured' => $featured,
            'coinCount' => Coin::query()->whereNotNull('rank')->count(),
        ])
            ->title($pageSeo->title)
            ->layoutData(['seo' => $pageSeo]);
    }
}
