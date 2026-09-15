<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\Coin;
use App\Models\MarketGlobal;
use App\Models\MarketStatusSnapshot;
use App\Models\User;
use App\Services\MarketData\NetworkCatalogService;
use App\Services\Seo\SeoService;
use App\Services\Watchlist\WatchlistService;
use App\Support\FormRateLimiter;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class Home extends Component
{
    use WithPagination;

    /**
     * Rows per page is a reader setting, like dense rows, not a redesign.
     *
     * @var list<int>
     */
    public const PER_PAGE_OPTIONS = [20, 50, 100];

    /**
     * Sortable columns, owned once so the header, the phone sort control and the query cannot
     * drift apart.
     *
     * @var array<string, string>
     */
    public const SORT_COLUMNS = [
        'rank' => 'Market cap rank',
        'name' => 'Name',
        'price' => 'Price',
        'percent_change_1h' => '1h change',
        'percent_change_24h' => '24h change',
        'percent_change_7d' => '7d change',
        'market_cap' => 'Market cap',
        'volume_24h' => 'Volume 24h',
    ];

    #[Url]
    public string $search = '';

    #[Url]
    public string $sort = 'rank';

    #[Url]
    public string $direction = 'asc';

    #[Url]
    public string $tab = 'all';

    #[Url]
    public string $network = 'all';

    #[Url]
    public int $perPage = 50;

    public bool $dense = false;

    /**
     * @var list<int>
     */
    public array $watchedIds = [];

    public function mount(WatchlistService $watchlist, NetworkCatalogService $networks): void
    {
        if (! $networks->isValidNetwork($this->network)) {
            $this->network = 'all';
        }

        if (! in_array($this->perPage, self::PER_PAGE_OPTIONS, true)) {
            $this->perPage = 50;
        }

        $user = Auth::user();

        if ($user instanceof User) {
            $this->watchedIds = $watchlist->watchedCoinIds($user)
                ->map(fn ($id): int => (int) $id)
                ->values()
                ->all();
        }
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingTab(): void
    {
        $this->resetPage();
    }

    public function updatingNetwork(): void
    {
        $this->resetPage();
    }

    /**
     * The phone list has no column headers to sort from, so it drives this property directly.
     * Picking a column starts on the direction that column is usually read in.
     */
    public function updatedSort(): void
    {
        if (! array_key_exists($this->sort, self::SORT_COLUMNS)) {
            $this->sort = 'rank';
        }

        $this->direction = $this->sort === 'rank' ? 'asc' : 'desc';
        $this->resetPage();
    }

    public function toggleDirection(): void
    {
        $this->direction = $this->direction === 'asc' ? 'desc' : 'asc';
        $this->resetPage();
    }

    public function updatedPerPage(): void
    {
        if (! in_array($this->perPage, self::PER_PAGE_OPTIONS, true)) {
            $this->perPage = 50;
        }

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

    public function setNetwork(string $network, NetworkCatalogService $networks): void
    {
        if (! $networks->isValidNetwork($network)) {
            return;
        }

        $this->network = $network;
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->network = 'all';
        $this->tab = 'all';
        $this->search = '';
        $this->resetPage();
    }

    public function sortBy(string $column): void
    {
        if (! array_key_exists($column, self::SORT_COLUMNS)) {
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

    public function toggleWatch(int $coinId, WatchlistService $watchlist): void
    {
        if (! Auth::check()) {
            $this->redirect(route('login'), navigate: true);

            return;
        }

        FormRateLimiter::ensureIsNotRateLimited('watch_toggle', errorKey: 'watched');
        FormRateLimiter::hit('watch_toggle');

        /** @var User $user */
        $user = Auth::user();
        $coin = Coin::query()->findOrFail($coinId);
        // Read before the toggle, because starting a watchlist is the conversion
        // and every later row is just a row.
        $startedEmpty = ! $watchlist->hasAny($user);
        $watched = $watchlist->toggle($user, $coin);

        if ($watched && $startedEmpty) {
            $this->dispatch('afmc-conversion', name: 'watchlist');
        }

        if ($watched) {
            $this->watchedIds[] = $coinId;
            $this->watchedIds = array_values(array_unique($this->watchedIds));
        } else {
            $this->watchedIds = array_values(array_filter(
                $this->watchedIds,
                fn (int $id): bool => $id !== $coinId,
            ));
        }
    }

    public function render(SeoService $seo, NetworkCatalogService $networks)
    {
        // Markets is a ranked list. Unranked rows (null rank) must not appear:
        // MySQL ASC puts NULLs first, which looked like a broken A-Z page.
        $query = Coin::query()
            ->select(Coin::LIST_COLUMNS)
            ->whereNotNull('rank');

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

        if ($this->network !== 'all') {
            $query->whereHas('platforms', function ($builder): void {
                $builder->where('platform_id', $this->network);
            });
        }

        $sort = array_key_exists($this->sort, self::SORT_COLUMNS) ? $this->sort : 'rank';
        $direction = $this->direction === 'desc' ? 'desc' : 'asc';

        if ($direction === 'desc') {
            $query->orderByDesc($sort);
        } else {
            $query->orderBy($sort);
        }

        $pageSeo = $seo->forHome();
        $chipRow = $networks->chipRow($this->network);

        return view('livewire.home', [
            'coins' => $query->paginate($this->perPage),
            'global' => MarketGlobal::latestSnapshot(),
            'status' => MarketStatusSnapshot::latestSnapshot(),
            'coinCount' => Coin::rankedCount(),
            'shownNetworks' => $chipRow['shown'],
            'moreNetworks' => $chipRow['rest'],
            'networkLabel' => $networks->networkLabel($this->network),
        ])
            ->title($pageSeo->title)
            ->layoutData(['seo' => $pageSeo]);
    }
}
