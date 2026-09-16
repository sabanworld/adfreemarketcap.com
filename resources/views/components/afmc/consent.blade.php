@inject('consent', 'App\Services\Consent\ConsentService')

@if ($consent->required())
    {{--
        Non-essential third-party contact, opt-in only.

        Today that is Google Ads conversion measurement and/or the ChangeNOW
        exchange widget. Nothing is requested from either host and no optional
        cookie exists until the visitor chooses Accept in the cookie bar. The
        Google tag uses Consent Mode v2 starting at denied; the ChangeNOW iframe
        and connector script are built in JavaScript rather than written into
        the markup. Withdrawing stops both and deletes Google cookies we wrote
        on this domain. ChangeNOW cookies live on changenow.io, which we cannot
        clear from here. The privacy and cookie policies describe exactly this.
    --}}
    <script>
        (() => {
            const KEY = @json($consent->storageKey());
            const VERSION = @json($consent->version());
            const ADVERTISING = @json($consent->advertisingEnabled());
            const EXCHANGE = @json($consent->exchangeWidgetEnabled());
            const SRC = @json($consent->advertisingEnabled() ? $consent->scriptUrl() : '');
            const ID = @json($consent->advertisingEnabled() ? $consent->conversionId() : '');
            const CONVERSIONS = @json((object) $consent->conversions());
            const CONNECTOR = @json($consent->exchangeWidgetEnabled() ? $consent->exchangeConnectorScriptUrl() : '');

            // A stored answer only counts for the purposes it was given for, so
            // a version bump sends the visitor back to the bar. `optional` is the
            // single yes/no for every non-essential purpose in this build.
            const read = () => {
                try {
                    const stored = JSON.parse(localStorage.getItem(KEY) || 'null');

                    return stored && stored.version === VERSION ? stored.optional === true : null;
                } catch (error) {
                    return null;
                }
            };

            const save = (optional) => {
                try {
                    localStorage.setItem(KEY, JSON.stringify({
                        version: VERSION,
                        optional: optional,
                        at: new Date().toISOString(),
                    }));
                } catch (error) {
                    // Storage can be blocked. Denied stays the effective answer.
                }
            };

            const notify = (granted) => {
                window.dispatchEvent(new CustomEvent('afmc-consent-changed', {
                    detail: { granted: granted },
                }));
            };

            let googleLoaded = false;
            let connectorLoaded = false;

            if (ADVERTISING) {
                window.dataLayer = window.dataLayer || [];
                function gtag() { dataLayer.push(arguments); }
                window.gtag = gtag;

                gtag('consent', 'default', {
                    ad_storage: 'denied',
                    ad_user_data: 'denied',
                    ad_personalization: 'denied',
                    analytics_storage: 'denied',
                    wait_for_update: 500,
                });
            }

            const loadGoogle = () => {
                if (! ADVERTISING || googleLoaded || ! SRC || ! ID) {
                    return;
                }

                googleLoaded = true;
                gtag('js', new Date());
                gtag('config', ID);

                const tag = document.createElement('script');
                tag.async = true;
                tag.src = SRC;
                document.head.appendChild(tag);
            };

            // The connector is shared by every exchange mount on the page. Load
            // it once after Accept; widgets invent their own iframes.
            const loadConnector = () => {
                if (! EXCHANGE || connectorLoaded || ! CONNECTOR) {
                    return;
                }

                if (document.querySelector('script[data-afmc-changenow-connector]')) {
                    connectorLoaded = true;
                    return;
                }

                connectorLoaded = true;
                const tag = document.createElement('script');
                tag.defer = true;
                tag.src = CONNECTOR;
                tag.dataset.afmcChangenowConnector = '1';
                document.head.appendChild(tag);
            };

            // Google writes _gcl_* on our own domain, so withdrawing has to clear
            // them. Stopping the next measurement is not the same as undoing it.
            // ChangeNOW cookies sit on changenow.io and are outside our reach.
            const forgetGoogle = () => {
                if (! ADVERTISING) {
                    return;
                }

                const host = window.location.hostname;
                const scopes = ['', host, '.' + host, '.' + host.split('.').slice(-2).join('.')];

                for (const pair of document.cookie.split(';')) {
                    const name = pair.split('=')[0].trim();

                    if (! name.startsWith('_gcl') && ! name.startsWith('_gac')) {
                        continue;
                    }

                    for (const scope of scopes) {
                        document.cookie = name + '=; path=/; max-age=0; SameSite=Lax'
                            + (scope ? '; domain=' + scope : '');
                    }
                }
            };

            const unloadExchange = () => {
                document.querySelectorAll('[data-afmc-exchange-host]').forEach((host) => {
                    host.replaceChildren();
                });

                document.querySelectorAll('script[data-afmc-changenow-connector]').forEach((tag) => {
                    tag.remove();
                });

                connectorLoaded = false;
            };

            const updateGoogle = (optional) => {
                if (! ADVERTISING || typeof gtag !== 'function') {
                    return;
                }

                gtag('consent', 'update', {
                    ad_storage: optional ? 'granted' : 'denied',
                    ad_user_data: optional ? 'granted' : 'denied',
                    ad_personalization: optional ? 'granted' : 'denied',
                    analytics_storage: optional ? 'granted' : 'denied',
                });
            };

            window.afmcConsent = {
                required: true,
                advertising: ADVERTISING,
                exchange: EXCHANGE,
                answered: () => read() !== null,
                granted: () => read() === true,
                accept() {
                    save(true);
                    updateGoogle(true);
                    loadGoogle();
                    loadConnector();
                    notify(true);
                },
                reject() {
                    save(false);
                    updateGoogle(false);
                    forgetGoogle();
                    unloadExchange();
                    notify(false);
                },
                // Reported by name, so a label that is not configured yet and a
                // visitor who never accepted both end the same way: nothing sent.
                conversion(name) {
                    const sendTo = CONVERSIONS[name];

                    if (! sendTo || read() !== true) {
                        return;
                    }

                    loadGoogle();
                    gtag('event', 'conversion', { send_to: sendTo });
                },
                ensureExchangeConnector() {
                    if (read() === true) {
                        loadConnector();
                    }
                },
            };

            // Livewire actions that stay on the page report through this event.
            // A redirect cannot, so those flash the name and the layout replays
            // it on the page the visitor lands on.
            window.addEventListener('afmc-conversion', (event) => {
                window.afmcConsent.conversion(event.detail && event.detail.name);
            });

            if (read() === true) {
                loadGoogle();
                loadConnector();
            }
        })();
    </script>
@else
    <script>
        // Nothing on this build writes to the device beyond what the service
        // needs, so there is nothing to consent to and the bar stays a notice.
        window.afmcConsent = {
            required: false,
            advertising: false,
            exchange: false,
            answered() {
                try {
                    return localStorage.getItem('afmc-cookies') !== null;
                } catch (error) {
                    return true;
                }
            },
            granted: () => false,
            accept() {
                try {
                    localStorage.setItem('afmc-cookies', 'acknowledged');
                } catch (error) {
                    // Nothing to record, and nothing depends on it.
                }
            },
            reject() {},
            conversion() {},
            ensureExchangeConnector() {},
        };
    </script>
@endif
