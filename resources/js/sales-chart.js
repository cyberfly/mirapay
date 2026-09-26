import Chart from 'chart.js/auto';

document.addEventListener('alpine:init', () => {
    window.Alpine.data('salesChart', (series) => ({
        chart: null,

        init() {
            const dark = document.documentElement.classList.contains('dark');
            const grid = dark ? 'rgba(148, 163, 184, 0.15)' : 'rgba(14, 165, 233, 0.12)';
            const text = dark ? '#cbd5e1' : '#475569';

            this.chart = new Chart(this.$refs.canvas, {
                type: 'line',
                data: {
                    labels: series.labels,
                    datasets: [{
                        label: 'Jualan (RM)',
                        data: series.values,
                        borderColor: '#0ea5e9',
                        backgroundColor: 'rgba(14, 165, 233, 0.15)',
                        fill: true,
                        tension: 0.3,
                        pointRadius: series.values.length > 40 ? 0 : 3,
                        pointHoverRadius: 5,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: (ctx) => 'RM' + ctx.parsed.y.toLocaleString('en-MY', { minimumFractionDigits: 2, maximumFractionDigits: 2 }),
                            },
                        },
                    },
                    scales: {
                        x: { grid: { display: false }, ticks: { color: text, maxTicksLimit: 8 } },
                        y: {
                            beginAtZero: true,
                            grid: { color: grid },
                            ticks: { color: text, callback: (v) => 'RM' + v.toLocaleString('en-MY') },
                        },
                    },
                },
            });
        },

        destroy() {
            this.chart?.destroy();
        },
    }));
});
