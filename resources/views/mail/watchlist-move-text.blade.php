@php use App\Services\Watchlist\WatchlistMailFigures; @endphp
{{ config('app.name') }}
{{ __('Watchlist alert') }}

{{ __('Hello :name, a coin you saved crossed a price mark.', ['name' => $user->name]) }}
@foreach ($alerts as $alert)

{{ $alert->coin->name }} ({{ strtoupper((string) $alert->coin->symbol) }}) {{ WatchlistMailFigures::price($alert->coin->price) }}
@foreach ($alert->lines as $line)
{{ WatchlistMailFigures::signedPercent($line->percent) }} {{ __('over :window, past the :band% mark', ['window' => $line->windowLabel, 'band' => $line->band]) }}
@endforeach
{{ route('coins.show', $alert->coin) }}
@endforeach

{{ __('Open your watchlist:') }} {{ route('watchlist') }}

{{ __('Marks start at 5%, then every 5% after that (10, 15, 20, and so on), counted separately for the last hour, 24 hours, and 7 days. Each coin and window waits an hour before the next email, so a jump past several marks arrives once, naming the highest one.') }}

{{ __('You get this because price emails are on for your watchlist. The switch on that page turns them off, and nothing else about your account changes.') }}
{{ config('company.product_name') }}, {{ __('operated by :legal, KvK :kvk.', ['legal' => config('company.legal_name'), 'kvk' => config('company.kvk')]) }}
