@php
    use App\Services\Currency\MarketDisplayService;
    use App\Services\MarketData\DexQualityAssessor;
    use App\Services\MarketData\MarketNumberFormatter;

    $display = app(MarketDisplayService::class);
    $quality = app(DexQualityAssessor::class);
@endphp

<main data-afmc-page class="afmc-page">
    <div class="afmc-pagehead">
        <div class="afmc-pagehead__title">
            <h1 style="font:var(--type-h2);margin:0">{{ __('DexScan: on-chain pairs by liquidity') }}</h1>
            <span style="font:var(--type-body-sm);color:var(--text-faint)">{{ __('Listing is automatic, never paid') }}</span>
        </div>
        <div class="afmc-search afmc-pagehead__search">
            <x-afmc.icon name="search" size="18px" color="var(--text-faint)" />
            <input type="search" wire:model.live.debounce.300ms="search" placeholder="{{ __('Search pair or contract address') }}" />
        </div>
    </div>

    <div class="afmc-callout afmc-callout--risk" style="margin-bottom:var(--space-5)">
        <x-afmc.icon name="report" filled class="afmc-callout__icon" />
        <div class="afmc-callout__content">
            <p class="afmc-callout__title">{{ __('Read this before you trade anything on this page') }}</p>
            <p class="afmc-callout__body">
                {{ __('Most tokens listed here will go to zero. A pool can be drained, a contract can block selling, and a chart that looks like a large gain in a few hours is usually thin liquidity. Check the contract address rather than the ticker, and never put in money you need back.') }}
            </p>
            <div style="margin-top:var(--space-1)">
                <button type="button" class="afmc-btn afmc-btn--secondary afmc-btn--sm" wire:click="$set('verifiedOnly', true)">{{ __('Hide unverified') }}</button>
            </div>
        </div>
    </div>

    <div class="afmc-kpi-row">
        <div class="afmc-kpi">
            <span class="afmc-ticker__label">{{ __('DEX volume 24h') }}</span>
            <span class="afmc-ticker__value">{{ MarketNumberFormatter::money($stats['volume']) }}</span>
        </div>
        <div class="afmc-kpi">
            <span class="afmc-ticker__label">{{ __('Tracked pairs') }}</span>
            <span class="afmc-ticker__value">{{ number_format($stats['pairs']) }}</span>
        </div>
        <div class="afmc-kpi">
            <span class="afmc-ticker__label">{{ __('New pairs 24h') }}</span>
            <span class="afmc-ticker__value">{{ number_format($stats['new_24h']) }}</span>
        </div>
    </div>

    <div style="display:flex;align-items:center;justify-content:space-between;gap:var(--space-4);margin-bottom:var(--space-3);flex-wrap:wrap">
        <div class="afmc-tabs" data-afmc-tabs role="tablist">
            @foreach (['trending' => __('Trending'), 'gainers' => __('Gainers'), 'new' => __('New pairs'), 'liquidity' => __('Top liquidity')] as $value => $label)
                <button
                    type="button"
                    role="tab"
                    aria-selected="{{ $tab === $value ? 'true' : 'false' }}"
                    tabindex="{{ $tab === $value ? '0' : '-1' }}"
                    class="afmc-tabs__item {{ $tab === $value ? 'is-active' : '' }}"
                    wire:click="setTab('{{ $value }}')"
                >{{ $label }}</button>
            @endforeach
        </div>
        <label class="afmc-switch" title="{{ __('Hides pairs whose base token is not listed on Markets and has no CoinGecko id.') }}">
            <input type="checkbox" role="switch" wire:model.live="verifiedOnly" />
            <span class="afmc-switch__track" aria-hidden="true"><span class="afmc-switch__thumb"></span></span>
            <span class="afmc-switch__label">{{ __('Hide unverified') }}</span>
        </label>
    </div>

    {{-- Seventeen chains as wrapping pills cost four rows and 180px of a phone screen before
         any data. One panning row with the rest behind More is the same control Markets uses. --}}
    @if ($shownChains->isNotEmpty())
        <x-afmc.network-filter
            :network="$chain"
            :networks="$shownChains"
            :more-networks="$moreChains"
            action="setChain"
            key-prefix="chain"
            :label="__('Filter by chain')"
            :all-label="__('All chains')"
            :search-label="__('Search chains')"
            :empty-label="__('No chain matches your search.')"
            style="margin-bottom:var(--space-4)"
        />
    @endif

    <div class="afmc-card" wire:loading.class="afmc-is-loading" wire:target="setTab,setChain,gotoPage,previousPage,nextPage,verifiedOnly,search">
        @if ($pairs->isEmpty())
            @php
                $filtered = $chain !== 'all' || $tab !== 'trending' || $verifiedOnly || filled($search);
            @endphp
            <div class="afmc-board__empty">
                <div class="afmc-empty">
                    <x-afmc.icon name="search_off" class="afmc-empty__icon" />
                    @if ($filtered)
                        <p class="afmc-empty__title">{{ __('No pairs match this view') }}</p>
                        <p class="afmc-empty__detail">
                            {{ __('A chain, a tab or the unverified filter is narrowing this list. Clear them to see every pair we track.') }}
                        </p>
                        <button type="button" class="afmc-btn afmc-btn--secondary afmc-btn--sm" wire:click="clearFilters">
                            {{ __('Clear filters') }}
                        </button>
                    @else
                        <p class="afmc-empty__title">{{ __('No pairs yet') }}</p>
                        <p class="afmc-empty__detail">
                            {{ __('On-chain pairs have not been synced yet, so there is nothing to rank by liquidity.') }}
                        </p>
                    @endif
                </div>
            </div>
        @else
            {{-- One list, two forms, the 700px rule in afmc.css picks. Nine columns behind a
                 sideways scroll left a phone with the pair and the price on screen. --}}
            <div class="afmc-board">
                <div class="afmc-board__list">
                    <x-afmc.pair-list :pairs="$pairs" :label="__('DexScan pairs')" />
                </div>

                <div class="afmc-board__table afmc-table-wrap" data-afmc-tablescroll role="region" aria-label="{{ __('DexScan pairs') }}" tabindex="0">
                    <table class="afmc-table afmc-table--dense">
                        <thead>
                            <tr>
                                <th class="is-sticky is-sticky--name" style="left:0"><span>{{ __('Pair') }}</span></th>
                                <th class="hide-narrow"><span>{{ __('Quality') }}</span></th>
                                <th class="is-right"><span>{{ __('Price') }}</span></th>
                                <th class="is-right"><span>24h %</span></th>
                                <th class="is-right"><span>{{ __('Liquidity') }}</span></th>
                                <th class="is-right hide-narrow"><span>{{ __('Volume 24h') }}</span></th>
                                <th class="is-right hide-narrow"><span>{{ __('Txns 24h') }}</span></th>
                                <th class="is-right hide-narrow"><span>{{ __('Age') }}</span></th>
                                <th class="is-right"><span>{{ __('Contract') }}</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($pairs as $pair)
                                @php
                                    $audit = strtolower((string) $pair->audit_status);
                                    $tone = match ($audit) {
                                        'verified' => 'up',
                                        'partial' => 'warn',
                                        default => 'down',
                                    };
                                    $label = match ($audit) {
                                        'verified' => __('Verified'),
                                        'partial' => __('Partial'),
                                        default => __('Unverified'),
                                    };
                                @endphp
                                <tr wire:key="dex-{{ $pair->id }}">
                                    <td class="is-wrap is-sticky is-sticky--name" style="left:0">
                                        <a href="{{ route('dexscan.pair', $pair) }}" wire:navigate style="display:grid;gap:2px;text-decoration:none;color:inherit">
                                            <span style="font:var(--weight-semibold) var(--text-sm)/1.2 var(--font-sans);color:var(--text-strong)">{{ $pair->pair }}</span>
                                            <span style="font:var(--type-num);font-size:var(--text-2xs);color:var(--text-faint)">{{ $pair->dex }} · {{ $pair->chain }}</span>
                                        </a>
                                    </td>
                                    <td class="hide-narrow">
                                        {{-- Block form, never @php(…): Blade's raw-block pass matches the
                                             first @php it sees against the next @endphp, so one inline
                                             call swallows every directive up to the next block. --}}
                                        @php
                                            $tier = $quality->describe($pair);
                                        @endphp
                                        <x-afmc.risk-level
                                            :tier="$tier['tier']"
                                            :label="__($tier['label'])"
                                            :why="__($tier['why'])"
                                        />
                                    </td>
                                    <td class="is-right">{{ MarketNumberFormatter::money($pair->price !== null ? (float) $pair->price : null, 8) }}</td>
                                    <td class="is-right"><x-afmc.price-change :value="$display->change($pair->percent_change_24h)" size="sm" /></td>
                                    <td class="is-right">{{ MarketNumberFormatter::money($pair->liquidity_usd !== null ? (float) $pair->liquidity_usd : null) }}</td>
                                    <td class="is-right">{{ MarketNumberFormatter::money($pair->volume_24h !== null ? (float) $pair->volume_24h : null) }}</td>
                                    <td class="is-right">{{ $pair->txns_24h !== null ? number_format($pair->txns_24h) : '—' }}</td>
                                    <td class="is-right">{{ $pair->ageLabel() }}</td>
                                    <td class="is-right">
                                        <span class="afmc-badge afmc-badge--{{ $tone }}">{{ $label }}</span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        @if ($pairs->hasPages())
            <div class="afmc-pagination">
                <span class="afmc-pagination__meta">
                    {{ __('Showing') }} {{ $pairs->firstItem() }}–{{ $pairs->lastItem() }} {{ __('of') }} {{ number_format($pairs->total()) }}
                </span>
                <div class="afmc-pagination__links">
                    {{ $pairs->onEachSide(1)->links('pagination.afmc') }}
                </div>
            </div>
        @endif
    </div>

    <div class="afmc-callout" style="margin-top:var(--space-4)">
        <x-afmc.icon name="info" class="afmc-callout__icon" />
        <div class="afmc-callout__content">
            <p class="afmc-callout__title">{{ __('How the Quality column is set') }}</p>
            <p class="afmc-callout__body">{{ __('Pool liquidity, how long the pair has existed, and whether the base token is listed on Markets. A CoinGecko id alone only reaches partial. Nothing about price performance, and nothing a project can pay to change.') }}</p>
        </div>
    </div>
</main>
