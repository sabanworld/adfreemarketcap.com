@php
    use App\Services\Currency\MarketDisplayService;
    use App\Services\MarketData\MarketNumberFormatter;

    $display = app(MarketDisplayService::class);
@endphp

<main data-afmc-page class="afmc-page">
    <div style="display:flex;align-items:baseline;justify-content:space-between;gap:var(--space-4);flex-wrap:wrap;margin-bottom:var(--space-4)">
        <div style="display:flex;align-items:baseline;gap:var(--space-3);flex-wrap:wrap">
            <h1 style="font:var(--type-h2);margin:0">{{ __('DexScan: on-chain pairs by liquidity') }}</h1>
            <span style="font:var(--type-body-sm);color:var(--text-faint)">{{ __('Listing is automatic, never paid') }}</span>
        </div>
        <div class="afmc-search" style="max-width:280px">
            <x-afmc.icon name="search" size="18px" color="var(--text-faint)" />
            <input type="search" wire:model.live.debounce.300ms="search" placeholder="{{ __('Search pair or contract address') }}" />
        </div>
    </div>

    <div class="afmc-callout afmc-callout--risk" style="margin-bottom:var(--space-5)">
        <p class="afmc-callout__title">{{ __('Read this before you trade anything on this page') }}</p>
        <p class="afmc-callout__body">
            {{ __('Most tokens listed here will go to zero. A pool can be drained, a contract can block selling, and a chart that looks like a large gain in a few hours is usually thin liquidity. Check the contract address rather than the ticker, and never put in money you need back.') }}
        </p>
        <div style="margin-top:var(--space-2)">
            <button type="button" class="afmc-btn afmc-btn--secondary afmc-btn--sm" wire:click="$set('verifiedOnly', true)">{{ __('Hide unverified') }}</button>
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
        <div class="afmc-tabs" role="tablist">
            @foreach (['trending' => __('Trending'), 'gainers' => __('Gainers'), 'new' => __('New pairs'), 'liquidity' => __('Top liquidity')] as $value => $label)
                <button type="button" class="afmc-tabs__item {{ $tab === $value ? 'is-active' : '' }}" wire:click="setTab('{{ $value }}')">{{ $label }}</button>
            @endforeach
        </div>
        <label class="afmc-check">
            <input type="checkbox" wire:model.live="verifiedOnly" />
            {{ __('Verified only') }}
        </label>
    </div>

    <div style="display:flex;gap:var(--space-2);flex-wrap:wrap;margin-bottom:var(--space-4)">
        <button type="button" class="afmc-tag {{ $chain === 'all' ? 'is-active' : '' }}" wire:click="setChain('all')" style="{{ $chain === 'all' ? 'background:var(--ink-900);color:var(--text-inverse);border-color:var(--ink-900)' : '' }}">{{ __('All chains') }}</button>
        @foreach ($chains as $chainName)
            <button type="button" class="afmc-tag" wire:click="setChain('{{ $chainName }}')" style="{{ $chain === $chainName ? 'background:var(--ink-900);color:var(--text-inverse);border-color:var(--ink-900)' : '' }}">{{ $chainName }}</button>
        @endforeach
    </div>

    <div class="afmc-card">
        <div class="afmc-table-wrap">
            <table class="afmc-table afmc-table--dense">
                <thead>
                    <tr>
                        <th>{{ __('Pair') }}</th>
                        <th class="is-right">{{ __('Price') }}</th>
                        <th class="is-right">24h %</th>
                        <th class="is-right">{{ __('Liquidity') }}</th>
                        <th class="is-right">{{ __('Volume 24h') }}</th>
                        <th class="is-right">{{ __('Txns 24h') }}</th>
                        <th class="is-right">{{ __('Age') }}</th>
                        <th class="is-right">{{ __('Contract') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($pairs as $pair)
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
                            <td class="is-wrap">
                                <span style="display:grid;gap:2px">
                                    <span style="font:var(--weight-semibold) var(--text-sm)/1.2 var(--font-sans);color:var(--text-strong)">{{ $pair->pair }}</span>
                                    <span style="font:var(--type-num);font-size:var(--text-2xs);color:var(--text-faint)">{{ $pair->dex }} · {{ $pair->chain }}</span>
                                </span>
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
                    @empty
                        <tr>
                            <td colspan="8" style="height:auto;padding:var(--space-8);text-align:center;color:var(--text-faint);font:var(--type-body-sm)">
                                {{ __('No DEX pairs yet. Sync with php artisan marketdata:sync --only-dex') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
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

    <div class="afmc-callout afmc-callout--note" style="margin-top:var(--space-4)">
        <p class="afmc-callout__title">{{ __('How the Quality column is set') }}</p>
        <p class="afmc-callout__body">{{ __('Pool liquidity, pair age, venue count and contract verification. Nothing about price performance, and nothing a project can pay to change.') }}</p>
    </div>
</main>
