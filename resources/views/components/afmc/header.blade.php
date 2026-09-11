@php
    $nav = [
        ['label' => __('Coins'), 'href' => route('home'), 'active' => request()->routeIs('home')],
        ['label' => __('DexScan'), 'href' => null, 'active' => false],
        ['label' => __('Exchanges'), 'href' => null, 'active' => false],
        ['label' => __('Watchlist'), 'href' => null, 'active' => false],
        ['label' => __('Picks'), 'href' => route('home').'#picks', 'active' => false],
    ];

    if (request()->routeIs('coins.show')) {
        $nav[0]['active'] = true;
    }
@endphp

<header data-afmc-header class="afmc-header" x-data>
    <x-afmc.brand-mark :href="route('home')" size="md" />

    <nav data-afmc-nav class="afmc-nav" aria-label="{{ __('Primary') }}">
        @foreach ($nav as $item)
            @if ($item['href'])
                <a
                    href="{{ $item['href'] }}"
                    @if (! str_contains($item['href'], '#')) wire:navigate @endif
                    class="afmc-nav__item {{ $item['active'] ? 'is-active' : '' }}"
                >{{ $item['label'] }}</a>
            @else
                <span class="afmc-nav__item is-disabled" title="{{ __('Coming soon') }}">{{ $item['label'] }}</span>
            @endif
        @endforeach
    </nav>

    <form data-afmc-search action="{{ route('home') }}" method="get" class="afmc-search" role="search">
        <span class="afmc-icon" style="font-size: 18px; color: var(--text-faint)">search</span>
        <input
            type="search"
            name="search"
            value="{{ request('search') }}"
            placeholder="{{ __('Search name or symbol') }}"
            aria-label="{{ __('Search name or symbol') }}"
        />
    </form>

    <div class="afmc-header__right">
        <button
            type="button"
            class="afmc-icon-btn"
            @click="dark = !dark"
            :aria-label="dark ? '{{ __('Switch to light theme') }}' : '{{ __('Switch to dark theme') }}'"
        >
            <span class="afmc-icon" x-text="dark ? 'light_mode' : 'dark_mode'"></span>
        </button>
    </div>
</header>
