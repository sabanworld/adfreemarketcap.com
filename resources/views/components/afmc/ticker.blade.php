@php
    use App\Models\Coin;
    use App\Models\MarketGlobal;
    use App\Services\MarketData\MarketNumberFormatter;

    $global = MarketGlobal::latestSnapshot();

    // Every number has one owner. Markets owns total market cap and 24h volume in its status
    // strip, so there the ticker carries only what that strip does not: repeating a figure
    // 110px above itself is the drift the one-owner rule exists to stop.
    $ownedByPage = request()->routeIs('home');

    $items = array_values(array_filter([
        $ownedByPage ? null : [__('Market cap'), MarketNumberFormatter::money($global?->total_market_cap !== null ? (float) $global->total_market_cap : null)],
        $ownedByPage ? null : [__('24h volume'), MarketNumberFormatter::money($global?->total_volume_24h !== null ? (float) $global->total_volume_24h : null)],
        [__('BTC dominance'), MarketNumberFormatter::percent($global?->btc_dominance !== null ? (float) $global->btc_dominance : null)],
        [__('Assets tracked'), number_format(Coin::rankedCount())],
    ]));
@endphp

<div data-afmc-ticker class="afmc-ticker" aria-label="{{ __('Market summary') }}">
    @foreach ($items as [$label, $value])
        <span class="afmc-ticker__item">
            <span class="afmc-ticker__label">{{ $label }}</span>
            <span class="afmc-ticker__value">{{ $value }}</span>
        </span>
    @endforeach
    <span class="afmc-ticker__note">
        <x-afmc.icon name="bolt" filled size="14px" />
        {{ __('Live · from the database') }}
    </span>
</div>
