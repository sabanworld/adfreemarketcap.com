@props([
    'data' => [],
    'width' => 132,
    'height' => 36,
    'up' => null,
    'fill' => true,
    'maxPoints' => null,
])

@php
    $spark = array_values(array_filter(is_array($data) ? $data : [], fn ($v) => is_numeric($v)));
    $width = (int) $width;
    $height = (int) $height;
    // One point per two pixels, because a 52px line cannot draw 168 hourly prices and the
    // coordinate list is the heaviest thing in a market row. Both ends survive the resample,
    // so the colour and the slope still come from the same first and last price the
    // percentage beside it was read from.
    $maxPoints = max(2, (int) ($maxPoints ?? ceil($width / 2)));

    if (count($spark) > $maxPoints) {
        $lastIndex = count($spark) - 1;
        $resampled = [];

        for ($step = 0; $step < $maxPoints; $step++) {
            $resampled[] = $spark[(int) round($step * $lastIndex / ($maxPoints - 1))];
        }

        $spark = $resampled;
    }

    $points = [];
    $flat = false;

    if (count($spark) >= 2) {
        $first = (float) $spark[0];
        $last = (float) $spark[count($spark) - 1];

        // Flat means flat. A stablecoin's line must not read as a gain, which is the same rule
        // the percentage columns follow.
        $flat = $up === null && $first !== 0.0 && abs($last / $first - 1) < 0.0005;

        $min = min($spark);
        $max = max($spark);
        $range = $max - $min;

        foreach ($spark as $i => $value) {
            $x = ($i / (count($spark) - 1)) * $width;
            // A flat series (an asset against itself) draws through the middle.
            $y = $range > 0
                ? $height - 2 - (((float) $value - $min) / $range) * ($height - 4)
                : $height / 2;
            $points[] = round($x, 1) . ' ' . round($y, 1);
        }
    }

    $rising = $up === null
        ? (count($spark) >= 2 && (float) $spark[count($spark) - 1] >= (float) $spark[0])
        : (bool) $up;

    $stroke = $flat ? 'var(--chart-axis)' : ($rising ? 'var(--chart-up)' : 'var(--chart-down)');
    $line = $points === [] ? '' : 'M ' . implode(' L ', $points);
    $gradientId = 'spark-' . substr(md5($line . $stroke), 0, 8);
@endphp

@if ($points !== [])
    <svg
        width="{{ $width }}"
        height="{{ $height }}"
        viewBox="0 0 {{ $width }} {{ $height }}"
        preserveAspectRatio="none"
        aria-hidden="true"
        {{ $attributes->class('afmc-sparkline') }}
    >
        @if ($fill)
            <defs>
                <linearGradient id="{{ $gradientId }}" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" stop-color="{{ $stroke }}" stop-opacity=".18" />
                    <stop offset="100%" stop-color="{{ $stroke }}" stop-opacity="0" />
                </linearGradient>
            </defs>
            <path d="{{ $line }} L {{ $width }} {{ $height }} L 0 {{ $height }} Z" fill="url(#{{ $gradientId }})" stroke="none" />
        @endif
        {{-- The viewBox is stretched to the cell, so the stroke has to opt out of scaling. --}}
        <path
            d="{{ $line }}"
            fill="none"
            stroke="{{ $stroke }}"
            stroke-width="1.5"
            stroke-linecap="round"
            stroke-linejoin="round"
            vector-effect="non-scaling-stroke"
        />
    </svg>
@else
    <span style="color: var(--text-faint); font: var(--type-body-sm)">{{ __('—') }}</span>
@endif
