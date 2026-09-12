@php
    $hint = $active->code === 'usd'
        ? __('Market data is stored in US dollars.')
        : ($ratesSyncedAt
            ? __('Converted from USD at rates synced :time.', ['time' => $ratesSyncedAt->diffForHumans()])
            : __('Converted from USD at the latest synced rate.'));
@endphp

<div class="afmc-currency">
    @if ($fiatUnits->count() + $cryptoUnits->count() > 1)
        <label class="afmc-currency__label" for="afmc-currency">{{ __('Currency') }}</label>
        <select
            id="afmc-currency"
            class="afmc-currency__select"
            wire:model.live="currency"
            title="{{ $hint }}"
            aria-label="{{ __('Display currency') }}"
        >
            @if ($fiatUnits->isNotEmpty())
                <optgroup label="{{ __('Fiat') }}">
                    @foreach ($fiatUnits as $unit)
                        <option value="{{ $unit->code }}">{{ $unit->displayCode() }} · {{ __($unit->label) }}</option>
                    @endforeach
                </optgroup>
            @endif

            @if ($cryptoUnits->isNotEmpty())
                <optgroup label="{{ __('Crypto') }}">
                    @foreach ($cryptoUnits as $unit)
                        <option value="{{ $unit->code }}">{{ $unit->displayCode() }} · {{ __($unit->label) }}</option>
                    @endforeach
                </optgroup>
            @endif
        </select>
    @else
        <span class="afmc-currency__label">{{ $active->displayCode() }}</span>
    @endif
</div>
