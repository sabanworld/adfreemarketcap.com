@inject('consent', 'App\Services\Consent\ConsentService')

@php
    $required = $consent->required();
    $advertising = $consent->advertisingEnabled();
    $exchange = $consent->exchangeWidgetEnabled();

    if ($advertising && $exchange) {
        $body = __('We store what the site needs either way: your session if you sign in, your theme, and this choice. Separately, measuring Google adverts that pointed people here, and loading the ChangeNOW swap widget, each mean letting another company talk to your browser. That part is up to you, and you can switch it off again at any time.');
    } elseif ($advertising) {
        $body = __('We store what the site needs either way: your session if you sign in, your theme, and this choice. Separately, we advertise this site on Google, and measuring which ads worked means letting Google write a cookie to your device. That part is up to you, and you can switch it off again at any time.');
    } elseif ($exchange) {
        $body = __('We store what the site needs either way: your session if you sign in, your theme, and this choice. Separately, the ChangeNOW swap widget on coin pages and the home page loads from their servers, which is optional. That part is up to you, and you can switch it off again at any time.');
    } else {
        $body = __('We store only what the site needs: your session if you sign in, your theme, and this choice. Page views are counted without cookies and without anything written to your device, and there is no advertising, so there is nothing here to opt out of.');
    }
@endphp

<div
    class="afmc-cookie"
    role="dialog"
    aria-label="{{ $required ? __('Cookie choices') : __('Cookie notice') }}"
    tabindex="-1"
    x-data="{
        open: ! window.afmcConsent.answered(),
        choose(optional) {
            optional ? window.afmcConsent.accept() : window.afmcConsent.reject();
            this.open = false;
        },
    }"
    x-show="open"
    x-cloak
    x-transition
    @afmc-cookie-notice.window="open = true; $nextTick(() => $el.focus())"
>
    <div>
        <p class="afmc-cookie__title">{{ __('Cookies on this site') }}</p>
        <p class="afmc-cookie__body">{{ $body }}</p>
    </div>
    <div class="afmc-cookie__actions">
        <a
            class="afmc-btn afmc-btn--ghost afmc-btn--sm"
            href="{{ route('legal.show', 'cookie-policy') }}"
            wire:navigate
        >{{ __('What is stored') }}</a>
        @if ($required)
            {{--
                Refusing has to be as easy as accepting, so the two buttons carry
                the same weight, the same size, and the same styling. Do not make
                Accept the primary button here.
            --}}
            <button
                type="button"
                class="afmc-btn afmc-btn--secondary afmc-btn--sm"
                @click="choose(false)"
            >{{ __('Reject') }}</button>
            <button
                type="button"
                class="afmc-btn afmc-btn--secondary afmc-btn--sm"
                @click="choose(true)"
            >{{ __('Accept') }}</button>
        @else
            <button
                type="button"
                class="afmc-btn afmc-btn--primary afmc-btn--sm"
                @click="choose(true)"
            >{{ __('Got it') }}</button>
        @endif
    </div>
</div>
