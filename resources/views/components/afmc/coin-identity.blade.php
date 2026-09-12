@props([
    'name',
    'symbol',
    'rank' => null,
    'image' => null,
    'size' => 'md',
    'href' => null,
])

@php
    $tag = $href ? 'a' : 'span';
    $initials = strtoupper(mb_substr((string) $symbol, 0, 2));
@endphp

<{{ $tag }}
    @if ($href) href="{{ $href }}" wire:navigate @endif
    {{ $attributes->class('afmc-identity') }}
>
    @if (filled($image))
        <img src="{{ $image }}" alt="{{ $name }}" class="afmc-identity__logo {{ $size === 'lg' ? 'afmc-identity__logo--lg' : '' }}" loading="lazy" />
    @else
        <span class="afmc-identity__logo afmc-identity__fallback {{ $size === 'lg' ? 'afmc-identity__logo--lg' : '' }}">{{ $initials }}</span>
    @endif
    <span class="afmc-identity__body">
        <span class="afmc-identity__name {{ $size === 'lg' ? 'afmc-identity__name--lg' : '' }}">{{ $name }}</span>
        <span class="afmc-identity__meta">
            <span class="afmc-identity__symbol">{{ $symbol }}</span>
            @if ($rank !== null)
                <span class="afmc-identity__rank">#{{ $rank }}</span>
            @endif
        </span>
    </span>
</{{ $tag }}>
