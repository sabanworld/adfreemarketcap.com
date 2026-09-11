<div
    class="afmc-cookie"
    role="dialog"
    aria-label="{{ __('Cookie consent') }}"
    x-show="!cookieConsent"
    x-cloak
    x-transition
>
    <div>
        <p class="afmc-cookie__title">{{ __('Cookies on this site') }}</p>
        <p class="afmc-cookie__body">
            {{ __('We use essential cookies to remember your theme and consent choice. Analytics stay off unless you accept. Reject all leaves the page fully usable.') }}
        </p>
    </div>
    <div class="afmc-cookie__actions">
        <button type="button" class="afmc-btn afmc-btn--ghost afmc-btn--sm" @click="cookieConsent = true; localStorage.setItem('afmc-cookies', 'essential')">{{ __('Manage') }}</button>
        <button type="button" class="afmc-btn afmc-btn--secondary afmc-btn--sm" @click="cookieConsent = true; localStorage.setItem('afmc-cookies', 'rejected')">{{ __('Reject all') }}</button>
        <button type="button" class="afmc-btn afmc-btn--primary afmc-btn--sm" @click="cookieConsent = true; localStorage.setItem('afmc-cookies', 'accepted')">{{ __('Accept all') }}</button>
    </div>
</div>
