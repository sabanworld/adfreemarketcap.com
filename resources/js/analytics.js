import { init } from '@plausible-analytics/tracker';

// Page-view counting, loaded only on builds where config('analytics.enabled')
// is true. The tracker ships in this bundle instead of being fetched from
// plausible.io, so the measurement request is the only thing that leaves the
// browser. What it sends is set out in the privacy policy.

const settings = JSON.parse(document.getElementById('afmc-analytics')?.textContent || 'null');

// Plausible has no Do Not Track check of its own, so this is where the promise
// in the privacy policy is kept: a browser that asks not to be tracked loads
// the tracker and sends nothing at all. Global Privacy Control counts too,
// because Do Not Track alone is gone from most browsers.
const asksNotToBeTracked = () => navigator.globalPrivacyControl === true
    || [navigator.doNotTrack, window.doNotTrack, navigator.msDoNotTrack].some(
        (signal) => signal === '1' || signal === 'yes',
    );

if (settings && (settings.collectDnt || ! asksNotToBeTracked())) {
    init({
        domain: settings.domain,
        endpoint: settings.endpoint,
        outboundLinks: settings.capture.outboundLinks,
        fileDownloads: settings.capture.fileDownloads,
        formSubmissions: settings.capture.formSubmissions,
    });
}
