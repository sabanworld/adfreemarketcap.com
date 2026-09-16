@php
    use App\Services\Currency\MarketDisplayService;
    use App\Services\MarketData\MarketNumberFormatter;

    $display = app(MarketDisplayService::class);
    $unit = $display->unit();
    $change24 = $display->change($pair->percent_change_24h);
    $chartUp = ($change24 ?? 0) >= 0;
    $values = array_map(static fn (array $point): float => $point[1], $chartPoints ?? []);
    $timestamps = array_map(static fn (array $point): int => (int) $point[0], $chartPoints ?? []);
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
                <h1 style="font:var(--type-h2);margin:0">{{ $pair->pair }}</h1>
                <p style="margin:0;font:var(--type-body-sm);color:var(--text-faint)">
                    {{ $pair->dex }} · {{ $pair->chain }}
                    @if (filled($pair->contract_address))
                        · {{ $truncate($pair->contract_address, 6) }}
                    @endif
                </p>
            </div>
            <div style="display:flex;align-items:flex-end;gap:var(--space-3);flex-wrap:wrap">
                <span class="afmc-num-lg">{{ MarketNumberFormatter::money($pair->price !== null ? (float) $pair->price : null, 8) }}</span>
                <x-afmc.price-change :value="$change24" chip size="lg" />
                <span style="font:var(--type-body-sm);color:var(--text-faint);padding-bottom:4px">{{ __('24h · :currency', ['currency' => $unit->displayCode()]) }}</span>
            </div>
            <div style="display:flex;gap:var(--space-2);flex-wrap:wrap;align-items:center">
                <x-afmc.risk-level
                    :tier="$quality['tier']"
                    :label="__($quality['label'])"
                    :why="__($quality['why'])"
                />
                @if ($token)
                    <a href="{{ route('dexscan.token', ['network' => $token->network_id, 'address' => $token->address]) }}" wire:navigate class="afmc-btn afmc-btn--secondary afmc-btn--sm">
                        {{ __('Token page') }}
                    </a>
                @endif
                @if ($marketsCoin)
                    <a href="{{ route('coins.show', $marketsCoin) }}" wire:navigate class="afmc-btn afmc-btn--secondary afmc-btn--sm">
                        {{ __('View on Markets') }}
                    </a>
                @endif
            </div>
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
                            aria-label="{{ __(':pair price over :range in :currency', [
                                'pair' => $pair->pair,
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
                        <h2 class="afmc-card__title">{{ __(':pair at a glance', ['pair' => $pair->pair]) }}</h2>
                    </div>
                </div>
                <div class="afmc-card__body">
                    <dl class="afmc-statlist">
                        <div>
                            <dt>{{ __('Market cap') }}</dt>
                            <dd><span class="afmc-statlist__value">{{ MarketNumberFormatter::money($pair->market_cap_usd !== null ? (float) $pair->market_cap_usd : null) }}</span></dd>
                        </div>
                        <div>
                            <dt>{{ __('FDV') }}</dt>
                            <dd><span class="afmc-statlist__value">{{ MarketNumberFormatter::money($pair->fdv_usd !== null ? (float) $pair->fdv_usd : null) }}</span></dd>
                        </div>
                        <div>
                            <dt>{{ __('Liquidity') }}</dt>
                            <dd><span class="afmc-statlist__value">{{ MarketNumberFormatter::money($pair->liquidity_usd !== null ? (float) $pair->liquidity_usd : null) }}</span></dd>
                        </div>
                        <div>
                            <dt>{{ __('Volume 24h') }}</dt>
                            <dd><span class="afmc-statlist__value">{{ MarketNumberFormatter::money($pair->volume_24h !== null ? (float) $pair->volume_24h : null) }}</span></dd>
                        </div>
                        <div>
                            <dt>{{ __('Txns 24h') }}</dt>
                            <dd>
                                <span class="afmc-statlist__note">
                                    @if ($pair->buys_24h !== null || $pair->sells_24h !== null)
                                        {{ __('Buys :buys · sells :sells', [
                                            'buys' => number_format((int) ($pair->buys_24h ?? 0)),
                                            'sells' => number_format((int) ($pair->sells_24h ?? 0)),
                                        ]) }}
                                    @endif
                                </span>
                                <span class="afmc-statlist__value">{{ $pair->txns_24h !== null ? number_format($pair->txns_24h) : '—' }}</span>
                            </dd>
                        </div>
                        <div>
                            <dt>{{ __('Age') }}</dt>
                            <dd><span class="afmc-statlist__value">{{ $pair->ageLabel() }}</span></dd>
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
                        <dl class="afmc-statlist">
                            <div>
                                <dt>{{ __('Pool liquidity') }}</dt>
                                <dd><span class="afmc-statlist__value">{{ MarketNumberFormatter::money($pair->liquidity_usd !== null ? (float) $pair->liquidity_usd : null) }}</span></dd>
                            </div>
                            <div>
                                <dt>{{ __('Pool address') }}</dt>
                                <dd><span class="afmc-statlist__value" style="font:var(--type-num);font-size:var(--text-xs)">{{ $pair->contract_address ?: '—' }}</span></dd>
                            </div>
                            <div>
                                <dt>{{ __('Base token') }}</dt>
                                <dd>
                                    @if ($token)
                                        <a href="{{ route('dexscan.token', ['network' => $token->network_id, 'address' => $token->address]) }}" wire:navigate>
                                            {{ $truncate($token->address, 6) }}
                                        </a>
                                    @else
                                        <span class="afmc-statlist__value">{{ $truncate($pair->base_token_address, 6) }}</span>
                                    @endif
                                </dd>
                            </div>
                        </dl>
                    @else
                        @if ($token)
                            @include('livewire.partials.dex-holders-table', [
                                'holders' => $holders,
                                'truncate' => $truncate,
                                'emptyMessage' => __('Top holders will appear after the next token sync. CoinGecko onchain access is required.'),
                            ])
                        @else
                            <p style="margin:0;font:var(--type-body-sm);color:var(--text-faint)">{{ __('Holders need a known base token address.') }}</p>
                        @endif
                    @endif
                </div>
            </section>

            <div class="afmc-callout afmc-callout--risk">
                <x-afmc.icon name="report" filled class="afmc-callout__icon" />
                <div class="afmc-callout__content">
                    <p class="afmc-callout__title">{{ __('On-chain risk') }}</p>
                    <p class="afmc-callout__body">{{ __('Trades are a recent snapshot (about the last 24 hours), not a full history. Thin pools can move far on small volume.') }}</p>
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
    const timestamps = @js($timestamps);
    const range = @js($chartRange);
    const values = @js($values);
    const up = @js($chartUp);
    const symbol = @js($unit->symbol);
    const symbolAfter = @js((bool) $unit->symbolAfter);

    requestAnimationFrame(() => {
        if (window.afmcMountDexChart) {
            window.afmcMountDexChart({ timestamps, range, values, up, symbol, symbolAfter });
        }
    });
</script>
@endscript
