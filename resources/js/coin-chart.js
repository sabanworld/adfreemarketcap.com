import Chart from 'chart.js/auto';

function mountCoinChart() {
    const canvas = document.getElementById('coin-chart');

    if (! canvas) {
        return;
    }

    const labels = JSON.parse(canvas.dataset.labels || '[]');
    const values = JSON.parse(canvas.dataset.values || '[]');
    const up = canvas.dataset.up === '1';
    const symbol = canvas.dataset.symbol || '$';
    const symbolAfter = canvas.dataset.symbolAfter === '1';
    const stroke = up ? '#0E9F6E' : '#D8433B';
    const fill = up ? 'rgba(14, 159, 110, 0.12)' : 'rgba(216, 67, 59, 0.12)';

    if (canvas._afmcChart) {
        canvas._afmcChart.destroy();
    }

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
            plugins: { legend: { display: false } },
            scales: {
                x: {
                    display: true,
                    ticks: { maxTicksLimit: 5, color: '#ADA697', font: { family: 'Public Sans Variable', size: 11 } },
                    grid: { color: '#EFEAE0' },
                },
                y: {
                    ticks: {
                        color: '#ADA697',
                        font: { family: 'JetBrains Mono Variable', size: 11 },
                        callback: (v) => (symbolAfter ? v + symbol : symbol + v),
                    },
                    grid: { color: '#EFEAE0' },
                },
            },
        },
    });
}

mountCoinChart();
document.addEventListener('livewire:navigated', mountCoinChart);
