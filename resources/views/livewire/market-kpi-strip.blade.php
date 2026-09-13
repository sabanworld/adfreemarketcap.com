@php
    use App\Services\MarketData\MarketNumberFormatter;

    $btcDominance = $global?->btc_dominance !== null ? (float) $global->btc_dominance : null;
@endphp

<div class="afmc-kpi-row" wire:poll.visible.60s>
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
