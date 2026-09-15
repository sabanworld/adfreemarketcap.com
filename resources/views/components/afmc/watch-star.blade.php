@props([
    'coinId',
    'watched' => false,
    'action' => 'toggleWatch',
    'showLabel' => false,
])

@php
    $coinId = (int) $coinId;
    $target = $action.'('.$coinId.')';
    $title = auth()->check()
        ? ($watched ? __('Remove from watchlist') : __('Add to watchlist'))
        : __('Sign in to use watchlist');
@endphp

<button
    type="button"
    {{ $attributes->class($showLabel ? 'afmc-panel-action' : 'afmc-icon-btn') }}
    wire:click="{{ $target }}"
    wire:loading.attr="disabled"
    wire:target="{{ $target }}"
    aria-pressed="{{ $watched ? 'true' : 'false' }}"
    aria-label="{{ $watched ? __('Remove from watchlist') : __('Add to watchlist') }}"
    title="{{ $title }}"
>
    <x-afmc.icon
        name="star"
        :filled="$watched"
        size="18px"
        {{-- amber-600, not 500: an interactive glyph needs 3:1 against paper. --}}
        :color="$watched ? 'var(--amber-600)' : 'var(--text-faint)'"
    />
    @if ($showLabel)
        {{-- The star alone is a 24px target in a table cell. In the row panel it is one of a
             row of full-width actions, so it says what it does. --}}
        <span aria-hidden="true">{{ $watched ? __('Saved') : __('Watch') }}</span>
    @endif
</button>
