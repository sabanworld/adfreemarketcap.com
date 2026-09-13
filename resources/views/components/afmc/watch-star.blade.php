@props([
    'coinId',
    'watched' => false,
    'action' => 'toggleWatch',
])

@php
    $coinId = (int) $coinId;
    $target = $action.'('.$coinId.')';
@endphp

<button
    type="button"
    class="afmc-icon-btn"
    wire:click="{{ $target }}"
    wire:loading.attr="disabled"
    wire:target="{{ $target }}"
    aria-pressed="{{ $watched ? 'true' : 'false' }}"
    aria-label="{{ $watched ? __('Remove from watchlist') : __('Add to watchlist') }}"
    title="{{ auth()->check() ? ($watched ? __('Remove from watchlist') : __('Add to watchlist')) : __('Sign in to use watchlist') }}"
>
    <x-afmc.icon
        name="star"
        :filled="$watched"
        size="18px"
        {{-- amber-600, not 500: an interactive glyph needs 3:1 against paper. --}}
        :color="$watched ? 'var(--amber-600)' : 'var(--text-faint)'"
    />
</button>
