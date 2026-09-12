@php
    $company = config('company');
    $address = $company['address'];
@endphp

<footer data-afmc-footer class="afmc-footer">
    <div class="afmc-footer__inner">
        <div data-afmc-footer-top class="afmc-footer__top">
            <div class="afmc-footer__brand">
                <x-afmc.brand-mark :href="route('home')" size="md" />
                <p class="afmc-footer__brand-note">
                    {{ __('Market data with no ads, no paid rankings and no sponsored listings. Paid for out of the creator\'s own pocket. Any strategic partnership our creator has with a company we link to is named on the link itself.') }}
                </p>
            </div>

            <div data-afmc-footer-cols class="afmc-footer__cols">
                <nav aria-label="{{ __('Markets') }}">
                    <span class="afmc-footer__col-title">{{ __('Markets') }}</span>
                    <a class="afmc-footer__link" href="{{ route('home') }}" wire:navigate>{{ __('Coins') }}</a>
                    <a class="afmc-footer__link" href="{{ route('dexscan') }}" wire:navigate>{{ __('DexScan') }}</a>
                    <span class="afmc-footer__link" style="opacity:.45">{{ __('Exchanges') }}</span>
                    <a class="afmc-footer__link" href="{{ route('watchlist') }}" wire:navigate>{{ __('Watchlist') }}</a>
                </nav>

                <nav aria-label="{{ __('About') }}">
                    <span class="afmc-footer__col-title">{{ __('About') }}</span>
                    <a class="afmc-footer__link" href="{{ route('home') }}#pledge">{{ __('Why ad-free') }}</a>
                    <a class="afmc-footer__link" href="{{ route('legal.show', 'disclosure-of-interests') }}" wire:navigate>{{ __('Picks policy') }}</a>
                    <a class="afmc-footer__link" href="{{ $company['website'] }}" rel="noopener noreferrer" target="_blank">{{ __('Creator / host') }}</a>
                    <a class="afmc-footer__link" href="{{ route('legal.show', 'imprint') }}" wire:navigate>{{ __('Imprint') }}</a>
                </nav>

                <nav aria-label="{{ __('Legal') }}">
                    <span class="afmc-footer__col-title">{{ __('Legal') }}</span>
                    <a class="afmc-footer__link" href="{{ route('legal.show', 'privacy-policy') }}" wire:navigate>{{ __('Privacy policy') }}</a>
                    <a class="afmc-footer__link" href="{{ route('legal.show', 'terms') }}" wire:navigate>{{ __('Terms & conditions') }}</a>
                    <a class="afmc-footer__link" href="{{ route('legal.show', 'risk-disclosure') }}" wire:navigate>{{ __('Risk disclosure') }}</a>
                    <a class="afmc-footer__link" href="{{ route('legal.show', 'complaints') }}" wire:navigate>{{ __('Complaints') }}</a>
                </nav>
            </div>
        </div>

        <nav data-afmc-footer-legal class="afmc-footer__legal-nav" aria-label="{{ __('Legal & compliance') }}">
            <span class="afmc-footer__col-title">{{ __('Legal & compliance') }}</span>
            <ul class="afmc-footer__legal-list">
                <li><a class="afmc-footer__link" href="{{ route('legal.show', 'cookie-policy') }}" wire:navigate>{{ __('Cookie policy') }}</a></li>
                <li><a class="afmc-footer__link" href="{{ route('legal.show', 'privacy-policy') }}" wire:navigate>{{ __('Privacy policy') }}</a></li>
                <li><a class="afmc-footer__link" href="{{ route('legal.show', 'terms') }}" wire:navigate>{{ __('Terms & conditions') }}</a></li>
                <li><a class="afmc-footer__link" href="{{ route('legal.show', 'imprint') }}" wire:navigate>{{ __('Imprint') }}</a></li>
                <li><a class="afmc-footer__link" href="{{ route('legal.show', 'risk-disclosure') }}" wire:navigate>{{ __('Risk disclosure') }}</a></li>
                <li><a class="afmc-footer__link" href="{{ route('legal.show', 'disclosure-of-interests') }}" wire:navigate>{{ __('Disclosure of interests') }}</a></li>
                <li><a class="afmc-footer__link" href="{{ route('legal.show', 'accessibility') }}" wire:navigate>{{ __('Accessibility statement') }}</a></li>
                <li><a class="afmc-footer__link" href="{{ route('legal.show', 'complaints') }}" wire:navigate>{{ __('Complaints') }}</a></li>
            </ul>
        </nav>

        <div class="afmc-footer__bottom">
            <span class="afmc-footer__entity">
                {{ __(':legal · KvK :kvk · :street, :postal :city · Not investment advice', [
                    'legal' => $company['legal_name'],
                    'kvk' => $company['kvk'],
                    'street' => $address['street'],
                    'postal' => $address['postal_code'],
                    'city' => $address['city'],
                ]) }}
            </span>
            <button
                type="button"
                class="afmc-footer__cookie-btn"
                x-data
                @click="$dispatch('afmc-cookie-notice')"
            ><x-afmc.icon name="cookie" size="15px" />{{ __('Cookie notice') }}</button>
        </div>
    </div>
</footer>
