import './bootstrap';
import { hydrateLocalTimes } from './local-time';

hydrateLocalTimes();
document.addEventListener('livewire:navigated', () => hydrateLocalTimes());

/*
 * Share action for a market row panel.
 *
 * Registered once rather than written inline, because a market page renders fifty rows and
 * fifty copies of this closure is real weight on the phone that gets the list.
 *
 * Nothing here leaves the browser on its own: the share sheet is the visitor's own OS
 * picking an app, and the clipboard write only happens because they pressed the button. No
 * request goes anywhere, so this adds nothing to the cookie table or the privacy policy.
 */
document.addEventListener('alpine:init', () => {
    window.Alpine.data('afmcShare', () => ({
        copied: false,

        /*
         * The button is hidden when neither route exists, so a control never sits there
         * unable to do the one thing its label promises. `navigator.share` is missing on
         * most desktop browsers and the async clipboard needs a secure origin.
         */
        get supported() {
            return !! (navigator.share || navigator.clipboard?.writeText);
        },

        share(url, title) {
            if (navigator.share) {
                navigator.share({ title, url }).catch((error) => {
                    // Dismissing the sheet is a decision, not a failure. Anything else means
                    // the sheet never opened, so the link goes to the clipboard instead.
                    if (error?.name !== 'AbortError') {
                        this.copy(url);
                    }
                });

                return;
            }

            this.copy(url);
        },

        copy(url) {
            if (! navigator.clipboard?.writeText) {
                return;
            }

            navigator.clipboard.writeText(url).then(() => {
                this.copied = true;
                clearTimeout(this.timer);
                this.timer = setTimeout(() => { this.copied = false; }, 2000);
            }).catch(() => {});
        },
    }));
});
