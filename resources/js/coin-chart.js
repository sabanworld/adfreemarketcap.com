import Chart from 'chart.js/auto';

function cssVar(name, fallback) {
    const value = getComputedStyle(document.documentElement).getPropertyValue(name).trim();

    return value || fallback;
}

/**
 * The theme swaps the whole palette on [data-theme], so a canvas cannot inherit it the way
 * the rest of the UI does. Every colour Chart.js needs is read here, once per paint.
 */
function palette(up) {
    return {
        stroke: up ? cssVar('--chart-up', '#0E9F6E') : cssVar('--chart-down', '#D8433B'),
        tick: cssVar('--text-faint', '#726B5E'),
        grid: cssVar('--chart-grid', '#EFEAE0'),
        // The tooltip is an inverse surface, and both ends of the ink scale flip with the theme.
        tooltipSurface: cssVar('--ink-900', '#14120E'),
        tooltipTitle: cssVar('--ink-300', '#ADA697'),
        tooltipBody: cssVar('--paper-0', '#FBF9F4'),
        pointRing: cssVar('--surface-card', '#FFFFFF'),
    };
}

function paintTheme(chart, up) {
    const colors = palette(up);
    const dataset = chart.data.datasets[0];
    const { scales, plugins } = chart.options;

    dataset.borderColor = colors.stroke;
    dataset.pointHoverBackgroundColor = colors.stroke;
    dataset.pointHoverBorderColor = colors.pointRing;

    scales.x.ticks.color = colors.tick;
    scales.x.border.color = colors.grid;
    scales.y.ticks.color = colors.tick;
    scales.y.grid.color = colors.grid;

    plugins.tooltip.backgroundColor = colors.tooltipSurface;
    plugins.tooltip.titleColor = colors.tooltipTitle;
    plugins.tooltip.bodyColor = colors.tooltipBody;
}

/**
 * Mount or remount the coin detail Chart.js instance.
 * Livewire morph must not touch the canvas (wire:ignore); call this from @script
 * whenever range data changes.
 *
 * @param {{ labels?: string[], values?: number[], up?: boolean, symbol?: string, symbolAfter?: boolean }} payload
 */
