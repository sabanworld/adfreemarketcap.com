@props([
    'value' => null,
    'zones' => [],
    'readout' => true,
])

@php
    // A 0 to 100 index shown against NAMED zones. The boundaries are drawn, so a reader never
    // has to hold "below 25 means Bitcoin season" in their head while looking at the bar.
    $numeric = is_numeric($value) ? max(0.0, min(100.0, (float) $value)) : null;

    $segments = [];
    $from = 0.0;
    $activeIndex = null;

    foreach ($zones as $index => $zone) {
        $to = (float) $zone['to'];
        $segments[] = [
            'label' => $zone['label'],
            'width' => $to - $from,
        ];

        if ($activeIndex === null && $numeric !== null && $numeric <= $to) {
            $activeIndex = $index;
        }

        $from = $to;
    }

    if ($activeIndex === null && $numeric !== null && $segments !== []) {
        $activeIndex = count($segments) - 1;
    }

    $zoneLabel = $activeIndex === null ? null : $segments[$activeIndex]['label'];
@endphp

<div {{ $attributes->class('afmc-threshold') }}>
    @if ($readout)
        <p class="afmc-threshold__reading">
            <span class="afmc-threshold__value">{{ $numeric !== null ? number_format($numeric) : __('—') }}</span>
            @if (filled($zoneLabel))
                <span class="afmc-threshold__zone">{{ $zoneLabel }}</span>
            @endif
        </p>
    @endif

    <div class="afmc-threshold__track" aria-hidden="true">
        <div class="afmc-threshold__zones">
            @foreach ($segments as $index => $segment)
                <span
                    class="afmc-threshold__band {{ $index === $activeIndex ? 'is-active' : '' }}"
                    style="width:{{ $segment['width'] }}%"
                ></span>
            @endforeach
        </div>
        @if ($numeric !== null)
            <span class="afmc-threshold__marker" style="left:{{ $numeric }}%"></span>
        @endif
    </div>

    <p class="afmc-threshold__labels">
        @foreach ($segments as $index => $segment)
            <span
                class="afmc-threshold__label {{ $index === $activeIndex ? 'is-active' : '' }}"
                style="width:{{ $segment['width'] }}%"
            >{{ $segment['label'] }}</span>
        @endforeach
    </p>
</div>
