@php
    use App\Services\MarketData\MarketNumberFormatter;
@endphp

<div class="afmc-table-wrap" role="region" aria-label="{{ __('Top holders') }}" tabindex="0">
    <table class="afmc-table afmc-table--dense">
        <thead>
            <tr>
                <th><span>#</span></th>
                <th><span>{{ __('Address') }}</span></th>
                <th class="is-right"><span>{{ __('Share') }}</span></th>
                <th class="is-right"><span>{{ __('Value') }}</span></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($holders as $holder)
                <tr wire:key="dex-holder-{{ $holder->id }}">
                    <td>{{ $holder->rank }}</td>
                    <td>
                        @if (filled($holder->explorer_url))
                            <a href="{{ $holder->explorer_url }}" rel="noopener noreferrer" target="_blank">
                                {{ $holder->label ?: $truncate($holder->address, 4) }}
                            </a>
                        @else
                            <span style="font:var(--type-num);font-size:var(--text-2xs)">{{ $holder->label ?: $truncate($holder->address, 4) }}</span>
                        @endif
                    </td>
                    <td class="is-right">{{ $holder->percentage !== null ? number_format((float) $holder->percentage, 2).'%' : '—' }}</td>
                    <td class="is-right">{{ MarketNumberFormatter::money($holder->value_usd !== null ? (float) $holder->value_usd : null) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="afmc-table__empty">
                        <p style="margin:0;font:var(--type-body-sm);color:var(--text-faint)">{{ $emptyMessage }}</p>
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
