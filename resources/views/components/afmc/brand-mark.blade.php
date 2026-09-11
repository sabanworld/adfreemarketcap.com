@props([
    'href' => null,
    'size' => 'md',
    'variant' => 'full',
    'tone' => 'ink',
])

@php
    $heights = [
        'sm' => 14,
        'md' => 18,
        'lg' => 26,
        'xl' => 40,
    ];
    $h = is_numeric($size) ? (int) $size : ($heights[$size] ?? 18);
    $gap = max(2, (int) round($h * 0.125));
    $barWidth = max(3, (int) round($h * 0.25));
    $markGap = (int) round($h * 0.45);
    $wordSize = (int) round($h * 0.82);
    $word = $variant === 'short' ? 'afmc' : 'adfreemarketcap';
    $tag = $href ? 'a' : 'span';
    $barHeights = [0.60, 1.0, 0.38, 0.78];
@endphp

<{{ $tag }}
    @if ($href) href="{{ $href }}" wire:navigate @endif
    {{ $attributes->class('afmc-brand') }}
    data-tone="{{ $tone }}"
    data-variant="{{ $variant }}"
    style="--afmc-brand-h: {{ $h }}px; --afmc-brand-gap: {{ $gap }}px; --afmc-brand-bar: {{ $barWidth }}px; --afmc-brand-mark-gap: {{ $markGap }}px; --afmc-brand-word: {{ $wordSize }}px;"
    @if ($href) aria-label="{{ __('adfreemarketcap home') }}" @endif
>
    <span class="afmc-brand__bars" aria-hidden="true">
        @foreach ($barHeights as $index => $portion)
            <span
                class="afmc-brand__bar {{ $index === 1 ? 'afmc-brand__bar--accent' : '' }}"
                style="height: {{ (int) round($h * $portion) }}px"
            ></span>
        @endforeach
    </span>

    @if ($variant !== 'mark')
        <span data-afmc-wordmark class="afmc-brand__word">{{ $word }}</span>
    @else
        <span class="afmc-brand__sr">adfreemarketcap</span>
    @endif
</{{ $tag }}>
