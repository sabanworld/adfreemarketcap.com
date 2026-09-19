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
            const apply = () => {
                const theme = localStorage.getItem('afmc-theme') === 'dark' ? 'dark' : 'light';
                document.documentElement.setAttribute('data-theme', theme);
            };
            apply();
            // wire:navigate copies the server HTML's data-theme="light" onto <html>.
            // Re-apply from localStorage in onSwap (same turn, after that copy, before paint)
            // so dark mode does not flash white between pages.
            document.addEventListener('livewire:navigating', (event) => {
                event.detail?.onSwap?.(apply);
            });
        })();
    </script>
    <x-afmc.consent />
    <x-afmc.analytics />
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
        localStorage.setItem('afmc-theme', dark ? 'dark' : 'light');
        document.documentElement.setAttribute('data-theme', dark ? 'dark' : 'light');
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
    @if ($conversion = session('afmc-conversion'))
        {{-- A redirect ends the Livewire component that earned the conversion,
             so it is replayed here on the page the visitor lands on. --}}
        <div
            data-afmc-conversion="{{ $conversion }}"
            x-data
            x-init="window.afmcConsent.conversion($el.dataset.afmcConversion)"
            hidden
        ></div>
    @endif
</body>
</html>
