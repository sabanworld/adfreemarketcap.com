@inject('consent', 'App\Services\Consent\ConsentService')

@if ($consent->advertisingEnabled())
    {{--
        Google Ads conversion measurement, opt-in only.

        Nothing is requested from Google and no advertising cookie exists until
        the visitor chooses Accept in the cookie bar. Consent Mode v2 starts at
        denied, the script tag is built in JavaScript rather than written into
        the markup, and withdrawing deletes what an earlier acceptance wrote.
        The privacy and cookie policies describe exactly this behaviour.
    --}}
    <script>
        (() => {
            const KEY = @json($consent->storageKey());
            const VERSION = @json($consent->version());
            const SRC = @json($consent->scriptUrl());
            const ID = @json($consent->conversionId());
            const CONVERSIONS = @json((object) $consent->conversions());

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

            // A stored answer only counts for the purposes it was given for, so
            // a version bump sends the visitor back to the bar.
            const read = () => {
                try {
                    const stored = JSON.parse(localStorage.getItem(KEY) || 'null');

                    return stored && stored.version === VERSION ? stored.advertising === true : null;
                } catch (error) {
                    return null;
                }
            };

            const save = (advertising) => {
                try {
                    localStorage.setItem(KEY, JSON.stringify({
                        version: VERSION,
                        advertising: advertising,
                        at: new Date().toISOString(),
                    }));
                } catch (error) {
                    // Storage can be blocked. Denied stays the effective answer.
                }
            };

            let loaded = false;

            const load = () => {
                if (loaded) {
                    return;
                }

                loaded = true;
                gtag('js', new Date());
                gtag('config', ID);

                const tag = document.createElement('script');
                tag.async = true;
                tag.src = SRC;
                document.head.appendChild(tag);
            };

            // Google writes _gcl_* on our own domain, so withdrawing has to clear
            // them. Stopping the next measurement is not the same as undoing it.
            const forget = () => {
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

            const update = (advertising) => gtag('consent', 'update', {
                ad_storage: advertising ? 'granted' : 'denied',
                ad_user_data: advertising ? 'granted' : 'denied',
                ad_personalization: advertising ? 'granted' : 'denied',
                analytics_storage: advertising ? 'granted' : 'denied',
            });

            window.afmcConsent = {
                required: true,
                answered: () => read() !== null,
                granted: () => read() === true,
                accept() {
                    save(true);
                    update(true);
                    load();
                },
                reject() {
                    save(false);
                    update(false);
                    forget();
                },
                // Reported by name, so a label that is not configured yet and a
                // visitor who never accepted both end the same way: nothing sent.
                conversion(name) {
                    const sendTo = CONVERSIONS[name];

                    if (! sendTo || read() !== true) {
                        return;
                    }

                    load();
                    gtag('event', 'conversion', { send_to: sendTo });
                },
            };

            // Livewire actions that stay on the page report through this event.
            // A redirect cannot, so those flash the name and the layout replays
            // it on the page the visitor lands on.
            window.addEventListener('afmc-conversion', (event) => {
                window.afmcConsent.conversion(event.detail && event.detail.name);
            });

            if (read() === true) {
                load();
            }
        })();
    </script>
@else
    <script>
        // Nothing on this build writes to the device beyond what the service
        // needs, so there is nothing to consent to and the bar stays a notice.
        window.afmcConsent = {
            required: false,
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
        };
    </script>
@endif
