@php
    $company = config('company');
@endphp

<template x-teleport="body">
    <div
        x-show="moreOpen"
        x-cloak
        @keydown.escape.window="moreOpen = false"
        x-effect="document.body.style.overflow = moreOpen ? 'hidden' : ''"
    >
        <div
            class="afmc-drawer__scrim"
            @click="moreOpen = false"
            x-show="moreOpen"
            x-transition.opacity
        ></div>

        <div
            id="afmc-nav-drawer"
            class="afmc-drawer"
            role="dialog"
            aria-modal="true"
            aria-label="{{ __('More') }}"
            x-show="moreOpen"
            x-transition:enter="afmc-drawer--enter"
            x-transition:enter-start="afmc-drawer--enter-start"
            x-transition:enter-end="afmc-drawer--enter-end"
            x-transition:leave="afmc-drawer--leave"
            x-transition:leave-start="afmc-drawer--leave-start"
            x-transition:leave-end="afmc-drawer--leave-end"
            @click.stop
        >
            <div class="afmc-drawer__head">
                <h2 class="afmc-drawer__title">{{ __('More') }}</h2>
                <button
                    type="button"
                    class="afmc-icon-btn afmc-icon-btn--lg"
                    @click="moreOpen = false"
                    aria-label="{{ __('Close menu') }}"
                >
                    <x-afmc.icon name="close" size="22px" />
                </button>
            </div>

            <div class="afmc-drawer__section">
                <span class="afmc-drawer__eyebrow">{{ __('Markets') }}</span>
                <span class="afmc-drawer__row is-disabled" title="{{ __('Coming soon') }}">
                    <x-afmc.icon name="account_balance" size="20px" color="var(--text-faint)" />
                    <span class="afmc-drawer__row-label">{{ __('Exchanges') }}</span>
                    <span class="afmc-soon">{{ __('Soon') }}</span>
                </span>
            </div>

            <div class="afmc-drawer__section">
                <span class="afmc-drawer__eyebrow">{{ __('Account') }}</span>
                @auth
                    <a href="{{ route('watchlist') }}" wire:navigate class="afmc-drawer__row" @click="moreOpen = false">
                        <x-afmc.icon name="star" size="20px" color="var(--text-muted)" />
                        <span class="afmc-drawer__row-label">{{ __('Watchlist') }}</span>
                        <x-afmc.icon name="chevron_right" size="18px" color="var(--text-faint)" />
                    </a>
                    <form method="post" action="{{ route('logout') }}" class="afmc-drawer__form">
                        @csrf
                        <button type="submit" class="afmc-drawer__row">
                            <x-afmc.icon name="login" size="20px" color="var(--text-muted)" />
                            <span class="afmc-drawer__row-label">{{ __('Sign out') }}</span>
                            <x-afmc.icon name="chevron_right" size="18px" color="var(--text-faint)" />
                        </button>
                    </form>
                @else
                    <a href="{{ route('login') }}" wire:navigate class="afmc-drawer__row" @click="moreOpen = false">
                        <x-afmc.icon name="login" size="20px" color="var(--text-muted)" />
                        <span class="afmc-drawer__row-label">{{ __('Sign in') }}</span>
                        <x-afmc.icon name="chevron_right" size="18px" color="var(--text-faint)" />
                    </a>
                @endauth
            </div>

            <div class="afmc-drawer__section">
                <span class="afmc-drawer__eyebrow">{{ __('About') }}</span>
                <a href="{{ route('legal.show', 'disclosure-of-interests') }}" wire:navigate class="afmc-drawer__row" @click="moreOpen = false">
                    <x-afmc.icon name="gavel" size="20px" color="var(--text-muted)" />
                    <span class="afmc-drawer__row-label">{{ __('Disclosure of interests') }}</span>
                    <x-afmc.icon name="chevron_right" size="18px" color="var(--text-faint)" />
                </a>
                <a href="{{ route('home') }}#pledge" class="afmc-drawer__row" @click="moreOpen = false">
                    <x-afmc.icon name="block" size="20px" color="var(--text-muted)" />
                    <span class="afmc-drawer__row-label">{{ __('Why ad-free') }}</span>
                    <x-afmc.icon name="chevron_right" size="18px" color="var(--text-faint)" />
                </a>
                <a href="{{ $company['website'] }}" rel="noopener noreferrer" target="_blank" class="afmc-drawer__row" @click="moreOpen = false">
                    <x-afmc.icon name="arrow_outward" size="20px" color="var(--text-muted)" />
                    <span class="afmc-drawer__row-label">{{ __('Creator / host') }}</span>
                    <x-afmc.icon name="chevron_right" size="18px" color="var(--text-faint)" />
                </a>
            </div>

            <div class="afmc-drawer__footer">
                <livewire:currency-selector variant="row" key="currency-drawer" />
                <label class="afmc-switch">
                    <input
                        type="checkbox"
                        role="switch"
                        :checked="dark"
                        @change="dark = !dark"
                    />
                    <span class="afmc-switch__track" aria-hidden="true"><span class="afmc-switch__thumb"></span></span>
                    <span class="afmc-switch__label">{{ __('Dark theme') }}</span>
                </label>
            </div>
        </div>
    </div>
</template>
