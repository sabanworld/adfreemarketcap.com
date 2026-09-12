<div
    class="afmc-cookie"
    role="dialog"
    aria-label="{{ __('Cookie notice') }}"
    tabindex="-1"
    x-data="{ open: localStorage.getItem('afmc-cookies') === null }"
    x-show="open"
    x-cloak
    x-transition
    @afmc-cookie-notice.window="open = true; localStorage.removeItem('afmc-cookies'); $nextTick(() => $el.focus())"
>
    <div>
        <p class="afmc-cookie__title">{{ __('Cookies on this site') }}</p>
        <p class="afmc-cookie__body">
            {{ __('We store only what the site needs: your session if you sign in, your theme, and this choice. Page views are counted without cookies and without anything written to your device, and there is no advertising, so there is nothing here to opt out of.') }}
        </p>
    </div>
    <div class="afmc-cookie__actions">
        <a
            class="afmc-btn afmc-btn--ghost afmc-btn--sm"
            href="{{ route('legal.show', 'cookie-policy') }}"
            wire:navigate
        >{{ __('What is stored') }}</a>
        <button
            type="button"
            class="afmc-btn afmc-btn--primary afmc-btn--sm"
            @click="open = false; localStorage.setItem('afmc-cookies', 'acknowledged')"
        >{{ __('Got it') }}</button>
    </div>
</div>
