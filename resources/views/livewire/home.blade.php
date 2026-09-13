@php
    use App\Services\Currency\MarketDisplayService;
    use App\Services\MarketData\MarketNumberFormatter;

    $display = app(MarketDisplayService::class);
    $btcDominance = $global?->btc_dominance !== null ? (float) $global->btc_dominance : null;
@endphp

<main data-afmc-page class="afmc-page">
    <div style="display:flex;align-items:baseline;justify-content:space-between;gap:var(--space-4);flex-wrap:wrap;margin-bottom:var(--space-4)">
        <div style="display:flex;align-items:baseline;gap:var(--space-3);flex-wrap:wrap">
            <h1 style="font:var(--type-h2);margin:0">{{ __('Cryptocurrency prices by market cap') }}</h1>
            <span style="font:var(--type-body-sm);color:var(--text-faint)">{{ __('Ranking cannot be bought') }}</span>
        </div>
        <span class="afmc-live">
            <x-afmc.icon name="fiber_manual_record" filled />
            {{ __('Live · rankings from synced market data') }}
        </span>
    </div>

    @if ($featured->isNotEmpty())
        <div class="afmc-grid afmc-grid--features">
            @foreach ($featured as $index => $coin)
                @php
                    $change24 = $display->change($coin->percent_change_24h);
                    $spark = $display->sparkline($coin->sparkline_7d);
                @endphp
                <a
                    href="{{ route('coins.show', $coin) }}"
                    wire:navigate
                    class="afmc-feature {{ $index === 0 ? 'afmc-feature--accent' : '' }}"
                    wire:key="featured-{{ $coin->id }}"
                >
                    <div class="afmc-feature__top">
                        <div>
                            <p class="afmc-feature__name">
                                {{ $coin->name }}
                                <span class="afmc-feature__symbol">{{ strtoupper((string) $coin->symbol) }}</span>
                            </p>
                        </div>
                        <span class="afmc-feature__rank">#{{ $coin->rank ?? '—' }}</span>
                    </div>
                    <div class="afmc-feature__price-row">
                        <div style="display:grid;gap:var(--space-2)">
                            <span class="afmc-feature__price">{{ MarketNumberFormatter::money($coin->price !== null ? (float) $coin->price : null, 8) }}</span>
                            <x-afmc.price-change :value="$change24" chip />
                        </div>
                        <x-afmc.sparkline :data="$spark" :up="($change24 ?? 0) >= 0" :width="190" :height="52" />
                    </div>
                    <dl class="afmc-feature__stats">
                        <div>
                            <dt>{{ __('Market cap') }}</dt>
                            <dd>{{ MarketNumberFormatter::money($coin->market_cap !== null ? (float) $coin->market_cap : null) }}</dd>
                        </div>
                        <div>
                            <dt>{{ __('Volume 24h') }}</dt>
                            <dd>{{ MarketNumberFormatter::money($coin->volume_24h !== null ? (float) $coin->volume_24h : null) }}</dd>
                        </div>
                        @if ($index === 0 && $btcDominance !== null)
                            <div>
                                <dt>{{ __('Dominance') }}</dt>
                                <dd>{{ MarketNumberFormatter::percent($btcDominance) }}</dd>
                            </div>
                        @endif
                    </dl>
                </a>
            @endforeach
        </div>
    @endif

    <livewire:market-kpi-strip />

    <div style="display:flex;align-items:center;justify-content:space-between;gap:var(--space-4);margin-bottom:var(--space-3);flex-wrap:wrap">
        <div class="afmc-tabs" role="tablist">
            <button type="button" class="afmc-tabs__item {{ $tab === 'all' ? 'is-active' : '' }}" wire:click="setTab('all')">
                {{ __('All coins') }}
                <span class="afmc-tabs__count">{{ number_format($coinCount) }}</span>
            </button>
            <button type="button" class="afmc-tabs__item {{ $tab === 'gainers' ? 'is-active' : '' }}" wire:click="setTab('gainers')">{{ __('Gainers') }}</button>
            <button type="button" class="afmc-tabs__item {{ $tab === 'losers' ? 'is-active' : '' }}" wire:click="setTab('losers')">{{ __('Losers') }}</button>
        </div>
        <label class="afmc-check">
            <input type="checkbox" wire:model.live="dense" />
            {{ __('Dense rows') }}
        </label>
    </div>

    <div class="afmc-card" wire:loading.class="afmc-is-loading" wire:target="sortBy,setTab,gotoPage,previousPage,nextPage,dense,search">
        <div class="afmc-table-wrap" data-afmc-tablescroll role="region" aria-label="{{ __('Cryptocurrency prices by market cap') }}" tabindex="0">
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
                        <th class="is-right hide-narrow">{{ __('Last 7 days') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($coins as $index => $coin)
                        @php
                            $change24 = $display->change($coin->percent_change_24h);
                            $spark = $display->sparkline($coin->sparkline_7d);
                        @endphp
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
                            <td class="is-right hide-narrow"><x-afmc.price-change :value="$display->change($coin->percent_change_1h, '1h')" size="sm" /></td>
                            <td class="is-right"><x-afmc.price-change :value="$change24" size="sm" /></td>
                            <td class="is-right"><x-afmc.price-change :value="$display->change($coin->percent_change_7d, '7d')" size="sm" /></td>
                            <td class="is-right">{{ MarketNumberFormatter::money($coin->market_cap !== null ? (float) $coin->market_cap : null) }}</td>
                            <td class="is-right">{{ MarketNumberFormatter::money($coin->volume_24h !== null ? (float) $coin->volume_24h : null) }}</td>
                            <td class="is-right hide-narrow">
                                <x-afmc.sparkline :data="$spark" :up="($change24 ?? 0) >= 0" />
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" style="height:auto;padding:var(--space-8);text-align:center;color:var(--text-faint);font:var(--type-body-sm)">
                                {{ __('No coins yet. Run php artisan marketdata:sync') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($coins->hasPages())
            <div class="afmc-pagination">
                <span class="afmc-pagination__meta">
                    {{ __('Showing') }}
                    {{ $coins->firstItem() }}–{{ $coins->lastItem() }}
                    {{ __('of') }}
                    {{ number_format($coins->total()) }}
                </span>
                <div class="afmc-pagination__links">
                    {{ $coins->onEachSide(1)->links('pagination.afmc') }}
                </div>
            </div>
        @endif
    </div>

    <div class="afmc-callout afmc-callout--risk" style="margin-top:var(--space-4)">
        <p class="afmc-callout__title">{{ __('Most of the long tail is worthless') }}</p>
        <p class="afmc-callout__body">
            {{ __('Of the assets we track, many have thin books, short history, or unverified contracts. Assume a total loss is possible, and never size a position on a 24h percentage.') }}
        </p>
    </div>

    <section id="pledge" class="afmc-pledge" style="margin-top:var(--space-10)">
        <div class="afmc-pledge__top">
            <div>
                <h2 class="afmc-pledge__statement">{{ __('No ads. No paid rankings. No sponsored listings.') }}</h2>
                <p class="afmc-pledge__detail">
                    {{ __('This site is paid for out of the creator\'s own pocket. Nobody can pay to appear, to move up, or to lose a risk flag. The picks below are companies our creator uses or has a strategic partnership with. Each card names the relationship, and none of them are paid placements.') }}
                </p>
            </div>
        </div>
        <div class="afmc-pledge__items">
            <div>
                <div class="afmc-pledge__item-label"><x-afmc.icon name="block" />{{ __('Zero ad slots') }}</div>
                <p class="afmc-pledge__item-detail">{{ __('None sold, none planned, no house ads.') }}</p>
            </div>
            <div>
                <div class="afmc-pledge__item-label"><x-afmc.icon name="balance" />{{ __('Rank is market cap') }}</div>
                <p class="afmc-pledge__item-detail">{{ __('One formula, published, applied to every asset.') }}</p>
            </div>
            <div>
                <div class="afmc-pledge__item-label"><x-afmc.icon name="visibility" />{{ __('Risk flags are ours') }}</div>
                <p class="afmc-pledge__item-detail">{{ __('Set by liquidity and history, never negotiable.') }}</p>
            </div>
            <div>
                <div class="afmc-pledge__item-label"><x-afmc.icon name="savings" />{{ __('Funded by the creator') }}</div>
                <p class="afmc-pledge__item-detail">{{ __('Out of pocket. No ads, no sponsors, no investors to please.') }}</p>
            </div>
        </div>
    </section>

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
                        <span class="afmc-pick__badge{{ $isPartner ? ' afmc-pick__badge--warn' : '' }}">{{ $isPartner ? __('Creator is a partner') : __('We use this') }}</span>
                    </div>
                    <h3 class="afmc-pick__title">{{ $pick['name'] }}</h3>
                    <p class="afmc-pick__note">{{ __($pick['note']) }}</p>
                    <div class="afmc-pick__foot">
                        <span>{{ parse_url($pick['url'], PHP_URL_HOST) }}<span class="afmc-visually-hidden">{{ __(', opens in a new tab') }}</span></span>
                        <x-afmc.icon name="arrow_outward" size="16px" />
                    </div>
                </a>
            @endforeach
        </div>
    </section>
</main>
