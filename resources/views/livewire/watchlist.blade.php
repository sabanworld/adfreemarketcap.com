@php
    use App\Services\Currency\MarketDisplayService;
    use App\Services\MarketData\MarketNumberFormatter;

    $display = app(MarketDisplayService::class);
@endphp

<main data-afmc-page class="afmc-page" wire:poll.30s>
    <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:var(--space-4);margin-bottom:var(--space-5);flex-wrap:wrap">
        <div style="display:grid;gap:var(--space-1)">
            <h1 style="font:var(--type-h1);margin:0">{{ __('Main') }}</h1>
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
        <div class="afmc-callout afmc-callout--note">
            <p class="afmc-callout__title">{{ __('Nothing here yet') }}</p>
            <p class="afmc-callout__body">{{ __('Star any coin on Markets to track it here. Your list is tied to this account.') }}</p>
        </div>
    @else
        <div class="afmc-grid afmc-grid--stats" style="margin-bottom:var(--space-5)">
            <div class="afmc-stat">
                <span class="afmc-stat__label">{{ __('Assets watched') }}</span>
                <span class="afmc-stat__value">{{ $coins->count() }}</span>
            </div>
            <div class="afmc-stat">
                <span class="afmc-stat__label">{{ __('Best 24h') }}</span>
                <span class="afmc-stat__value">{{ $best?->symbol ? strtoupper((string) $best->symbol) : '—' }}</span>
                <x-afmc.price-change :value="$display->change($best?->percent_change_24h)" chip size="sm" />
            </div>
            <div class="afmc-stat">
                <span class="afmc-stat__label">{{ __('Worst 24h') }}</span>
                <span class="afmc-stat__value">{{ $worst?->symbol ? strtoupper((string) $worst->symbol) : '—' }}</span>
                <x-afmc.price-change :value="$display->change($worst?->percent_change_24h)" chip size="sm" />
            </div>
        </div>

        <div class="afmc-card">
            <div class="afmc-card__header">
                <div>
                    <span class="afmc-card__eyebrow">{{ __('Watchlist') }}</span>
                    <h2 class="afmc-card__title">{{ __('Holdings view') }}</h2>
                </div>
            </div>
            <div class="afmc-table-wrap" data-afmc-tablescroll role="region" aria-label="{{ __('Watchlist') }}" tabindex="0">
                <table class="afmc-table">
                    <thead>
                        <tr>
                            <th class="is-sticky is-sticky--watch hide-narrow" style="width:38px;left:0"></th>
                            <th class="is-sticky is-sticky--name" style="left:38px">{{ __('Name') }}</th>
                            <th class="is-right">{{ __('Price') }}</th>
                            <th class="is-right">24h %</th>
                            <th class="is-right">7d %</th>
                            <th class="is-right">{{ __('Market cap') }}</th>
                            <th class="is-right hide-narrow">{{ __('Last 7 days') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($coins as $coin)
                            @php
                                $change24 = $display->change($coin->percent_change_24h);
                                $spark = $display->sparkline($coin->sparkline_7d);
                            @endphp
                            <tr wire:key="watch-{{ $coin->id }}">
                                <td class="is-sticky is-sticky--watch hide-narrow" style="left:0">
                                    <livewire:watch-toggle :coin="$coin" :key="'watch-toggle-'.$coin->id" />
                                </td>
                                <td class="is-sticky is-sticky--name" style="left:38px">
                                    <x-afmc.coin-identity
                                        :name="$coin->name"
                                        :symbol="$coin->symbol"
                                        :rank="$coin->rank"
                                        :image="$coin->image_url"
                                        :href="route('coins.show', $coin)"
                                    />
                                </td>
                                <td class="is-right">{{ MarketNumberFormatter::money($coin->price !== null ? (float) $coin->price : null, 8) }}</td>
                                <td class="is-right"><x-afmc.price-change :value="$change24" size="sm" /></td>
                                <td class="is-right"><x-afmc.price-change :value="$display->change($coin->percent_change_7d, '7d')" size="sm" /></td>
                                <td class="is-right">{{ MarketNumberFormatter::money($coin->market_cap !== null ? (float) $coin->market_cap : null) }}</td>
                                <td class="is-right hide-narrow"><x-afmc.sparkline :data="$spark" :up="($change24 ?? 0) >= 0" /></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</main>