export function mountCoinChart(payload = {}) {
    const canvas = document.getElementById('coin-chart');

    if (! canvas) {
        return;
    }

    const labels = Array.isArray(payload.labels) ? payload.labels : JSON.parse(canvas.dataset.labels || '[]');
    const values = Array.isArray(payload.values) ? payload.values : JSON.parse(canvas.dataset.values || '[]');
    const up = typeof payload.up === 'boolean' ? payload.up : canvas.dataset.up === '1';
    const symbol = typeof payload.symbol === 'string' ? payload.symbol : (canvas.dataset.symbol || '$');
    const symbolAfter = typeof payload.symbolAfter === 'boolean'
        ? payload.symbolAfter
        : canvas.dataset.symbolAfter === '1';

    const colors = palette(up);

    if (canvas._afmcChart) {
        canvas._afmcChart.destroy();
        canvas._afmcChart = null;
    }

    // Chart.js leaves sizing attrs that confuse a remount on the same node.
    canvas.removeAttribute('width');
    canvas.removeAttribute('height');
    canvas.style.height = '280px';
    canvas.style.width = '100%';

    canvas._afmcUp = up;
    canvas._afmcChart = new Chart(canvas, {
        type: 'line',
        data: {
            labels,
            datasets: [{
                data: values,
                borderColor: colors.stroke,
                // Reads the live border colour so a theme repaint carries the gradient with it.
                backgroundColor: (context) => areaFill(context, context.chart.data.datasets[0].borderColor),
                fill: true,
                pointRadius: 0,
                pointHoverRadius: 4,
                pointHoverBackgroundColor: colors.stroke,
                pointHoverBorderColor: colors.pointRing,
                pointHoverBorderWidth: 2,
                tension: 0.25,
                borderWidth: 1.75,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: prefersReducedMotion() ? false : { duration: 320 },
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { display: false },
                tooltip: {
                    displayColors: false,
                    backgroundColor: colors.tooltipSurface,
                    titleColor: colors.tooltipTitle,
                    bodyColor: colors.tooltipBody,
                    titleFont: { family: 'Public Sans Variable', size: 11 },
                    bodyFont: { family: 'JetBrains Mono Variable', size: 12, weight: 600 },
                    padding: { x: 8, y: 5 },
                    cornerRadius: 5,
                    // The tooltip keeps full precision; the axis is only for orientation.
                    callbacks: { label: (item) => money(item.parsed.y, symbol, symbolAfter, true) },
                },
            },
            scales: {
                x: {
                    display: true,
                    ticks: { maxTicksLimit: 5, color: colors.tick, font: { family: 'Public Sans Variable', size: 11 } },
                    // Only the horizontal rules carry the scale; vertical ones add nothing.
                    grid: { display: false },
                    border: { color: colors.grid },
                },
                y: {
                    ticks: {
                        color: colors.tick,
                        font: { family: 'JetBrains Mono Variable', size: 11 },
                        callback: (value) => money(value, symbol, symbolAfter, false),
                    },
                    grid: { color: colors.grid },
                    border: { display: false },
                },
            },
        },
    });
}

function prefersReducedMotion() {
    return typeof window.matchMedia === 'function'
        && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}

/**
 * Soft area fill under the line: the only decorative gradient the system allows, and it
 * needs the plot box, which Chart.js only knows after the first layout pass.
 */
function areaFill(context, stroke) {
    const { ctx, chartArea } = context.chart;

    if (! chartArea) {
        return 'transparent';
    }

    const gradient = ctx.createLinearGradient(0, chartArea.top, 0, chartArea.bottom);
    gradient.addColorStop(0, withAlpha(stroke, 0.22));
    gradient.addColorStop(1, withAlpha(stroke, 0));

    return gradient;
}

function withAlpha(color, alpha) {
    const hex = color.replace('#', '');

    if (! /^[0-9a-f]{6}$/i.test(hex)) {
        return color;
    }

    const [r, g, b] = [0, 2, 4].map((i) => parseInt(hex.slice(i, i + 2), 16));

    return `rgba(${r}, ${g}, ${b}, ${alpha})`;
}

/**
 * Axis labels read as round money. Chart.js already snaps the scale to a sensible step, but
 * without this an axis on a $76k asset prints "76234.68" — cent precision nobody asked for
 * and no thousands separator.
 */
function money(value, symbol, symbolAfter, precise) {
    const digits = precise
        ? (Math.abs(value) >= 1 ? 2 : 8)
        : (Math.abs(value) >= 1000 ? 0 : Math.abs(value) >= 1 ? 2 : 6);

    const formatted = value.toLocaleString('en-US', {
        minimumFractionDigits: precise && Math.abs(value) >= 1 ? 2 : 0,
        maximumFractionDigits: digits,
    });

    return symbolAfter ? formatted + symbol : symbol + formatted;
}

window.afmcMountCoinChart = mountCoinChart;

document.addEventListener('livewire:navigated', () => {
    // Full navigations re-run @script; this is a safety net if data attrs exist.
    const canvas = document.getElementById('coin-chart');

    if (canvas?.dataset?.labels) {
        mountCoinChart();
    }
});

// The theme toggle rewrites [data-theme] on the root, which repaints every CSS-driven surface
// but leaves the canvas holding the palette it was built with: light gridlines burnt onto a
// dark page. Repaint in place rather than remount, so the series does not animate back in.
new MutationObserver(() => {
    const canvas = document.getElementById('coin-chart');
    const chart = canvas?._afmcChart;

    if (! chart) {
        return;
    }

    paintTheme(chart, canvas._afmcUp);
    chart.update('none');
}).observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] });
