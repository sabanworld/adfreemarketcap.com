@props([
    'groups',
    'quick',
    'active',
    'checkSize' => '18px',
])

{{-- Shared by the desktop popover and the phone sheet: a filter field, a quick-pick row of
     the common codes, and grouped rows showing code and full name. --}}
<div class="afmc-currency__head">
    <span class="afmc-currency__filter">
        <x-afmc.icon name="search" size="18px" color="var(--text-faint)" />
        <input
            type="text"
            x-ref="filter"
            x-model="query"
            placeholder="{{ __('Find a currency') }}"
            aria-label="{{ __('Find a currency') }}"
        />
        <button
            type="button"
            class="afmc-currency__filter-clear"
            x-show="filtering"
            x-cloak
            @click="query = ''"
            aria-label="{{ __('Clear') }}"
        ><x-afmc.icon name="close" size="18px" /></button>
    </span>

    @if ($quick->isNotEmpty())
        <div class="afmc-currency__quick" x-show="! filtering">
            @foreach ($quick as $unit)
                <button
                    type="button"
                    class="afmc-currency__quick-item {{ $unit->code === $active->code ? 'is-active' : '' }}"
                    @click="pick('{{ $unit->code }}')"
                >{{ $unit->displayCode() }}</button>
            @endforeach
        </div>
    @endif
</div>

<div class="afmc-currency__scroll" x-ref="list">
    <div class="afmc-currency__groups" role="listbox" aria-label="{{ __('Display currency') }}">
        @foreach ($groups as $group)
            <div class="afmc-currency__group">
                <span class="afmc-currency__group-label" x-show="! filtering">{{ $group['label'] }}</span>
                <div class="afmc-currency__options">
                    @foreach ($group['units'] as $unit)
                        <button
                            type="button"
                            data-currency
                            class="afmc-currency__option {{ $unit->code === $active->code ? 'is-active' : '' }}"
                            role="option"
                            @if ($unit->code === $active->code) aria-selected="true" @endif
                            :hidden="! matches(@js(mb_strtolower($unit->displayCode().' '.__($unit->label))))"
                            @click="pick('{{ $unit->code }}')"
                        >
                            <span class="afmc-currency__option-code">{{ $unit->displayCode() }}</span>
                            <span class="afmc-currency__option-label">{{ __($unit->label) }}</span>
                            @if ($unit->code === $active->code)
                                <x-afmc.icon name="check" :size="$checkSize" color="var(--amber-600)" />
                            @endif
                        </button>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>

    <p class="afmc-currency__empty" x-show="empty" x-cloak>
        {{ __('No currency matches your search.') }}
    </p>
</div>
