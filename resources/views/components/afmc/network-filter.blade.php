{{-- Networks on Markets and chains on DexScan are the same control over a different catalog,
     so the copy and the Livewire action are props rather than a second component. --}}
@props([
    'network' => 'all',
    'networks' => null,
    'moreNetworks' => null,
    'action' => 'setNetwork',
    'label' => null,
    'allLabel' => null,
    'searchLabel' => null,
    'emptyLabel' => null,
    'keyPrefix' => 'network',
])

@php
    $networks = collect($networks);
    $moreNetworks = collect($moreNetworks);
    $label ??= __('Filter by network');
    $allLabel ??= __('All networks');
    $searchLabel ??= __('Search networks');
    $emptyLabel ??= __('No network matches your search.');
@endphp

<div {{ $attributes->class('afmc-network-filter') }} role="group" aria-label="{{ $label }}">
    {{-- The chip row pans horizontally on a phone, so the More menu is a sibling of it: a
         popover inside an overflow-x parent is clipped by it. --}}
    <div data-afmc-chiprow class="afmc-network-filter__chips">
        <button
            type="button"
            class="afmc-chip {{ $network === 'all' ? 'is-active' : '' }}"
            aria-pressed="{{ $network === 'all' ? 'true' : 'false' }}"
            wire:click="{{ $action }}('all')"
        >
            {{ $allLabel }}
        </button>
        @foreach ($networks as $item)
            <button
                type="button"
                class="afmc-chip {{ $network === $item['id'] ? 'is-active' : '' }}"
                aria-pressed="{{ $network === $item['id'] ? 'true' : 'false' }}"
                wire:click="{{ $action }}({{ \Illuminate\Support\Js::from($item['id']) }})"
                wire:key="{{ $keyPrefix }}-{{ $item['id'] }}"
            >
                {{ $item['label'] }}
                <span class="afmc-chip__count">{{ number_format($item['count']) }}</span>
            </button>
        @endforeach
    </div>

    @if ($moreNetworks->isNotEmpty())
        <div
            class="afmc-network-filter__more"
            x-data="{
                open: false,
                q: '',
                haystacks: {{ \Illuminate\Support\Js::from($moreNetworks->map(fn (array $n): string => strtolower($n['label'] . ' ' . $n['id']))->values()) }},
                matches(needle) { return this.haystacks.filter((h) => h.includes(needle)).length },
            }"
            @keydown.escape.window="open = false"
        >
            <button
                type="button"
                class="afmc-chip afmc-chip--more"
                :aria-expanded="open.toString()"
                aria-haspopup="listbox"
                @click="open = ! open; if (open) $nextTick(() => $refs.search.focus())"
            >
                {{ __('More') }}
                <span class="afmc-chip__count">{{ number_format($moreNetworks->count()) }}</span>
                <x-afmc.icon name="expand_more" class="afmc-chip__caret" />
            </button>
            <div
                class="afmc-network-filter__popover"
                role="listbox"
                aria-label="{{ $label }}"
                x-show="open"
                x-cloak
                @click.outside="open = false"
            >
                <label class="afmc-currency__filter">
                    <x-afmc.icon name="search" />
                    <input
                        type="search"
                        x-ref="search"
                        x-model="q"
                        placeholder="{{ $searchLabel }}"
                        aria-label="{{ $searchLabel }}"
                        autocomplete="off"
                    >
                </label>
                <div class="afmc-network-filter__list">
                    @foreach ($moreNetworks as $item)
                        <button
                            type="button"
                            role="option"
                            aria-selected="{{ $network === $item['id'] ? 'true' : 'false' }}"
                            class="afmc-network-filter__option {{ $network === $item['id'] ? 'is-active' : '' }}"
                            wire:click="{{ $action }}({{ \Illuminate\Support\Js::from($item['id']) }})"
                            @click="open = false; q = ''"
                            wire:key="{{ $keyPrefix }}-more-{{ $item['id'] }}"
                            x-show="! q || {{ \Illuminate\Support\Js::from(strtolower($item['label'] . ' ' . $item['id'])) }}.includes(q.toLowerCase())"
                        >
                            <span class="afmc-network-filter__option-name">{{ $item['label'] }}</span>
                            <span class="afmc-network-filter__option-meta">
                                <span class="afmc-chip__count">{{ number_format($item['count']) }}</span>
                                @if ($network === $item['id'])
                                    <x-afmc.icon name="check" color="var(--text-brand)" />
                                @endif
                            </span>
                        </button>
                    @endforeach
                    <p
                        class="afmc-network-filter__empty"
                        x-show="q.trim() && matches(q.trim().toLowerCase()) === 0"
                        x-cloak
                    >{{ $emptyLabel }}</p>
                </div>
            </div>
        </div>
    @endif
</div>
