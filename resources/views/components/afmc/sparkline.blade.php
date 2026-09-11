@props([
    'data' => [],
    'width' => 120,
    'height' => 32,
    'up' => true,
])

@php
    $spark = array_values(array_filter(is_array($data) ? $data : [], fn ($v) => is_numeric($v)));
    $points = [];
    if (count($spark) >= 2) {
        $min = min($spark);
        $max = max($spark);
        $range = max(0.0000001, $max - $min);
        foreach ($spark as $i => $value) {
            $x = ($i / (count($spark) - 1)) * $width;
            $y = $height - (((float) $value - $min) / $range) * ($height - 2) - 1;
            $points[] = round($x, 2).','.round($y, 2);
        }
    }
    $stroke = $up ? 'var(--chart-up)' : 'var(--chart-down)';
@endphp

@if (count($points))
    <svg viewBox="0 0 {{ $width }} {{ $height }}" width="{{ $width }}" height="{{ $height }}" aria-hidden="true" {{ $attributes }}>
        <polyline fill="none" stroke="{{ $stroke }}" stroke-width="1.5" points="{{ implode(' ', $points) }}" />
    </svg>
@else
    <span style="color: var(--text-faint); font: var(--type-body-sm)">—</span>
@endif
