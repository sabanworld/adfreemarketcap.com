@php
    $tabs = [
        [
            'label' => __('Coins'),
            'href' => route('home'),
            'icon' => 'data_table',
            'active' => request()->routeIs('home') || request()->routeIs('coins.show'),
        ],
        [
            'label' => __('DexScan'),
            'href' => route('dexscan'),
            'icon' => 'swap_horiz',
            'active' => request()->routeIs('dexscan'),
        ],
        [
            'label' => __('Watchlist'),
            'href' => route('watchlist'),
            'icon' => 'star',
            'active' => request()->routeIs('watchlist'),
        ],
    ];
@endphp

<nav data-afmc-tabbar class="afmc-tabbar" aria-label="{{ __('Primary') }}">
    {{-- One column per tab, counting More: a hardcoded count leaves a dead column behind. --}}
    <ul class="afmc-tabbar__list" style="grid-template-columns:repeat({{ count($tabs) + 1 }}, 1fr)">
        @foreach ($tabs as $tab)
            <li class="afmc-tabbar__item">
                <a
                    href="{{ $tab['href'] }}"
                    @if (! str_contains($tab['href'], '#')) wire:navigate @endif
                    class="afmc-tabbar__btn {{ $tab['active'] ? 'is-active' : '' }}"
                    @if ($tab['active']) aria-current="page" @endif
                >
                    <x-afmc.icon :name="$tab['icon']" size="22px" :filled="$tab['active']" />
                    <span class="afmc-tabbar__label">{{ $tab['label'] }}</span>
                </a>
            </li>
        @endforeach
        <li class="afmc-tabbar__item">
            <button
                type="button"
                class="afmc-tabbar__btn"
                @click="moreOpen = true"
                :aria-expanded="moreOpen"
                aria-controls="afmc-nav-drawer"
            >
                <x-afmc.icon name="menu" size="22px" />
                <span class="afmc-tabbar__label">{{ __('More') }}</span>
            </button>
        </li>
    </ul>
</nav>
