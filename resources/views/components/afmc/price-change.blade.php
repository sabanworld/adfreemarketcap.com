@props([
    'value' => null,
    'chip' => false,
    'size' => 'md',
    'showIcon' => true,
    'digits' => 2,
])

@php
    $numeric = is_numeric($value) ? (float) $value : null;
    $digits = max(0, (int) $digits);

    // Zero is not a direction. A change that rounds to 0.00% gets no caret and neutral ink,
    // because rendering it green would read as a gain, and 0.00% is the normal state for a
    // stablecoin.
    $flat = $numeric !== null && abs($numeric) < (10 ** -$digits) / 2;
    $up = $numeric !== null && $numeric > 0;

    $classes = collect([
        'afmc-change',
        $numeric === null ? 'afmc-change--empty' : null,
        $numeric === null ? null : ($flat ? 'afmc-change--flat' : ($up ? 'afmc-change--up' : 'afmc-change--down')),
        $chip ? 'afmc-change--chip' : null,
        $size === 'sm' ? 'afmc-change--sm' : null,
        $size === 'lg' ? 'afmc-change--lg' : null,
    ])->filter()->implode(' ');
@endphp

@if ($numeric === null)
    <span {{ $attributes->class($classes) }}>—</span>
@else
    <span {{ $attributes->class($classes) }}>
        @if ($showIcon && ! $flat)
            <x-afmc.icon :name="$up ? 'arrow_drop_up' : 'arrow_drop_down'" filled size="1.1em" />
        @endif
        {{ number_format(abs($numeric), $digits) }}%
    </span>
@endif
