@props([
    'tickers' => [],
    'initial' => 5,
    'label' => null,
])

@php
    use App\Services\MarketData\MarketNumberFormatter;

    $initial = max(1, (int) $initial);
    $total = count($tickers);
    // Bars are relative to the largest venue on the list, not to 100%: the top exchange rarely
    // holds more than a fifth of a coin's volume, and a bar that never fills reads as missing
    // data rather than as a small share.
    $topShare = 0.0;

    foreach ($tickers as $ticker) {
        $topShare = max($topShare, (float) ($ticker->volume_share_percent ?? 0));
    }
@endphp

{{-- The phone form of the exchange markets table. Two lines per venue instead of seven
     columns behind a sideways scroll. --}}
<div class="afmc-exlist" x-data="{ all: false }">
    <ul class="afmc-exlist__rows" @if ($label) aria-label="{{ $label }}" @endif>
        @foreach ($tickers as $index => $ticker)
            @php
                $share = $ticker->volume_share_percent === null ? null : (float) $ticker->volume_share_percent;
                $fill = $share !== null && $topShare > 0 ? max(4, $share / $topShare * 100) : 0;
            @endphp
            <li
                @class(['afmc-exlist__item', 'is-muted' => $ticker->is_stale || $ticker->is_anomaly])
                @if ($index >= $initial) x-show="all" x-cloak @endif
            >
                <span class="afmc-exlist__line">
                    <span class="afmc-exlist__rank">{{ $ticker->rank }}</span>
                    @if ($ticker->trade_url)
                        <a href="{{ $ticker->trade_url }}" rel="noopener noreferrer sponsored" target="_blank" class="afmc-exlist__name">
                            {{ $ticker->exchange_name }}
                        </a>
                    @else
                        <span class="afmc-exlist__name">{{ $ticker->exchange_name }}</span>
                    @endif
                    <span class="afmc-exlist__price">{{ MarketNumberFormatter::moneyRow($ticker->price_usd !== null ? (float) $ticker->price_usd : null) }}</span>
                </span>
                <span class="afmc-exlist__line">
                    <span class="afmc-exlist__rank"></span>
                    <span class="afmc-exlist__facts">
                        <span>{{ $ticker->pair }}</span>
                        <span>{{ MarketNumberFormatter::money($ticker->volume_24h_usd !== null ? (float) $ticker->volume_24h_usd : null) }}</span>
                        <span class="afmc-tag">{{ __($ticker->trustLabel()) }}</span>
                    </span>
                    <span class="afmc-exlist__share">
                        <span class="afmc-exlist__track" aria-hidden="true">
                            <span class="afmc-exlist__fill" style="width:{{ $fill }}%"></span>
                        </span>
                        <span class="afmc-exlist__percent">{{ $share !== null ? number_format($share, 2).'%' : '—' }}</span>
                    </span>
                </span>
            </li>
        @endforeach
    </ul>

    @if ($total > $initial)
        <div class="afmc-exlist__foot">
            <button type="button" class="afmc-btn afmc-btn--secondary afmc-btn--full" @click="all = ! all">
                <span x-show="! all">{{ __('Show all :count markets', ['count' => $total]) }}</span>
                <span x-show="all" x-cloak>{{ __('Show the top :count only', ['count' => $initial]) }}</span>
                <span class="afmc-exlist__caret" ::class="all && 'is-open'">
                    <x-afmc.icon name="expand_more" size="16px" />
                </span>
            </button>
        </div>
    @endif
</div>
