@props([
    'at' => null,
])

@php
    use Illuminate\Support\Carbon;

    $moment = $at instanceof Carbon ? $at : (filled($at) ? Carbon::parse($at) : null);
@endphp

@if ($moment)
    <time
        datetime="{{ $moment->toIso8601String() }}"
        data-afmc-local-time
        {{ $attributes }}
    >{{ $moment->timezone('UTC')->format('Y-m-d H:i') }} UTC</time>
@endif
