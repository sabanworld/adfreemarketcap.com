@php
    /** @var int $status */
    $status = (int) ($status ?? 500);
    $copy = (array) __('errors.' . $status);
    $heading = is_array($copy) ? (string) ($copy['title'] ?? __('Something broke')) : __('Something broke');
    $detail = is_array($copy) ? (string) ($copy['detail'] ?? '') : '';
    $icon = match ($status) {
        401 => 'login',
        402 => 'payments',
        403 => 'block',
        404 => 'search_off',
        419 => 'warning',
        429 => 'sensors',
        503 => 'bolt',
        default => 'report',
    };
    $seo = app(\App\Services\Seo\SeoService::class)->forErrorPage($status, $heading, $detail);
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <x-seo-meta :seo="$seo" />
    <link rel="icon" type="image/svg+xml" href="{{ asset('brand/logo.svg') }}">
    <link rel="apple-touch-icon" href="{{ asset('brand/logo.svg') }}">
    <script>
        (() => {
            const theme = localStorage.getItem('afmc-theme') === 'dark' ? 'dark' : 'light';
            document.documentElement.setAttribute('data-theme', theme);
        })();
    </script>
    <x-afmc.consent />
    <x-afmc.analytics />
    @vite(['resources/css/app.css'])
</head>
<body class="afmc-error-body" data-afmc-error="{{ $status }}">
    <a class="afmc-skip" href="#afmc-error-main">{{ __('Skip to main content') }}</a>

    <header class="afmc-error-shell__bar">
        <x-afmc.brand-mark :href="route('home')" size="md" />

        <button
            type="button"
            class="afmc-icon-btn"
            data-afmc-theme-toggle
            aria-label="{{ __('Switch theme') }}"
        >
            <x-afmc.icon name="dark_mode" class="afmc-error-shell__theme-dark" />
            <x-afmc.icon name="light_mode" class="afmc-error-shell__theme-light" />
        </button>
    </header>

    <main id="afmc-error-main" class="afmc-error" tabindex="-1">
        <div class="afmc-error__mark" aria-hidden="true">
            <x-afmc.icon :name="$icon" size="28px" />
        </div>

        <p class="afmc-error__code">{{ $status }}</p>
        <h1 class="afmc-error__title">{{ $heading }}</h1>
        @if (filled($detail))
            <p class="afmc-error__detail">{{ $detail }}</p>
        @endif

        <div class="afmc-error__actions">
            @if ($status === 401)
                <a class="afmc-btn afmc-btn--primary" href="{{ route('login') }}">{{ __('errors.sign_in') }}</a>
                <a class="afmc-btn afmc-btn--secondary" href="{{ route('home') }}">{{ __('errors.back_home') }}</a>
            @elseif (in_array($status, [419, 429, 500, 503], true))
                <a class="afmc-btn afmc-btn--primary" href="{{ url()->previous() !== url()->current() ? url()->previous() : route('home') }}">{{ __('errors.try_again') }}</a>
                <a class="afmc-btn afmc-btn--secondary" href="{{ route('home') }}">{{ __('errors.back_home') }}</a>
            @else
                <a class="afmc-btn afmc-btn--primary" href="{{ route('home') }}">{{ __('errors.back_home') }}</a>
                <a class="afmc-btn afmc-btn--secondary" href="{{ route('dexscan') }}">{{ __('errors.open_dexscan') }}</a>
            @endif
        </div>
    </main>

    <script>
        (() => {
            const root = document.documentElement;
            const button = document.querySelector('[data-afmc-theme-toggle]');
            if (! button) {
                return;
            }

            const apply = (dark) => {
                root.setAttribute('data-theme', dark ? 'dark' : 'light');
                try {
                    localStorage.setItem('afmc-theme', dark ? 'dark' : 'light');
                } catch (error) {}
                button.setAttribute(
                    'aria-label',
                    dark ? @json(__('Switch to light theme')) : @json(__('Switch to dark theme'))
                );
            };

            apply(root.getAttribute('data-theme') === 'dark');
            button.addEventListener('click', () => {
                apply(root.getAttribute('data-theme') !== 'dark');
            });
        })();
    </script>
</body>
</html>
