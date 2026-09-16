import { init } from '@plausible-analytics/tracker';

// Page-view counting, loaded only on builds where config('analytics.enabled')
// is true. The tracker ships in this bundle instead of being fetched from
// plausible.io, so the measurement request is the only thing that leaves the
// browser. What it sends is set out in the privacy policy.

const settings = JSON.parse(document.getElementById('afmc-analytics')?.textContent || 'null');

// Do Not Track and Global Privacy Control are ignored by default
// (collectDnt: true). The privacy policy names the remaining ways out. Set
// collectDnt false only if you want those browser signals to skip the tracker.
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
