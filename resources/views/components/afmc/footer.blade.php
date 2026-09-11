<footer data-afmc-footer class="afmc-footer">
    <div class="afmc-footer__inner">
        <div class="afmc-footer__grid">
            <div>
                <x-afmc.brand-mark :href="route('home')" size="md" />
                <p class="afmc-footer__brand-note">
                    {{ __('Market data with no ads, no paid rankings and no sponsored listings. Paid for out of the creator\'s own pocket. Where our creator holds a stake in a company we link to, the link says so.') }}
                </p>
            </div>

            <nav aria-label="{{ __('Markets') }}">
                <span class="afmc-footer__col-title">{{ __('Markets') }}</span>
                <a class="afmc-footer__link" href="{{ route('home') }}" wire:navigate>{{ __('Coins') }}</a>
                <span class="afmc-footer__link" style="opacity:.45">{{ __('DexScan') }}</span>
                <span class="afmc-footer__link" style="opacity:.45">{{ __('Exchanges') }}</span>
                <span class="afmc-footer__link" style="opacity:.45">{{ __('Watchlist') }}</span>
            </nav>

            <nav aria-label="{{ __('Data') }}">
                <span class="afmc-footer__col-title">{{ __('Data') }}</span>
                <span class="afmc-footer__link" style="opacity:.45">{{ __('Methodology') }}</span>
                <span class="afmc-footer__link" style="opacity:.45">{{ __('Listing criteria') }}</span>
                <span class="afmc-footer__link" style="opacity:.45">{{ __('API') }}</span>
                <span class="afmc-footer__link" style="opacity:.45">{{ __('Status') }}</span>
            </nav>

            <nav aria-label="{{ __('About') }}">
                <span class="afmc-footer__col-title">{{ __('About') }}</span>
                <a class="afmc-footer__link" href="#pledge">{{ __('Why ad-free') }}</a>
                <a class="afmc-footer__link" href="#pledge">{{ __('Who pays for this') }}</a>
                <a class="afmc-footer__link" href="#picks">{{ __('Picks policy') }}</a>
                <span class="afmc-footer__link" style="opacity:.45">{{ __('Contact') }}</span>
            </nav>
        </div>

        <div class="afmc-footer__legal">
            <span class="afmc-footer__link" style="opacity:.45">{{ __('Cookie policy') }}</span>
            <span class="afmc-footer__link" style="opacity:.45">{{ __('Privacy policy') }}</span>
            <span class="afmc-footer__link" style="opacity:.45">{{ __('Terms & conditions') }}</span>
            <span class="afmc-footer__link" style="opacity:.45">{{ __('Imprint') }}</span>
            <span class="afmc-footer__link" style="opacity:.45">{{ __('Risk disclosure') }}</span>
            <span class="afmc-footer__link" style="opacity:.45">{{ __('Disclosure of interests') }}</span>
            <button type="button" class="afmc-footer__link" style="all:unset;cursor:pointer;color:var(--text-brand);font:var(--weight-semibold) var(--text-xs)/1.4 var(--font-sans);border-bottom:1px solid var(--amber-300)" @click="cookieConsent = false">
                {{ __('Manage cookie preferences') }}
            </button>
            <span class="afmc-footer__entity">{{ __('Operated for adfreemarketcap · Not investment advice') }}</span>
        </div>
    </div>
</footer>
