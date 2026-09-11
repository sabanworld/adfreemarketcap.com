@php
    use App\Models\MarketGlobal;
    use App\Services\MarketData\MarketNumberFormatter;

    $global = MarketGlobal::latestSnapshot();
@endphp

<div class="afmc-ticker" aria-label="{{ __('Market summary') }}">
    <span class="afmc-ticker__item">
        <span class="afmc-ticker__label">{{ __('Market cap') }}</span>
        <span class="afmc-ticker__value">{{ MarketNumberFormatter::money($global?->total_market_cap !== null ? (float) $global->total_market_cap : null) }}</span>
    </span>
    <span class="afmc-ticker__item">
        <span class="afmc-ticker__label">{{ __('24h volume') }}</span>
        <span class="afmc-ticker__value">{{ MarketNumberFormatter::money($global?->total_volume_24h !== null ? (float) $global->total_volume_24h : null) }}</span>
    </span>
    <span class="afmc-ticker__item">
        <span class="afmc-ticker__label">{{ __('BTC dominance') }}</span>
        <span class="afmc-ticker__value">{{ MarketNumberFormatter::percent($global?->btc_dominance !== null ? (float) $global->btc_dominance : null) }}</span>
    </span>
    <span class="afmc-ticker__note">
        <span class="afmc-icon" data-fill="1" style="font-size: 14px; color: var(--up-500)">bolt</span>
        {{ __('Live · from the database') }}
    </span>
</div>
