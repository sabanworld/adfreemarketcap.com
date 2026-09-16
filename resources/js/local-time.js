/**
 * Visitor-local date/time formatting via the browser's own timezone.
 * No geolocation, cookies, or third-party timezone APIs.
 */

const TIME_RANGES = new Set(['1h', '12h', '1d']);
const DAY_RANGES = new Set(['7d', '1m', '3m']);

/**
 * Axis / tooltip label style for a chart range band.
 *
 * @param {string} range
 * @returns {'time' | 'day' | 'month'}
 */
export function chartLabelStyle(range) {
    if (TIME_RANGES.has(range)) {
        return 'time';
    }

    if (DAY_RANGES.has(range)) {
        return 'day';
    }

    return 'month';
}

/**
 * Format a millisecond epoch for a chart axis tick in the visitor's timezone.
 *
 * @param {number} ms
 * @param {string} range
 * @returns {string}
 */
export function formatChartLabel(ms, range) {
    const date = new Date(ms);

    if (Number.isNaN(date.getTime())) {
        return '';
    }

    const style = chartLabelStyle(range);

    if (style === 'time') {
        return new Intl.DateTimeFormat(undefined, {
            hour: '2-digit',
            minute: '2-digit',
        }).format(date);
    }

    if (style === 'day') {
        return new Intl.DateTimeFormat(undefined, {
            month: 'short',
            day: 'numeric',
        }).format(date);
    }

    return new Intl.DateTimeFormat(undefined, {
        month: 'short',
        year: 'numeric',
    }).format(date);
}

/**
 * Absolute local datetime for UI clocks (medium date + short time).
 *
 * @param {string | number | Date} isoOrMs
 * @returns {string}
 */
export function formatLocalDateTime(isoOrMs) {
    const date = isoOrMs instanceof Date ? isoOrMs : new Date(isoOrMs);

    if (Number.isNaN(date.getTime())) {
        return '';
    }

    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(date);
}

/**
 * Replace UTC fallback text inside [data-afmc-local-time] with the visitor's local clock.
 *
 * @param {ParentNode} [root=document]
 */
export function hydrateLocalTimes(root = document) {
    root.querySelectorAll('time[data-afmc-local-time][datetime]').forEach((el) => {
        const iso = el.getAttribute('datetime');

        if (! iso) {
            return;
        }

        const formatted = formatLocalDateTime(iso);

        if (formatted) {
            el.textContent = formatted;
        }
    });
}

window.afmcLocalTime = {
    chartLabelStyle,
    formatChartLabel,
    formatLocalDateTime,
    hydrateLocalTimes,
};
