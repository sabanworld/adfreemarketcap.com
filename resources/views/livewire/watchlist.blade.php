@php
    use App\Services\Currency\MarketDisplayService;
    use App\Services\MarketData\MarketNumberFormatter;

    $display = app(MarketDisplayService::class);
@endphp

<main data-afmc-page class="afmc-page" wire:poll.visible.60s>
    <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:var(--space-4);margin-bottom:var(--space-5);flex-wrap:wrap">
        <div style="display:grid;gap:var(--space-1)">
            <h1 style="font:var(--type-h2);margin:0">{{ __('Watchlist') }}</h1>
            <span style="font:var(--type-body-sm);color:var(--text-muted)">
                {{ trans_choice(':count asset|:count assets', $coins->count(), ['count' => $coins->count()]) }}
                · {{ __('saved to your account') }}
            </span>
        </div>
        <a href="{{ route('home') }}" wire:navigate class="afmc-btn afmc-btn--secondary afmc-btn--sm">
            <x-afmc.icon name="add" size="16px" />
            {{ __('Add coins') }}
        </a>
    </div>

    @if ($coins->isEmpty())
        <div class="afmc-callout">
            <x-afmc.icon name="info" class="afmc-callout__icon" />
            <div class="afmc-callout__content">
                <p class="afmc-callout__title">{{ __('Nothing here yet') }}</p>
                <p class="afmc-callout__body">{{ __('Star any coin on Markets to track it here. Your list is tied to this account.') }}</p>
            </div>
        </div>
    @else
        <div class="afmc-grid afmc-grid--stats" style="margin-bottom:var(--space-5)">
            {{-- The asset count sits in the subtitle above, so this row opens on the figure the
                 page does not state anywhere else. --}}
            <div class="afmc-stat">
                <span class="afmc-stat__label">{{ __('List 24h') }}</span>
                <span class="afmc-stat__value">{{ MarketNumberFormatter::percent($display->change($averageChange)) }}</span>
                <span class="afmc-stat__meta">
                    <x-afmc.price-change :value="$display->change($averageChange)" chip size="sm" />
                    <span class="afmc-stat__note">{{ __('Equal-weighted') }}</span>
                </span>
            </div>
            <div class="afmc-stat">
                <span class="afmc-stat__label">{{ __('Best 24h') }}</span>
                <span class="afmc-stat__value">{{ $best?->symbol ? strtoupper((string) $best->symbol) : '—' }}</span>
                <span class="afmc-stat__meta">
                    <x-afmc.price-change :value="$display->change($best?->percent_change_24h)" chip size="sm" />
                </span>
            </div>
            <div class="afmc-stat">
                <span class="afmc-stat__label">{{ __('Worst 24h') }}</span>
                <span class="afmc-stat__value">{{ $worst?->symbol ? strtoupper((string) $worst->symbol) : '—' }}</span>
                <span class="afmc-stat__meta">
                    <x-afmc.price-change :value="$display->change($worst?->percent_change_24h)" chip size="sm" />
                </span>
            </div>
        </div>

        <div class="afmc-card" wire:loading.class="afmc-is-loading" wire:target="toggleWatch">
            <div class="afmc-card__header">
                <div>
                    <span class="afmc-card__eyebrow">{{ __('Watchlist') }}</span>
                    <h2 class="afmc-card__title">{{ __('Holdings view') }}</h2>
                </div>
            </div>
            <div class="afmc-board">
                <div class="afmc-board__list">
                    {{-- Every row here is already starred, so the panel action removes it. --}}
                    <x-afmc.market-list
                        :coins="$coins"
                        :watched-ids="$coins->pluck('id')"
                        :label="__('Watchlist')"
                    />
                </div>

                <div class="afmc-board__table afmc-table-wrap" data-afmc-tablescroll role="region" aria-label="{{ __('Watchlist') }}" tabindex="0">
                    <table class="afmc-table">
                        <thead>
                            <tr>
                                <th class="is-sticky is-sticky--watch hide-narrow" style="width:38px;left:0"></th>
                                <th class="is-sticky is-sticky--name" style="left:38px"><span>{{ __('Name') }}</span></th>
                                <th class="is-right"><span>{{ __('Price') }}</span></th>
                                <th class="is-right"><span>24h %</span></th>
                                <th class="is-right"><span>7d %</span></th>
                                <th class="is-right"><span>{{ __('Market cap') }}</span></th>
                                <th class="is-right hide-narrow"><span>{{ __('Last 7 days') }}</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($coins as $index => $coin)
                                <tr wire:key="watch-{{ $coin->id }}">
                                    <td class="is-sticky is-sticky--watch hide-narrow" style="left:0">
                                        <x-afmc.watch-star
                                            :coin-id="$coin->id"
                                            :watched="true"
                                        />
                                    </td>
                                    <td class="is-sticky is-sticky--name" style="left:38px">
                                        <x-afmc.coin-identity
                                            :name="$coin->name"
                                            :symbol="$coin->symbol"
                                            :rank="$coin->rank"
                                            :image="$coin->image_url"
                                            :href="route('coins.show', $coin)"
                                            :eager="$index < 8"
                                        />
                                    </td>
                                    <td class="is-right">{{ MarketNumberFormatter::money($coin->price !== null ? (float) $coin->price : null, 8) }}</td>
                                    <td class="is-right"><x-afmc.price-change :value="$display->change($coin->percent_change_24h)" size="sm" /></td>
                                    <td class="is-right"><x-afmc.price-change :value="$display->change($coin->percent_change_7d, '7d')" size="sm" /></td>
                                    <td class="is-right">{{ MarketNumberFormatter::money($coin->market_cap !== null ? (float) $coin->market_cap : null) }}</td>
                                    {{-- Colour and slope come from the seven-day series drawn here,
                                         not from the 24h column beside it. --}}
                                    <td class="is-right hide-narrow"><x-afmc.sparkline :data="$display->sparkline($coin->sparkline_7d)" /></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif
</main>
