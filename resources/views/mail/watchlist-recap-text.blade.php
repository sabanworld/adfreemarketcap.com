@php use App\Services\Watchlist\WatchlistMailFigures; @endphp
{{ config('app.name') }}
{{ __('Daily recap') }} · {{ $sentOn }}

{{ __('Hello :name, here is every coin on your watchlist, whether it moved or not. Prices are in US dollars, as of :time.', ['name' => $user->name, 'time' => $sentAt]) }}
@foreach ($coins as $coin)

{{ $coin->name }} ({{ strtoupper((string) $coin->symbol) }}) {{ WatchlistMailFigures::price($coin->price) }}
{{ __('1 hour') }} {{ WatchlistMailFigures::signedPercent($coin->percent_change_1h) }} · {{ __('24 hours') }} {{ WatchlistMailFigures::signedPercent($coin->percent_change_24h) }} · {{ __('7 days') }} {{ WatchlistMailFigures::signedPercent($coin->percent_change_7d) }}
{{ route('coins.show', $coin) }}
@endforeach

{{ __('Open your watchlist:') }} {{ route('watchlist') }}

{{ __('Figures come from the same sync that feeds the site, so they are as fresh as the last run rather than live. One recap goes out each day, whatever the market did.') }}

{{ __('You get this because price emails are on for your watchlist. The switch on that page turns them off, and nothing else about your account changes.') }}
{{ config('company.product_name') }}, {{ __('operated by :legal, KvK :kvk.', ['legal' => config('company.legal_name'), 'kvk' => config('company.kvk')]) }}
