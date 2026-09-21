@php
    use App\Services\Watchlist\WatchlistMailFigures;

    // Built here rather than looped inline: a @foreach on its own line swallows the newline
    // after it, which glues the coin link onto the end of the percentages.
    $changeLine = fn (App\Models\Coin $coin): string => collect($windows)
        ->map(fn (string $label, string $key): string => $label . ' ' . WatchlistMailFigures::signedPercent($coin->{'percent_change_' . $key}))
        ->implode(' · ');
@endphp
{{ config('app.name') }}
{{ $heading }} · {{ $sentOn }}

{{ __('Hello :name, here is every coin on your watchlist, moved or not. Prices are in US dollars, as of :time.', ['name' => $user->name, 'time' => $sentAt]) }}
@foreach ($coins as $coin)

{{ $coin->name }} ({{ strtoupper((string) $coin->symbol) }}) {{ WatchlistMailFigures::price($coin->price) }}
{{ $changeLine($coin) }}
{{ route('coins.show', $coin) }}
@endforeach

{{ __('Open your watchlist:') }} {{ route('watchlist') }}

{{ $closing }}

{{ __('You get this because price emails are on for your watchlist. The switch on that page turns them off.') }}
{{ config('company.product_name') }}, {{ __('operated by :legal, KvK :kvk.', ['legal' => config('company.legal_name'), 'kvk' => config('company.kvk')]) }}
