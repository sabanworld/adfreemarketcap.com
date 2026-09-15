@php
    use App\Services\Currency\MarketDisplayService;
    use App\Services\MarketData\MarketNumberFormatter;

    $display = app(MarketDisplayService::class);
@endphp

<main data-afmc-page class="afmc-page">
    <div style="display:flex;align-items:baseline;justify-content:space-between;gap:var(--space-4);flex-wrap:wrap;margin-bottom:var(--space-4)">
        <div style="display:flex;align-items:baseline;gap:var(--space-3);flex-wrap:wrap">
            <h1 style="font:var(--type-h2);margin:0">{{ __('Cryptocurrency prices by market cap') }}</h1>
        </div>
        <span class="afmc-live">
            <x-afmc.icon name="fiber_manual_record" filled />
            {{ __('Live · rankings from synced market data') }}
        </span>
    </div>

    <x-afmc.market-status :global="$global" :status="$status" style="margin-bottom:var(--space-5)" />

    <div style="display:flex;align-items:center;justify-content:space-between;gap:var(--space-4);margin-bottom:var(--space-3);flex-wrap:wrap">
        {{-- The tab badge is the one owner of the tracked-asset count on this page, which is
             why the ticker leaves it out. --}}
        <div class="afmc-tabs" data-afmc-tabs role="tablist">
            @foreach (['all' => __('All coins'), 'gainers' => __('Gainers'), 'losers' => __('Losers')] as $value => $label)
                <button
                    type="button"
                    role="tab"
                    aria-selected="{{ $tab === $value ? 'true' : 'false' }}"
                    tabindex="{{ $tab === $value ? '0' : '-1' }}"
                    class="afmc-tabs__item {{ $tab === $value ? 'is-active' : '' }}"
                    wire:click="setTab('{{ $value }}')"
                >
                    {{ $label }}
                    @if ($value === 'all')
                        <span class="afmc-tabs__count">{{ number_format($coinCount) }}</span>
                    @endif
                </button>
            @endforeach
        </div>
        {{-- Row density belongs to the table, so it goes when the table does. --}}
        <label data-afmc-densetoggle class="afmc-check">
            <input type="checkbox" wire:model.live="dense" />
            {{ __('Dense rows') }}
        </label>
    </div>

    {{-- Below 700px the ranking is a list of expandable rows, which has no column headers to
         sort from. This is where the sort lives on a phone. --}}
    <div data-afmc-mobilesort class="afmc-sortbar">
        <span class="afmc-sortbar__label">{{ __('Sort') }}</span>
        <label class="afmc-sortbar__select">
            <span class="afmc-visually-hidden">{{ __('Sort by') }}</span>
            <select wire:model.live="sort">
                @foreach (\App\Livewire\Home::SORT_COLUMNS as $column => $columnLabel)
                    <option value="{{ $column }}" @selected($column === $sort)>{{ __($columnLabel) }}</option>
                @endforeach
            </select>
            <x-afmc.icon name="expand_more" size="16px" />
        </label>
        <button
            type="button"
            class="afmc-icon-btn afmc-icon-btn--lg"
            wire:click="toggleDirection"
            aria-label="{{ $direction === 'asc'
                ? __('Sorted ascending · switch to descending')
                : __('Sorted descending · switch to ascending') }}"
        >
            <x-afmc.icon :name="$direction === 'asc' ? 'arrow_upward' : 'arrow_downward'" size="18px" />
        </button>
    </div>

    @if ($shownNetworks->isNotEmpty())
        <x-afmc.network-filter
            :network="$network"
            :networks="$shownNetworks"
            :more-networks="$moreNetworks"
            style="margin-bottom:var(--space-3)"
        />
    @endif

    @php
        $filtered = $network !== 'all' || $tab !== 'all' || filled($search);
    @endphp

    <div class="afmc-card" wire:loading.class="afmc-is-loading" wire:target="sortBy,sort,toggleDirection,setTab,setNetwork,gotoPage,previousPage,nextPage,dense,search">
        @if ($coins->isEmpty())
            <div class="afmc-board__empty">
                <div class="afmc-empty">
                    <x-afmc.icon name="search_off" class="afmc-empty__icon" />
                    @if ($network !== 'all')
                        <p class="afmc-empty__title">{{ __('Nothing on :network yet', ['network' => $networkLabel ?? __('this network')]) }}</p>
                        <p class="afmc-empty__detail">
                            {{ __('We list an asset against a chain only once we can confirm its contract, so a chain we have just added can look empty for a while.') }}
                        </p>
                    @elseif ($filtered)
                        <p class="afmc-empty__title">{{ __('Nothing matches this view') }}</p>
                        <p class="afmc-empty__detail">
                            {{ __('Gainers and losers are read off the 24 hour change. Clear the filters to see everything we track.') }}
                        </p>
                    @else
                        <p class="afmc-empty__title">{{ __('No coins yet') }}</p>
                        <p class="afmc-empty__detail">
                            {{ __('Market data has not been synced yet, so there is nothing to rank.') }}
                        </p>
                    @endif
                    @if ($filtered)
                        <button type="button" class="afmc-btn afmc-btn--secondary afmc-btn--sm" wire:click="clearFilters">
                            {{ __('Clear filters') }}
                        </button>
                    @endif
                </div>
            </div>
        @else
            {{-- One ranking, two forms. Both ship and the 700px rule in base.css decides which
                 one a viewport gets, so a phone never paints the table first. --}}
            <div class="afmc-board">
                <div class="afmc-board__list">
                    <x-afmc.market-list
                        :coins="$coins"
                        :watched-ids="$watchedIds"
                        :label="__('Cryptocurrency prices by market cap')"
                    />
                </div>

                <div class="afmc-board__table afmc-table-wrap" data-afmc-tablescroll role="region" aria-label="{{ __('Cryptocurrency prices by market cap') }}" tabindex="0">
                    <table class="afmc-table {{ $dense ? 'afmc-table--dense' : '' }}">
                        <thead>
                            <tr>
                                <th class="is-sticky is-sticky--watch hide-narrow" style="width:38px;left:0"></th>
                                <th class="is-sticky is-sticky--name {{ in_array($sort, ['rank', 'name'], true) ? 'is-sorted' : '' }}" style="left:38px">
                                    <span class="afmc-table__sortpair">
                                        <button type="button" wire:click="sortBy('rank')" aria-label="{{ __('Sort by market-cap rank') }}">#
                                            @if ($sort === 'rank')
                                                <x-afmc.icon :name="$direction === 'asc' ? 'arrow_drop_up' : 'arrow_drop_down'" size="14px" color="var(--amber-600)" />
                                            @endif
                                        </button>
                                        <button type="button" wire:click="sortBy('name')">{{ __('Name') }}
                                            @if ($sort === 'name')
                                                <x-afmc.icon :name="$direction === 'asc' ? 'arrow_drop_up' : 'arrow_drop_down'" size="14px" color="var(--amber-600)" />
                                            @endif
                                        </button>
                                    </span>
                                </th>
                                <th class="is-right {{ $sort === 'price' ? 'is-sorted' : '' }}">
                                    <button type="button" wire:click="sortBy('price')">{{ __('Price') }}
                                        @if ($sort === 'price')
                                            <x-afmc.icon :name="$direction === 'asc' ? 'arrow_drop_up' : 'arrow_drop_down'" size="14px" color="var(--amber-600)" />
                                        @endif
                                    </button>
                                </th>
                                <th class="is-right hide-narrow {{ $sort === 'percent_change_1h' ? 'is-sorted' : '' }}">
                                    <button type="button" wire:click="sortBy('percent_change_1h')">1h</button>
                                </th>
                                <th class="is-right {{ $sort === 'percent_change_24h' ? 'is-sorted' : '' }}">
                                    <button type="button" wire:click="sortBy('percent_change_24h')">24h %</button>
                                </th>
                                <th class="is-right {{ $sort === 'percent_change_7d' ? 'is-sorted' : '' }}">
                                    <button type="button" wire:click="sortBy('percent_change_7d')">7d %</button>
                                </th>
                                <th class="is-right {{ $sort === 'market_cap' ? 'is-sorted' : '' }}">
                                    <button type="button" wire:click="sortBy('market_cap')">{{ __('Market cap') }}</button>
                                </th>
                                <th class="is-right {{ $sort === 'volume_24h' ? 'is-sorted' : '' }}">
                                    <button type="button" wire:click="sortBy('volume_24h')">{{ __('Volume (24h)') }}</button>
                                </th>
                                <th class="is-right hide-narrow"><span>{{ __('Last 7 days') }}</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($coins as $index => $coin)
                                <tr wire:key="coin-{{ $coin->id }}">
                                    <td class="is-sticky is-sticky--watch hide-narrow" style="left:0">
                                        <x-afmc.watch-star
                                            :coin-id="$coin->id"
                                            :watched="in_array($coin->id, $watchedIds, true)"
                                        />
                                    </td>
                                    <td class="is-sticky is-sticky--name" style="left:38px">
                                        <span class="afmc-table__namecell">
                                            <span data-afmc-rankcell class="afmc-table__rank">{{ $coin->rank ?? '—' }}</span>
                                            <x-afmc.coin-identity
                                                :name="$coin->name"
                                                :symbol="$coin->symbol"
                                                :image="$coin->image_url"
                                                :href="route('coins.show', $coin)"
                                                :eager="$index < 8"
                                            />
                                        </span>
                                    </td>
                                    <td class="is-right">{{ MarketNumberFormatter::money($coin->price !== null ? (float) $coin->price : null, 8) }}</td>
                                    {{-- 1h carries no caret: three carets in a row turn the percentage
                                         columns into an arrow field and the sign already reads. --}}
                                    <td class="is-right hide-narrow"><x-afmc.price-change :value="$display->change($coin->percent_change_1h, '1h')" size="sm" :show-icon="false" /></td>
                                    <td class="is-right"><x-afmc.price-change :value="$display->change($coin->percent_change_24h)" size="sm" /></td>
                                    <td class="is-right"><x-afmc.price-change :value="$display->change($coin->percent_change_7d, '7d')" size="sm" /></td>
                                    <td class="is-right">{{ MarketNumberFormatter::money($coin->market_cap !== null ? (float) $coin->market_cap : null) }}</td>
                                    <td class="is-right">{{ MarketNumberFormatter::money($coin->volume_24h !== null ? (float) $coin->volume_24h : null) }}</td>
                                    {{-- Colour and slope both come from the seven-day series this cell
                                         draws. Tinting it from the 24h column made a chart contradict
                                         its own line. --}}
                                    <td class="is-right hide-narrow">
                                        <x-afmc.sparkline :data="$display->sparkline($coin->sparkline_7d)" />
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        @if ($coins->total() > 0)
            <div class="afmc-pagination">
                <span class="afmc-pagination__meta">
                    {{ __('Showing') }}
                    {{ $coins->firstItem() }}–{{ $coins->lastItem() }}
                    {{ __('of') }}
                    {{ number_format($coins->total()) }}
                </span>
                <div class="afmc-pagination__links">
                    {{ $coins->onEachSide(1)->links('pagination.afmc') }}
                    <label class="afmc-perpage">
                        <span class="afmc-visually-hidden">{{ __('Rows per page') }}</span>
                        <select wire:model.live="perPage">
                            @foreach (\App\Livewire\Home::PER_PAGE_OPTIONS as $option)
                                <option value="{{ $option }}" @selected($option === $perPage)>{{ __(':count / page', ['count' => $option]) }}</option>
                            @endforeach
                        </select>
                        <x-afmc.icon name="expand_more" size="16px" />
                    </label>
                </div>
            </div>
        @endif
    </div>

    <div class="afmc-callout afmc-callout--risk" style="margin-top:var(--space-4)">
        <x-afmc.icon name="report" filled class="afmc-callout__icon" />
        <div class="afmc-callout__content">
            <p class="afmc-callout__title">{{ __('Most of the long tail is worthless') }}</p>
            <p class="afmc-callout__body">
                {{ __('Of the assets we track, many have thin books, short history, or unverified contracts. Assume a total loss is possible, and never size a position on a 24h percentage.') }}
            </p>
        </div>
    </div>

    <x-afmc.pledge-band
        style="margin-top:var(--space-10)"
        :detail="__(':person pays for this site out of his own pocket. Nobody can pay to show up here, to move up the table, or to lose a risk flag. The picks below are companies he uses or has a partnership with. Every card says which, and none of them are paid placements.', ['person' => config('company.person')])"
    />

    <p style="margin:var(--space-3) 0 0;font:var(--type-body-sm);color:var(--text-muted)">
        <a href="{{ route('why-ad-free') }}" wire:navigate class="afmc-link">{{ __('Why we build it this way') }}</a>
    </p>

    <x-afmc.mining-block style="margin-top:var(--space-10)" />

    <section id="picks" style="margin-top:var(--space-10)">
        <div style="display:flex;align-items:baseline;justify-content:space-between;margin-bottom:var(--space-3);gap:var(--space-3);flex-wrap:wrap">
            <h2 style="margin:0;font:var(--type-h2)">{{ __('Picks, not ads') }}</h2>
            <span style="font:var(--type-body-sm);color:var(--text-faint)">{{ __('Companies we like · any partnership is disclosed') }}</span>
        </div>
        <div class="afmc-grid afmc-grid--picks">
            @foreach (config('picks') as $pick)
                @php
                    $isPartner = $pick['relationship'] === 'partner';
                @endphp
                <a
                    class="afmc-pick"
                    href="{{ $pick['url'] }}"
                    rel="noopener noreferrer"
                    target="_blank"
                >
                    <div class="afmc-pick__head">
                        <span class="afmc-pick__kind">{{ __($pick['kind']) }}</span>
                        <span class="afmc-pick__badge{{ $isPartner ? ' afmc-pick__badge--interest' : '' }}">{{ __($isPartner ? ':person is a partner' : ':person uses this', ['person' => config('company.person')]) }}</span>
                    </div>
                    <h3 class="afmc-pick__title">{{ $pick['name'] }}</h3>
                    <p class="afmc-pick__note">{{ __($pick['note'], ['person' => config('company.person')]) }}</p>
                    <div class="afmc-pick__foot">
                        <span>{{ parse_url($pick['url'], PHP_URL_HOST) }}<span class="afmc-visually-hidden">{{ __(', opens in a new tab') }}</span></span>
                        <x-afmc.icon name="arrow_outward" size="14px" />
                    </div>
                </a>
            @endforeach
        </div>
    </section>
</main>
