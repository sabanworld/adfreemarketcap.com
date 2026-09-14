@php
    use App\Services\MarketData\MarketOverviewService;

    // Every number has one owner. On markets the status strip publishes total market cap, 24h
    // volume and the dominance split, and the "All coins" tab badge carries the tracked count,
    // so the ticker asks the service what is left. When that is nothing, the strip owns the
    // whole set and this surface is hidden rather than padded out with something to say.
    $items = app(MarketOverviewService::class)->tickerItems(statusStripOnPage: request()->routeIs('home'));
@endphp

@if ($items !== [])
    <div data-afmc-ticker class="afmc-ticker" aria-label="{{ __('Market summary') }}">
        @foreach ($items as $item)
            <span class="afmc-ticker__item">
                <span class="afmc-ticker__label">{{ $item['label'] }}</span>
                <span class="afmc-ticker__value">{{ $item['value'] }}</span>
            </span>
        @endforeach
        <span class="afmc-ticker__note">
            <x-afmc.icon name="bolt" filled size="14px" />
            {{ __('Live · from the database') }}
        </span>
    </div>
@endif
