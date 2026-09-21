@php use App\Services\Watchlist\WatchlistMailFigures; @endphp
{{ config('app.name') }}
{{ __('Watchlist alert') }}

{{ __('Hello :name, prices are in US dollars and come from the last sync, not a live feed.', ['name' => $user->name]) }}
@foreach ($alerts as $alert)

{{ $alert->coin->name }} ({{ strtoupper((string) $alert->coin->symbol) }}) {{ WatchlistMailFigures::price($alert->coin->price) }}
@foreach ($alert->lines as $line)
{{ WatchlistMailFigures::signedPercent($line->percent) }} {{ __('over :window, past the :band% mark', ['window' => $line->windowLabel, 'band' => $line->band]) }}
@endforeach
{{ route('coins.show', $alert->coin) }}
@endforeach

{{ __('Open your watchlist:') }} {{ route('watchlist') }}

{{ __('Marks start at 5% and step every 5%. One email per coin an hour, naming the highest mark it passed.') }}

{{ __('You get this because price emails are on for your watchlist. The switch on that page turns them off.') }}
{{ config('company.product_name') }}, {{ __('operated by :legal, KvK :kvk.', ['legal' => config('company.legal_name'), 'kvk' => config('company.kvk')]) }}
