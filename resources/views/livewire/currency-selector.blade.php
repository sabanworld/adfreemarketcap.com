@php
    $hint = $active->code === 'usd'
        ? __('Market data is stored in US dollars.')
        : ($ratesSyncedAt
            ? __('Converted from USD at rates synced :time.', ['time' => $ratesSyncedAt->diffForHumans()])
            : __('Converted from USD at the latest synced rate.'));

    $groups = [];

    if ($fiatUnits->isNotEmpty()) {
        $groups[] = [
            'label' => __('Fiat'),
            'units' => $fiatUnits,
        ];
    }

    if ($cryptoUnits->isNotEmpty()) {
        $groups[] = [
            'label' => __('Crypto'),
            'units' => $cryptoUnits,
        ];
    }
@endphp

<div
    class="afmc-currency"
    x-data="{
        open: false,
        narrow: window.matchMedia('(max-width: 640px)').matches,
        init() {
            const mq = window.matchMedia('(max-width: 640px)');
            const sync = () => { this.narrow = mq.matches };
            mq.addEventListener ? mq.addEventListener('change', sync) : mq.addListener(sync);
        },
        pick(code) {
            this.open = false;
            $wire.set('currency', code);
        },
    }"
    @keydown.escape.window="if (open) open = false"
>
    @if (count($groups) > 0 && ($fiatUnits->count() + $cryptoUnits->count()) > 1)
        <button
            type="button"
            class="afmc-currency__trigger"
            @click="open = !open"
            :aria-expanded="open"
            aria-haspopup="listbox"
            aria-label="{{ __('Display currency') }}: {{ $active->displayCode() }}"
            title="{{ $hint }}"
        >
            {{ $active->displayCode() }}
            <x-afmc.icon name="expand_more" size="16px" color="var(--text-faint)" />
        </button>

        {{--
            The sheet is teleported to the body because the sticky header paints a
            backdrop-filter, which makes it the containing block for fixed children:
            left over, the sheet anchors to the header instead of the viewport.
        --}}
        <template x-teleport="body">
            <div
                x-show="open && narrow"
                x-cloak
                x-effect="document.body.style.overflow = (open && narrow) ? 'hidden' : ''"
            >
                <div class="afmc-currency__scrim" @click="open = false" x-transition.opacity></div>
                <div class="afmc-currency__sheet" role="listbox" aria-label="{{ __('Display currency') }}">
                    <div class="afmc-currency__sheet-head">
                        <span class="afmc-currency__sheet-title">{{ __('Currency') }}</span>
                        <button type="button" class="afmc-icon-btn afmc-icon-btn--lg" @click="open = false" aria-label="{{ __('Close') }}">
                            <x-afmc.icon name="close" size="22px" />
                        </button>
                    </div>
                    @foreach ($groups as $group)
                        <div class="afmc-currency__group">
                            <span class="afmc-currency__group-label">{{ $group['label'] }}</span>
                            <div class="afmc-currency__options afmc-currency__options--sheet">
                                @foreach ($group['units'] as $unit)
                                    <button
                                        type="button"
                                        class="afmc-currency__option {{ $unit->code === $active->code ? 'is-active' : '' }}"
                                        role="option"
                                        @if ($unit->code === $active->code) aria-selected="true" @endif
                                        @click="pick('{{ $unit->code }}')"
                                    >
                                        <span class="afmc-currency__option-code">{{ $unit->displayCode() }}</span>
                                        <span class="afmc-currency__option-label">{{ __($unit->label) }}</span>
                                        @if ($unit->code === $active->code)
                                            <x-afmc.icon name="check" size="18px" color="var(--amber-600)" />
                                        @endif
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </template>

        <div
            class="afmc-currency__popover"
            role="listbox"
            aria-label="{{ __('Display currency') }}"
            x-show="open && !narrow"
            x-cloak
            @click.outside="open = false"
        >
            @foreach ($groups as $group)
                <div class="afmc-currency__group">
                    <span class="afmc-currency__group-label">{{ $group['label'] }}</span>
                    <div class="afmc-currency__options">
                        @foreach ($group['units'] as $unit)
                            <button
                                type="button"
                                class="afmc-currency__option {{ $unit->code === $active->code ? 'is-active' : '' }}"
                                role="option"
                                @if ($unit->code === $active->code) aria-selected="true" @endif
                                @click="pick('{{ $unit->code }}')"
                            >
                                <span class="afmc-currency__option-code">{{ $unit->displayCode() }}</span>
                                <span class="afmc-currency__option-label">{{ __($unit->label) }}</span>
                                @if ($unit->code === $active->code)
                                    <x-afmc.icon name="check" size="16px" color="var(--amber-600)" />
                                @endif
                            </button>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <span class="afmc-currency__label">{{ $active->displayCode() }}</span>
    @endif
</div>
