@props([
    'seo' => null,
    'title' => null,
])

@php
    /** @var \App\Services\Seo\PageSeo|null $seo */
    $pageTitle = $seo?->title ?? $title ?? config('app.name', 'adfreemarketcap.com');
    $description = $seo?->description ?? (string) config('seo.default_description');
    $canonical = $seo?->canonical ?? url()->current();
    $image = $seo?->image;
    $ogType = $seo?->ogType ?? 'website';
    $robots = $seo?->robots ?? 'index,follow';
    $twitterSite = config('seo.twitter_site');
@endphp

<title>{{ $pageTitle }}</title>
<meta name="description" content="{{ $description }}">
<meta name="robots" content="{{ $robots }}">
<link rel="canonical" href="{{ $canonical }}">

<meta property="og:site_name" content="{{ config('app.name', 'adfreemarketcap.com') }}">
<meta property="og:type" content="{{ $ogType }}">
<meta property="og:title" content="{{ $pageTitle }}">
<meta property="og:description" content="{{ $description }}">
<meta property="og:url" content="{{ $canonical }}">
@if (filled($image))
    <meta property="og:image" content="{{ $image }}">
@endif

<meta name="twitter:card" content="{{ filled($image) ? 'summary_large_image' : 'summary' }}">
<meta name="twitter:title" content="{{ $pageTitle }}">
<meta name="twitter:description" content="{{ $description }}">
@if (filled($image))
    <meta name="twitter:image" content="{{ $image }}">
@endif
@if (is_string($twitterSite) && filled($twitterSite))
    <meta name="twitter:site" content="{{ $twitterSite }}">
@endif

@foreach ($seo?->jsonLd ?? [] as $graph)
    <script type="application/ld+json">{!! json_encode($graph, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) !!}</script>
@endforeach
