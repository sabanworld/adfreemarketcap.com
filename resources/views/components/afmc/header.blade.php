@php
    $nav = [
        ['label' => __('Coins'), 'href' => route('home'), 'active' => request()->routeIs('home')],
        ['label' => __('DexScan'), 'href' => route('dexscan'), 'active' => request()->routeIs('dexscan')],
        ['label' => __('Exchanges'), 'href' => null, 'active' => false],
        ['label' => __('Watchlist'), 'href' => route('watchlist'), 'active' => request()->routeIs('watchlist')],
    ];

    if (request()->routeIs('coins.show')) {
        $nav[0]['active'] = true;
    }
@endphp

<header data-afmc-header class="afmc-header">
    <x-afmc.brand-mark :href="route('home')" size="md" />

    <nav data-afmc-nav class="afmc-nav" aria-label="{{ __('Primary') }}">
        @foreach ($nav as $item)
            @if ($item['href'])
                <a
                    href="{{ $item['href'] }}"
                    @if (! str_contains($item['href'], '#')) wire:navigate @endif
                    class="afmc-nav__item {{ $item['active'] ? 'is-active' : '' }}"
                    @if ($item['active']) aria-current="page" @endif
                >{{ $item['label'] }}</a>
            @else
                <span class="afmc-nav__item is-disabled" title="{{ __('Coming soon') }}">{{ $item['label'] }}</span>
            @endif
        @endforeach
    </nav>

    <form data-afmc-search action="{{ route('home') }}" method="get" class="afmc-search" role="search">
        <button type="submit" class="afmc-search__submit" aria-label="{{ __('Search') }}">
            <x-afmc.icon name="search" size="18px" color="var(--text-faint)" />
        </button>
        <input
            type="search"
            name="search"
            value="{{ request('search') }}"
            placeholder="{{ __('Search name or symbol') }}"
            aria-label="{{ __('Search name or symbol') }}"
        />
    </form>

    <button
        type="button"
        data-afmc-searchbtn
        class="afmc-icon-btn afmc-icon-btn--lg afmc-header__search-btn"
        @click="searchOpen = !searchOpen"
        :aria-expanded="searchOpen"
        aria-label="{{ __('Search coins') }}"
    >
        <x-afmc.icon name="close" size="22px" x-show="searchOpen" x-cloak />
        <x-afmc.icon name="search" size="22px" x-show="!searchOpen" x-cloak />
    </button>

    <div data-afmc-actions class="afmc-header__right">
        <livewire:currency-selector />

        <button
            type="button"
            class="afmc-icon-btn"
            @click="dark = !dark"
            :aria-label="dark ? '{{ __('Switch to light theme') }}' : '{{ __('Switch to dark theme') }}'"
        >
            <x-afmc.icon name="light_mode" x-show="dark" x-cloak />
            <x-afmc.icon name="dark_mode" x-show="!dark" x-cloak />
        </button>

        @auth
            <a href="{{ route('watchlist') }}" wire:navigate class="afmc-btn afmc-btn--secondary afmc-btn--sm" data-afmc-signin>{{ __('Watchlist') }}</a>
            <form method="post" action="{{ route('logout') }}" data-afmc-signin>
                @csrf
                <button type="submit" class="afmc-btn afmc-btn--ghost afmc-btn--sm">{{ __('Sign out') }}</button>
            </form>
        @else
            <a href="{{ route('login') }}" wire:navigate class="afmc-btn afmc-btn--secondary afmc-btn--sm" data-afmc-signin>{{ __('Sign in') }}</a>
        @endauth
    </div>

    <template x-if="searchOpen">
        <div data-afmc-searchrow class="afmc-header__search-row">
            <form action="{{ route('home') }}" method="get" class="afmc-search" role="search">
                <button type="submit" class="afmc-search__submit" aria-label="{{ __('Search') }}">
                    <x-afmc.icon name="search" size="18px" color="var(--text-faint)" />
                </button>
                <input
                    type="search"
                    name="search"
                    value="{{ request('search') }}"
                    placeholder="{{ __('Search name or symbol') }}"
                    aria-label="{{ __('Search name or symbol') }}"
                    x-init="$el.focus()"
                    @keydown.escape.window="if (searchOpen) searchOpen = false"
                />
            </form>
        </div>
    </template>
</header>
