<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <x-seo-meta :seo="$seo ?? null" :title="$title ?? null" />
    <link rel="icon" type="image/svg+xml" href="{{ asset('brand/logo.svg') }}">
    <link rel="apple-touch-icon" href="{{ asset('brand/logo.svg') }}">
    <script>
        (() => {
            const theme = localStorage.getItem('afmc-theme') === 'dark' ? 'dark' : 'light';
            document.documentElement.setAttribute('data-theme', theme);
        })();
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    <style>[x-cloak]{display:none!important}</style>
</head>
<body
    style="min-height:100vh;background:var(--surface-page);color:var(--text-body);margin:0"
    x-data="{
        dark: localStorage.getItem('afmc-theme') === 'dark',
        moreOpen: false,
        searchOpen: false,
    }"
    x-effect="
        document.documentElement.setAttribute('data-theme', dark ? 'dark' : 'light');
        localStorage.setItem('afmc-theme', dark ? 'dark' : 'light');
    "
>
    <a class="afmc-skip" href="#afmc-main">{{ __('Skip to main content') }}</a>

    <x-afmc.header />
    <x-afmc.ticker />

    <div id="afmc-main" tabindex="-1" style="outline:none">
        {{ $slot }}
    </div>

    <x-afmc.footer />
    <x-afmc.bottom-tab-bar />
    <x-afmc.nav-drawer />
    <x-afmc.cookie-bar />

    @livewireScripts
    <x-afmc.analytics />
</body>
</html>
