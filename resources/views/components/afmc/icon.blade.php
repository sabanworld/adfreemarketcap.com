@props([
    'name',
    'filled' => false,
    'size' => null,
    'color' => null,
])

<span
    {{ $attributes->class('afmc-icon')->merge(['aria-hidden' => 'true']) }}
    @if ($filled) data-fill="1" @endif
    @style([
        'font-size: '.$size => filled($size),
        'color: '.$color => filled($color),
    ])
>{{ \App\Support\Icons::character($name) }}</span>
