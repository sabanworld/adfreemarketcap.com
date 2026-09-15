@php
    use App\Services\MarketData\MarketNumberFormatter;
@endphp

<div class="afmc-table-wrap" role="region" aria-label="{{ __('Recent trades') }}" tabindex="0">
    <table class="afmc-table afmc-table--dense">
        <thead>
            <tr>
                <th><span>{{ __('Time') }}</span></th>
                <th><span>{{ __('Side') }}</span></th>
                <th class="is-right"><span>{{ __('Price') }}</span></th>
                <th class="is-right"><span>{{ __('Value') }}</span></th>
                <th class="hide-narrow"><span>{{ __('Trader') }}</span></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($trades as $trade)
                <tr wire:key="dex-trade-{{ $trade->id }}">
                    <td>{{ $trade->traded_at?->diffForHumans() ?? '—' }}</td>
                    <td>
                        @php $kind = strtolower((string) $trade->kind); @endphp
                        <span @class([
                            'afmc-badge',
                            'afmc-badge--up' => $kind === 'buy',
                            'afmc-badge--down' => $kind === 'sell',
                        ])>{{ $trade->kind ? __(ucfirst($trade->kind)) : '—' }}</span>
                    </td>
                    <td class="is-right">{{ MarketNumberFormatter::money($trade->price_usd !== null ? (float) $trade->price_usd : null, 8) }}</td>
                    <td class="is-right">{{ MarketNumberFormatter::money($trade->volume_usd !== null ? (float) $trade->volume_usd : null) }}</td>
                    <td class="hide-narrow" style="font:var(--type-num);font-size:var(--text-2xs)">{{ $truncate($trade->trader_address, 4) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="afmc-table__empty">
                        <p style="margin:0;font:var(--type-body-sm);color:var(--text-faint)">{{ __('Recent trades will appear after the next sync.') }}</p>
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
