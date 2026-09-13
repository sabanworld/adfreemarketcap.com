@props([
    'tier' => \App\Services\MarketData\DexQualityAssessor::TIER_ESTABLISHED,
    'label' => null,
    'why' => null,
    'showLabel' => true,
    'compact' => false,
])

@php
    $meta = \App\Services\MarketData\DexQualityAssessor::TIERS[$tier]
        ?? \App\Services\MarketData\DexQualityAssessor::TIERS[\App\Services\MarketData\DexQualityAssessor::TIER_ESTABLISHED];
    $text = $label ?? __($meta['label']);
    $dots = $meta['dots'];
@endphp

<span
    {{ $attributes->class(['afmc-risk', 'afmc-risk--' . $tier, 'afmc-risk--compact' => $compact]) }}
    title="{{ $why ?? __($meta['why']) }}"
>
    <span class="afmc-risk__bars" aria-hidden="true">
        @for ($i = 0; $i < 4; $i++)
            <span @class(['afmc-risk__bar', 'is-lit' => $i < $dots])></span>
        @endfor
    </span>
    @if ($showLabel)
        <span class="afmc-risk__label">{{ $text }}</span>
    @else
        <span class="afmc-visually-hidden">{{ $text }}</span>
    @endif
</span>
