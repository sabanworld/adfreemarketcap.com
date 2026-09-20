@props([
    'value' => null,
    'size' => 'md',
])

@php
    use App\Services\Watchlist\WatchlistMailFigures;

    // Direction is never colour alone here either. The icon font is not available in an
    // email, so the sign in front of the figure carries it instead of a caret.
    $direction = WatchlistMailFigures::direction($value);

    [$ink, $ground] = match ($direction) {
        'up' => ['#0A6B49', '#DCF5EA'],
        'down' => ['#9E2B24', '#FBE3E1'],
        'flat' => ['#5C6053', '#F5F6F2'],
        default => ['#666A5C', '#F5F6F2'],
    };

    $fontSize = $size === 'lg' ? 16 : 14;
@endphp

<span style="display:inline-block;padding:3px 7px;border-radius:4px;background:{{ $ground }};color:{{ $ink }};font-family:'JetBrains Mono',ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;font-size:{{ $fontSize }}px;line-height:1.3;font-weight:600;white-space:nowrap">{{ WatchlistMailFigures::signedPercent($value) }}</span>
