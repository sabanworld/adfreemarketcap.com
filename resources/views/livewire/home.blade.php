@php
    use App\Services\MarketData\MarketNumberFormatter;

    $btcDominance = $global?->btc_dominance !== null ? (float) $global->btc_dominance : null;
@endphp

<main data-afmc-page class="afmc-page" wire:poll.30s>
    <div style="display:flex;align-items:baseline;justify-content:space-between;gap:var(--space-4);flex-wrap:wrap;margin-bottom:var(--space-4)">
        <div style="display:flex;align-items:baseline;gap:var(--space-3);flex-wrap:wrap">
            <h1 style="font:var(--type-h2);margin:0">{{ __('Cryptocurrency prices by market cap') }}</h1>
            <span style="font:var(--type-body-sm);color:var(--text-faint)">{{ __('Ranking cannot be bought') }}</span>
        </div>
        <span class="afmc-live">
            <span class="afmc-icon" data-fill="1">fiber_manual_record</span>
            {{ __('Live · rankings from synced market data') }}
        </span>
    </div>

    @if ($featured->isNotEmpty())
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:var(--space-3);margin-bottom:var(--space-3)">
            @foreach ($featured as $index => $coin)
                @php
                    $change24 = $coin->percent_change_24h !== null ? (float) $coin->percent_change_24h : null;
                    $spark = is_array($coin->sparkline_7d) ? $coin->sparkline_7d : [];
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

    <div class="afmc-kpi-row">
        <div class="afmc-kpi">
            <span class="afmc-ticker__label">{{ __('Market cap') }}</span>
            <span class="afmc-ticker__value">{{ MarketNumberFormatter::money($global?->total_market_cap !== null ? (float) $global->total_market_cap : null) }}</span>
        </div>
        <div class="afmc-kpi">
            <span class="afmc-ticker__label">{{ __('24h volume') }}</span>
            <span class="afmc-ticker__value">{{ MarketNumberFormatter::money($global?->total_volume_24h !== null ? (float) $global->total_volume_24h : null) }}</span>
        </div>
        <div class="afmc-kpi">
            <span class="afmc-ticker__label">{{ __('BTC dominance') }}</span>
            <span class="afmc-ticker__value">{{ MarketNumberFormatter::percent($btcDominance) }}</span>
        </div>
        <div class="afmc-kpi">
            <span class="afmc-ticker__label">{{ __('Assets tracked') }}</span>
            <span class="afmc-ticker__value">{{ number_format($coinCount) }}</span>
        </div>
    </div>

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

    <div class="afmc-card">
        <div class="afmc-table-wrap">
            <table class="afmc-table {{ $dense ? 'afmc-table--dense' : '' }}">
                <thead>
                    <tr>
                        <th class="{{ $sort === 'rank' ? 'is-sorted' : '' }}" style="width:48px">
                            <button type="button" wire:click="sortBy('rank')">#
                                @if ($sort === 'rank')
                                    <span class="afmc-icon" style="font-size:14px;color:var(--amber-600)">{{ $direction === 'asc' ? 'arrow_drop_up' : 'arrow_drop_down' }}</span>
                                @endif
                            </button>
                        </th>
                        <th class="{{ $sort === 'name' ? 'is-sorted' : '' }}">
                            <button type="button" wire:click="sortBy('name')">{{ __('Name') }}
                                @if ($sort === 'name')
                                    <span class="afmc-icon" style="font-size:14px;color:var(--amber-600)">{{ $direction === 'asc' ? 'arrow_drop_up' : 'arrow_drop_down' }}</span>
                                @endif
                            </button>
                        </th>
                        <th class="is-right {{ $sort === 'price' ? 'is-sorted' : '' }}">
                            <button type="button" wire:click="sortBy('price')">{{ __('Price') }}
                                @if ($sort === 'price')
                                    <span class="afmc-icon" style="font-size:14px;color:var(--amber-600)">{{ $direction === 'asc' ? 'arrow_drop_up' : 'arrow_drop_down' }}</span>
                                @endif
                            </button>
                        </th>
                        <th class="is-right {{ $sort === 'percent_change_1h' ? 'is-sorted' : '' }}">
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
                        <th class="is-right">{{ __('Last 7 days') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($coins as $coin)
                        @php
                            $change24 = $coin->percent_change_24h !== null ? (float) $coin->percent_change_24h : null;
                            $spark = is_array($coin->sparkline_7d) ? $coin->sparkline_7d : [];
                        @endphp
                        <tr wire:key="coin-{{ $coin->id }}">
                            <td class="is-muted">{{ $coin->rank ?? '—' }}</td>
                            <td>
                                <x-afmc.coin-identity
                                    :name="$coin->name"
                                    :symbol="$coin->symbol"
                                    :image="$coin->image_url"
                                    :href="route('coins.show', $coin)"
                                />
                            </td>
                            <td class="is-right">{{ MarketNumberFormatter::money($coin->price !== null ? (float) $coin->price : null, 8) }}</td>
                            <td class="is-right"><x-afmc.price-change :value="$coin->percent_change_1h !== null ? (float) $coin->percent_change_1h : null" size="sm" /></td>
                            <td class="is-right"><x-afmc.price-change :value="$change24" size="sm" /></td>
                            <td class="is-right"><x-afmc.price-change :value="$coin->percent_change_7d !== null ? (float) $coin->percent_change_7d : null" size="sm" /></td>
                            <td class="is-right">{{ MarketNumberFormatter::money($coin->market_cap !== null ? (float) $coin->market_cap : null) }}</td>
                            <td class="is-right">{{ MarketNumberFormatter::money($coin->volume_24h !== null ? (float) $coin->volume_24h : null) }}</td>
                            <td class="is-right">
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
                    {{ __('This site is paid for out of the creator\'s own pocket. Nobody can pay to appear, to move up, or to lose a risk flag, and the picks below are companies our creator uses or has a stake in — stated on each card — never paid placements.') }}
                </p>
            </div>
        </div>
        <div class="afmc-pledge__items">
            <div>
                <div class="afmc-pledge__item-label"><span class="afmc-icon">block</span>{{ __('Zero ad slots') }}</div>
                <p class="afmc-pledge__item-detail">{{ __('None sold, none planned, no house ads.') }}</p>
            </div>
            <div>
                <div class="afmc-pledge__item-label"><span class="afmc-icon">balance</span>{{ __('Rank is market cap') }}</div>
                <p class="afmc-pledge__item-detail">{{ __('One formula, published, applied to every asset.') }}</p>
            </div>
            <div>
                <div class="afmc-pledge__item-label"><span class="afmc-icon">visibility</span>{{ __('Risk flags are ours') }}</div>
                <p class="afmc-pledge__item-detail">{{ __('Set by liquidity and history, never negotiable.') }}</p>
            </div>
            <div>
                <div class="afmc-pledge__item-label"><span class="afmc-icon">savings</span>{{ __('Funded by the creator') }}</div>
                <p class="afmc-pledge__item-detail">{{ __('Out of pocket. No ads, no sponsors, no investors to please.') }}</p>
            </div>
        </div>
    </section>

    <section id="picks" style="margin-top:var(--space-10)">
        <div style="display:flex;align-items:baseline;justify-content:space-between;margin-bottom:var(--space-3);gap:var(--space-3);flex-wrap:wrap">
            <h2 style="margin:0;font:var(--type-h2)">{{ __('Picks, not ads') }}</h2>
            <span style="font:var(--type-body-sm);color:var(--text-faint)">{{ __('Companies we like · any stake is disclosed') }}</span>
        </div>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:var(--space-3)">
            <article class="afmc-pick">
                <div style="display:flex;justify-content:space-between;gap:var(--space-2)">
                    <span class="afmc-pick__kind">{{ __('Hardware') }}</span>
                    <span class="afmc-pick__badge">{{ __('We use this') }}</span>
                </div>
                <h3 class="afmc-pick__title">Trezor</h3>
                <p class="afmc-pick__note">{{ __('Suggested hardware wallet for long-term custody. We use one.') }}</p>
                <div class="afmc-pick__foot">
                    <span>trezor.io</span>
                    <span class="afmc-icon" style="font-size:16px">arrow_outward</span>
                </div>
            </article>
            <article class="afmc-pick">
                <div style="display:flex;justify-content:space-between;gap:var(--space-2)">
                    <span class="afmc-pick__kind">{{ __('Swap') }}</span>
                    <span class="afmc-pick__badge">{{ __('We use this') }}</span>
                </div>
                <h3 class="afmc-pick__title">ChangeNOW</h3>
                <p class="afmc-pick__note">{{ __('Non-custodial swaps when a CEX account is the wrong tool.') }}</p>
                <div class="afmc-pick__foot">
                    <span>changenow.io</span>
                    <span class="afmc-icon" style="font-size:16px">arrow_outward</span>
                </div>
            </article>
            <article class="afmc-pick">
                <div style="display:flex;justify-content:space-between;gap:var(--space-2)">
                    <span class="afmc-pick__kind">{{ __('Mining') }}</span>
                    <span class="afmc-pick__badge afmc-pick__badge--warn">{{ __('Creator is a shareholder') }}</span>
                </div>
                <h3 class="afmc-pick__title">Rigly</h3>
                <p class="afmc-pick__note">{{ __('Bitcoin mining marketplace. The creator holds shares — stated here, not only in a policy.') }}</p>
                <div class="afmc-pick__foot">
                    <span>rigly.io</span>
                    <span class="afmc-icon" style="font-size:16px">arrow_outward</span>
                </div>
            </article>
        </div>
    </section>
</main>
