@props([
    // Ticker ChangeNOW should offer as the source asset. Null uses the config default.
    'from' => null,
    // Ticker ChangeNOW should offer as the destination. Null picks eth, or btc when from is eth.
    'to' => null,
    'amount' => null,
])

@inject('consent', 'App\Services\Consent\ConsentService')

@if ($consent->exchangeWidgetEnabled())
    @php
        $defaults = $consent->exchangeWidgetDefaults();
        $fromTicker = filled($from) ? mb_strtolower((string) $from) : (string) $defaults['from'];
        $toTicker = filled($to)
            ? mb_strtolower((string) $to)
            : ($fromTicker === 'eth' ? 'btc' : (string) $defaults['to']);
        $amountValue = filled($amount) ? (string) $amount : (string) $defaults['amount'];
        $lightBackground = (string) ($defaults['backgroundColor'] ?? 'FFFFFF');
        $darkBackground = (string) config('exchange-widget.defaults.background_color_dark', '000000');
        $person = config('company.person');
    @endphp

    <section
        {{ $attributes->class('afmc-exchange') }}
        aria-label="{{ __('Swap with ChangeNOW') }}"
        x-data="{
            granted: window.afmcConsent && window.afmcConsent.granted(),
            theme: document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light',
            baseUrl: @js($consent->exchangeWidgetBaseUrl()),
            params: @js(array_merge($defaults, [
                'from' => $fromTicker,
                'to' => $toTicker,
                'amount' => $amountValue,
            ])),
            lightBackground: @js($lightBackground),
            darkBackground: @js($darkBackground),
            themeObserver: null,
            buildSrc() {
                const params = { ...this.params };
                const dark = this.theme === 'dark';
                params.darkMode = dark;
                params.backgroundColor = dark ? this.darkBackground : this.lightBackground;

                const query = Object.entries(params).map(([key, value]) => {
                    if (value === '' || value === null || value === undefined) {
                        return encodeURIComponent(key);
                    }

                    return encodeURIComponent(key) + '=' + encodeURIComponent(String(value));
                }).join('&');

                return this.baseUrl + '?' + query;
            },
            syncTheme() {
                const next = document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';

                if (next === this.theme) {
                    return;
                }

                this.theme = next;

                if (this.granted) {
                    this.mount();
                }
            },
            mount() {
                if (! this.$refs.host || ! this.granted) {
                    return;
                }

                window.afmcConsent.ensureExchangeConnector();
                this.$refs.host.replaceChildren();

                const frame = document.createElement('iframe');
                frame.className = 'afmc-exchange__iframe';
                frame.title = @js(__('ChangeNOW exchange widget'));
                frame.src = this.buildSrc();
                frame.setAttribute('loading', 'lazy');
                frame.setAttribute('referrerpolicy', 'no-referrer-when-downgrade');
                // Blank canvas colour before/around their rounded panel.
                frame.style.backgroundColor = '#' + (this.theme === 'dark' ? this.darkBackground : this.lightBackground);
                frame.style.colorScheme = this.theme === 'dark' ? 'dark' : 'light';
                this.$refs.host.appendChild(frame);
            },
            unmount() {
                if (this.$refs.host) {
                    this.$refs.host.replaceChildren();
                }
            },
            openPreferences() {
                window.dispatchEvent(new CustomEvent('afmc-cookie-notice'));
            },
            destroy() {
                if (this.themeObserver) {
                    this.themeObserver.disconnect();
                    this.themeObserver = null;
                }
            },
        }"
        x-init="
            themeObserver = new MutationObserver(() => syncTheme());
            themeObserver.observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] });
            if (granted) { $nextTick(() => mount()); }
            window.addEventListener('afmc-consent-changed', (event) => {
                granted = !!(event.detail && event.detail.granted);
                granted ? $nextTick(() => mount()) : unmount();
            });
        "
    >
        <div class="afmc-exchange__head">
            <span class="afmc-exchange__eyebrow">{{ __('Swap') }}</span>
            <span class="afmc-exchange__badge">{{ __(':person earns a commission', ['person' => $person]) }}</span>
        </div>

        <div class="afmc-exchange__body">
            <h2 class="afmc-exchange__title">{{ __('Exchange with ChangeNOW') }}</h2>
            <p class="afmc-exchange__lead">
                {{ __('Non-custodial swaps.') }}
            </p>
        </div>

        {{--
            Placeholder stays visible without x-cloak. Consent lives in local
            storage, so the server cannot know the answer; defaulting to the
            locked state avoids a flash of bare headings before Alpine runs.
        --}}
        <div
            class="afmc-exchange__placeholder"
            x-show="! granted"
        >
            <div class="afmc-exchange__placeholder-icon" aria-hidden="true">
                <x-afmc.icon name="cookie" size="28px" />
            </div>
            <div class="afmc-exchange__placeholder-copy">
                <p class="afmc-exchange__placeholder-title">{{ __('Accept cookies to use this swap') }}</p>
                <p class="afmc-exchange__placeholder-body">
                    {{ __('The widget loads from ChangeNOW. Until you accept optional cookies, your browser never contacts them from this page.') }}
                </p>
            </div>
            <button
                type="button"
                class="afmc-btn afmc-btn--primary afmc-btn--sm afmc-exchange__cta"
                @click="openPreferences()"
            >{{ __('Cookie preferences') }}</button>
        </div>

        <div
            class="afmc-exchange__frame"
            data-afmc-exchange-host
            x-ref="host"
            x-show="granted"
            x-cloak
            :data-theme="theme"
        ></div>
    </section>
@endif
