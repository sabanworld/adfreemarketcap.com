import Chart from 'chart.js/auto';

function cssVar(name, fallback) {
    const value = getComputedStyle(document.documentElement).getPropertyValue(name).trim();

    return value || fallback;
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

    const stroke = up ? cssVar('--up-500', '#0E9F6E') : cssVar('--down-500', '#D8433B');
    const fill = up ? 'rgba(14, 159, 110, 0.12)' : 'rgba(216, 67, 59, 0.12)';
    const tick = cssVar('--text-faint', '#ADA697');
    const grid = cssVar('--border-card', '#EFEAE0');

    if (canvas._afmcChart) {
        canvas._afmcChart.destroy();
        canvas._afmcChart = null;
    }

    // Chart.js leaves sizing attrs that confuse a remount on the same node.
    canvas.removeAttribute('width');
    canvas.removeAttribute('height');
    canvas.style.height = '280px';
    canvas.style.width = '100%';

    canvas._afmcChart = new Chart(canvas, {
        type: 'line',
        data: {
            labels,
            datasets: [{
                data: values,
                borderColor: stroke,
                backgroundColor: fill,
                fill: true,
                pointRadius: 0,
                tension: 0.25,
                borderWidth: 1.75,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: {
                    display: true,
                    ticks: { maxTicksLimit: 5, color: tick, font: { family: 'Public Sans Variable', size: 11 } },
                    grid: { color: grid },
                },
                y: {
                    ticks: {
                        color: tick,
                        font: { family: 'JetBrains Mono Variable', size: 11 },
                        callback: (v) => (symbolAfter ? v + symbol : symbol + v),
                    },
                    grid: { color: grid },
                },
            },
        },
    });
}

window.afmcMountCoinChart = mountCoinChart;

document.addEventListener('livewire:navigated', () => {
    // Full navigations re-run @script; this is a safety net if data attrs exist.
    const canvas = document.getElementById('coin-chart');

    if (canvas?.dataset?.labels) {
        mountCoinChart();
    }
});
