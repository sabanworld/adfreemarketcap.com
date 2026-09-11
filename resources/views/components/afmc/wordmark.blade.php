@props(['href' => null, 'tag' => null])

@php
    $tag = $tag ?? ($href ? 'a' : 'span');
@endphp

<{{ $tag }} @if ($href) href="{{ $href }}" @endif {{ $attributes->class('afmc-wordmark') }}>
    adfreemarketcap<span class="afmc-wordmark__dot">.</span>
</{{ $tag }}>
