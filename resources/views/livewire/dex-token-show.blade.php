@php
    use App\Services\Currency\MarketDisplayService;
    use App\Services\MarketData\MarketNumberFormatter;

    $display = app(MarketDisplayService::class);
    $unit = $display->unit();
    $change24 = $display->change($token->percent_change_24h);
    $chartUp = ($change24 ?? 0) >= 0;
    $labels = $chartLabels ?? [];
    $values = array_map(static fn (array $point): float => $point[1], $chartPoints ?? []);
    $truncate = static function (?string $value, int $keep = 4): string {
        if (! filled($value)) {
            return '—';
        }
        $value = (string) $value;
        if (strlen($value) <= ($keep * 2) + 3) {
            return $value;
        }

        return substr($value, 0, $keep) . '…' . substr($value, -$keep);
    };
@endphp

<main data-afmc-page class="afmc-page afmc-page--detail" @if ($needsPoll) wire:poll.5s="refreshDetail" @endif>
    <a href="{{ route('dexscan') }}" wire:navigate class="afmc-back">
        <x-afmc.icon name="arrow_back" size="16px" />
        {{ __('DexScan') }}
    </a>

    <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:var(--space-6);margin-bottom:var(--space-5);flex-wrap:wrap">
        <div style="display:grid;gap:var(--space-3)">
            <div style="display:grid;gap:4px">
                <h1 style="font:var(--type-h2);margin:0">{{ $token->displayName() }}</h1>
                <p style="margin:0;font:var(--type-body-sm);color:var(--text-faint)">
                    {{ strtoupper($token->symbol) }} · {{ $token->network_id }} · {{ $truncate($token->address, 6) }}
                </p>
            </div>
            <div style="display:flex;align-items:flex-end;gap:var(--space-3);flex-wrap:wrap">
                <span class="afmc-num-lg">{{ MarketNumberFormatter::money($token->price !== null ? (float) $token->price : null, 8) }}</span>
                <x-afmc.price-change :value="$change24" chip size="lg" />
                <span style="font:var(--type-body-sm);color:var(--text-faint);padding-bottom:4px">{{ __('24h · :currency', ['currency' => $unit->displayCode()]) }}</span>
            </div>
            @if ($marketsCoin)
                <div>
                    <a href="{{ route('coins.show', $marketsCoin) }}" wire:navigate class="afmc-btn afmc-btn--secondary afmc-btn--sm">
                        {{ __('View on Markets') }}
                    </a>
                </div>
            @endif
        </div>
    </div>

    <div class="afmc-grid afmc-grid--detail">
        <div style="display:grid;gap:var(--space-3)">
            <section class="afmc-card">
                <div class="afmc-card__body">
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:var(--space-3);gap:var(--space-3);flex-wrap:wrap">
                        <span class="afmc-card__eyebrow">{{ __('Price') }}</span>
                        <div class="afmc-chart-ranges" role="group" aria-label="{{ __('Chart range') }}">
                            @foreach ($rangeMeta as $key => $meta)
                                @php
                                    $isAvailable = in_array($key, $availableRanges, true);
                                    $isActive = $chartRange === $key;
                                @endphp
                                <button
                                    type="button"
                                    class="afmc-chart-ranges__item {{ $isActive ? 'is-active' : '' }}"
                                    wire:click="setChartRange('{{ $key }}')"
                                    @disabled(! $isAvailable)
                                    aria-pressed="{{ $isActive ? 'true' : 'false' }}"
                                >{{ $meta['label'] }}</button>
                            @endforeach
                        </div>
                    </div>
                    @if (count($values))
                        <div
                            class="afmc-chart-frame"
                            role="img"
                            aria-label="{{ __(':name price over :range in :currency', [
                                'name' => $token->displayName(),
                                'range' => $spokenRange,
                                'currency' => $unit->displayCode(),
                            ]) }}"
                        >
                            <canvas wire:ignore id="dex-chart" height="280"></canvas>
                        </div>
                    @else
                        <p style="margin:0;font:var(--type-body-sm);color:var(--text-faint)">{{ __('Chart data will appear after the next sync.') }}</p>
                    @endif
                </div>
            </section>

            <section class="afmc-card">
                <div class="afmc-card__header">
                    <div>
                        <span class="afmc-card__eyebrow">{{ __('Key stats') }}</span>
                        <h2 class="afmc-card__title">{{ __(':symbol at a glance', ['symbol' => strtoupper($token->symbol)]) }}</h2>
                    </div>
                </div>
                <div class="afmc-card__body">
                    <dl class="afmc-statlist">
                        <div>
                            <dt>{{ __('Market cap') }}</dt>
                            <dd><span class="afmc-statlist__value">{{ MarketNumberFormatter::money($token->market_cap_usd !== null ? (float) $token->market_cap_usd : null) }}</span></dd>
                        </div>
                        <div>
                            <dt>{{ __('FDV') }}</dt>
                            <dd><span class="afmc-statlist__value">{{ MarketNumberFormatter::money($token->fdv_usd !== null ? (float) $token->fdv_usd : null) }}</span></dd>
                        </div>
                        <div>
                            <dt>{{ __('Liquidity') }}</dt>
                            <dd><span class="afmc-statlist__value">{{ MarketNumberFormatter::money($token->liquidity_usd !== null ? (float) $token->liquidity_usd : null) }}</span></dd>
                        </div>
                        <div>
                            <dt>{{ __('Volume 24h') }}</dt>
                            <dd><span class="afmc-statlist__value">{{ MarketNumberFormatter::money($token->volume_24h !== null ? (float) $token->volume_24h : null) }}</span></dd>
                        </div>
                        <div>
                            <dt>{{ __('Holders') }}</dt>
                            <dd><span class="afmc-statlist__value">{{ $token->holders_count !== null ? number_format($token->holders_count) : '—' }}</span></dd>
                        </div>
                    </dl>
                </div>
            </section>
        </div>

        <div style="display:grid;gap:var(--space-3)">
            <section class="afmc-card">
                <div class="afmc-card__body">
                    <div class="afmc-tabs" role="tablist" style="margin-bottom:var(--space-3)">
                        @foreach (['trades' => __('Trades'), 'liquidity' => __('Liquidity'), 'holders' => __('Holders')] as $value => $label)
                            <button
                                type="button"
                                role="tab"
                                aria-selected="{{ $tab === $value ? 'true' : 'false' }}"
                                class="afmc-tabs__item {{ $tab === $value ? 'is-active' : '' }}"
                                wire:click="setTab('{{ $value }}')"
                            >{{ $label }}</button>
                        @endforeach
                    </div>

                    @if ($tab === 'trades')
                        @include('livewire.partials.dex-trades-table', ['trades' => $trades, 'truncate' => $truncate])
                    @elseif ($tab === 'liquidity')
                        <div class="afmc-table-wrap" role="region" aria-label="{{ __('Liquidity pools') }}" tabindex="0">
                            <table class="afmc-table afmc-table--dense">
                                <thead>
                                    <tr>
                                        <th><span>{{ __('Pair') }}</span></th>
                                        <th class="is-right"><span>{{ __('Liquidity') }}</span></th>
                                        <th class="is-right"><span>{{ __('Volume 24h') }}</span></th>
                                        <th class="hide-narrow"><span>{{ __('DEX') }}</span></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($pools as $pool)
                                        <tr wire:key="dex-pool-{{ $pool->id }}">
                                            <td>
                                                <a href="{{ route('dexscan.pair', $pool) }}" wire:navigate>
                                                    {{ $pool->pair }}
                                                </a>
                                            </td>
                                            <td class="is-right">{{ MarketNumberFormatter::money($pool->liquidity_usd !== null ? (float) $pool->liquidity_usd : null) }}</td>
                                            <td class="is-right">{{ MarketNumberFormatter::money($pool->volume_24h !== null ? (float) $pool->volume_24h : null) }}</td>
                                            <td class="hide-narrow">{{ $pool->dex }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4" class="afmc-table__empty">
                                                <p style="margin:0;font:var(--type-body-sm);color:var(--text-faint)">{{ __('Related pools will appear after the next sync.') }}</p>
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    @else
                        @include('livewire.partials.dex-holders-table', [
                            'holders' => $holders,
                            'truncate' => $truncate,
                            'emptyMessage' => $holdersWarming
                                ? __('Top holders are warming up. CoinGecko onchain access is required for this tab.')
                                : __('No top holders stored for this token yet.'),
                        ])
                    @endif
                </div>
            </section>

            <div class="afmc-callout afmc-callout--risk">
                <x-afmc.icon name="report" filled class="afmc-callout__icon" />
                <div class="afmc-callout__content">
                    <p class="afmc-callout__title">{{ __('On-chain risk') }}</p>
                    <p class="afmc-callout__body">{{ __('Most tokens here can go to zero. Trades are a recent snapshot, and holder lists need CoinGecko onchain access.') }}</p>
                </div>
            </div>
        </div>
    </div>
</main>

@assets
    @vite('resources/js/dex-chart.js')
@endassets

@script
<script>
    const labels = @js($labels);
    const values = @js($values);
    const up = @js($chartUp);
    const symbol = @js($unit->symbol);
    const symbolAfter = @js((bool) $unit->symbolAfter);

    requestAnimationFrame(() => {
        if (window.afmcMountDexChart) {
            window.afmcMountDexChart({ labels, values, up, symbol, symbolAfter });
        }
    });
</script>
@endscript
