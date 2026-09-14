@props([
    'value' => null,
    'label' => null,
    'size' => 150,
    'thickness' => 12,
])

@php
    // Fear and greed dial. Intensity rises toward BOTH extremes and the midpoint is the
    // faintest step, because a neutral reading is the absence of a signal rather than a small
    // one. Green and red are deliberately absent: in this product they mean price direction,
    // and sentiment is not a direction, so amber carries "hot" and warm grey carries "cold".
    $zones = [
        ['to' => 25, 'label' => __('Extreme fear'), 'color' => 'var(--ink-400)'],
        ['to' => 45, 'label' => __('Fear'), 'color' => 'var(--ink-300)'],
        ['to' => 55, 'label' => __('Neutral'), 'color' => 'var(--ink-200)'],
        ['to' => 75, 'label' => __('Greed'), 'color' => 'var(--amber-300)'],
        ['to' => 100, 'label' => __('Extreme greed'), 'color' => 'var(--amber-600)'],
    ];

    $size = (int) $size;
    $thickness = (int) $thickness;
    $radius = ($size - $thickness) / 2;
    $cx = $size / 2;
    $cy = $radius + $thickness / 2;
    $height = $cy + $thickness / 2 + 2;

    $point = function (float $fraction) use ($cx, $cy, $radius): array {
        $angle = M_PI + $fraction * M_PI;

        return [$cx + $radius * cos($angle), $cy + $radius * sin($angle)];
    };

    $arc = function (float $from, float $to) use ($point, $radius): string {
        [$x0, $y0] = $point($from);
        [$x1, $y1] = $point($to);

        return sprintf('M %.2f %.2f A %s %s 0 0 1 %.2f %.2f', $x0, $y0, $radius, $radius, $x1, $y1);
    };

    $gap = 0.01;
    $segments = [];
    $from = 0.0;
    foreach ($zones as $index => $zone) {
        $last = $index === count($zones) - 1;
        $segments[] = [
            'path' => $arc(
                $index === 0 ? $from / 100 : $from / 100 + $gap,
                $last ? $zone['to'] / 100 : $zone['to'] / 100 - $gap,
            ),
            'color' => $zone['color'],
        ];
        $from = (float) $zone['to'];
    }

    $numeric = is_numeric($value) ? max(0.0, min(100.0, (float) $value)) : null;
    $needle = null;
    $zoneLabel = null;

    if ($numeric !== null) {
        $angle = M_PI + ($numeric / 100) * M_PI;
        $needle = [$cx + $radius * 0.74 * cos($angle), $cy + $radius * 0.74 * sin($angle)];

        foreach ($zones as $zone) {
            if ($numeric <= $zone['to']) {
                $zoneLabel = $zone['label'];
                break;
            }
        }
    }

    $reading = filled($label) ? $label : $zoneLabel;
@endphp

<div {{ $attributes->class('afmc-gauge') }}>
    {{-- Pure geometry, drawn at its measured pixel size: the reading is HTML below, so
         nothing here scales type. --}}
    <svg
        width="{{ $size }}"
        height="{{ $height }}"
        viewBox="0 0 {{ $size }} {{ $height }}"
        class="afmc-gauge__svg"
        aria-hidden="true"
    >
        @foreach ($segments as $segment)
            <path d="{{ $segment['path'] }}" fill="none" stroke="{{ $segment['color'] }}" stroke-width="{{ $thickness }}" stroke-linecap="butt" />
        @endforeach
        @if ($needle !== null)
            <line x1="{{ $cx }}" y1="{{ $cy }}" x2="{{ round($needle[0], 2) }}" y2="{{ round($needle[1], 2) }}" stroke="var(--ink-900)" stroke-width="2.5" stroke-linecap="round" />
            <circle cx="{{ $cx }}" cy="{{ $cy }}" r="4.5" fill="var(--ink-900)" />
        @endif
    </svg>
    <p class="afmc-gauge__reading">
        <span class="afmc-gauge__value">{{ $numeric !== null ? number_format($numeric) : __('—') }}</span>
        @if (filled($reading))
            <span class="afmc-gauge__zone">{{ $reading }}</span>
        @endif
    </p>
</div>
