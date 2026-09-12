@props([
    'value' => null,
    'chip' => false,
    'size' => 'md',
])

@php
    $numeric = is_numeric($value) ? (float) $value : null;
    $up = $numeric !== null && $numeric >= 0;
    $classes = collect([
        'afmc-change',
        $numeric === null ? null : ($up ? 'afmc-change--up' : 'afmc-change--down'),
        $chip ? 'afmc-change--chip' : null,
        $size === 'sm' ? 'afmc-change--sm' : null,
        $size === 'lg' ? 'afmc-change--lg' : null,
    ])->filter()->implode(' ');
@endphp

@if ($numeric === null)
    <span class="afmc-change" style="color: var(--text-faint)">—</span>
@else
    <span {{ $attributes->class($classes) }}>
        <x-afmc.icon :name="$up ? 'arrow_drop_up' : 'arrow_drop_down'" filled size="1.1em" />
        {{ number_format(abs($numeric), 2) }}%
    </span>
@endif
