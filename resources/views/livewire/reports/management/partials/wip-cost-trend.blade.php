{{-- A cost's weekly trend: bars in RM, a line for its share of sales. Shared by
     the Wastage and Staff meals slides, which ask the same question of it. --}}
<div class="relative h-64" :class="presenting && '!h-[45vh]'"
     wire:key="wip-{{ $key }}-{{ md5(json_encode($chart)) }}"
     x-data="{
        init() {
            const old = Chart.getChart(this.$refs.c); if (old) { old.destroy(); }
            const d = @js($chart);
            const label = @js($label);
            const rm = v => 'RM ' + Number(v).toLocaleString('en-MY', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            new Chart(this.$refs.c, {
                data: {
                    labels: d.labels,
                    datasets: [
                        { type: 'bar', label: label, data: d.values, backgroundColor: d.color, borderRadius: 3, yAxisID: 'y', order: 2 },
                        { type: 'line', label: '% of sales', data: d.pct, borderColor: d.line, backgroundColor: d.line, yAxisID: 'pct', tension: 0.3, spanGaps: true, order: 1 },
                    ],
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    plugins: {
                        legend: { position: 'bottom' },
                        tooltip: { callbacks: { label: i => i.dataset.yAxisID === 'pct'
                            ? i.dataset.label + ': ' + (i.parsed.y === null ? '—' : i.parsed.y.toFixed(1) + '%')
                            : i.dataset.label + ': ' + rm(i.parsed.y) } },
                    },
                    scales: {
                        y: { beginAtZero: true, ticks: { callback: v => 'RM ' + Number(v).toLocaleString() } },
                        pct: { position: 'right', beginAtZero: true, grid: { drawOnChartArea: false }, ticks: { callback: v => v + '%' } },
                    },
                },
            });
        },
     }">
    <canvas x-ref="c"></canvas>
</div>
