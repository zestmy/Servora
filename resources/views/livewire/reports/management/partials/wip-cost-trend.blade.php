{{-- A figure's trend: bars per period, and an optional line on a right-hand axis.
     Shared by the Wastage, Staff meals, Stock transfers, Overtime and Labour
     slides. Units come from the chart data (bar_prefix/bar_suffix, line_label/
     line_suffix) so the same partial draws RM bars with a % of sales line,
     or overtime cost with an hours line. A null `pct` draws no line and no
     right-hand axis. --}}
<div class="relative h-64" :class="presenting && '!h-[34vh]'"
     wire:key="wip-{{ $key }}-{{ md5(json_encode($chart)) }}"
     x-data="{
        init() {
            const old = Chart.getChart(this.$refs.c); if (old) { old.destroy(); }
            const d = @js($chart);
            const label = @js($label);
            const pre = d.bar_prefix ?? 'RM ';
            const suf = d.bar_suffix ?? '';
            const lineSuf = d.line_suffix ?? '%';
            const bar = v => pre + Number(v).toLocaleString('en-MY', { minimumFractionDigits: pre ? 2 : 1, maximumFractionDigits: pre ? 2 : 1 }) + suf;
            new Chart(this.$refs.c, {
                data: {
                    labels: d.labels,
                    datasets: [
                        { type: 'bar', label: label, data: d.values, backgroundColor: d.color, borderRadius: 3, yAxisID: 'y', order: 2 },
                    ].concat(d.pct ? [
                        { type: 'line', label: d.line_label ?? '% of sales', data: d.pct, borderColor: d.line, backgroundColor: d.line, yAxisID: 'pct', tension: 0.3, spanGaps: true, order: 1 },
                    ] : []),
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    plugins: {
                        legend: { position: 'bottom' },
                        tooltip: { callbacks: { label: i => i.dataset.yAxisID === 'pct'
                            ? i.dataset.label + ': ' + (i.parsed.y === null ? '—' : Number(i.parsed.y).toFixed(1) + lineSuf)
                            : i.dataset.label + ': ' + bar(i.parsed.y) } },
                    },
                    scales: Object.assign(
                        { y: { beginAtZero: true, ticks: { callback: v => pre + Number(v).toLocaleString() + suf } } },
                        d.pct ? { pct: { position: 'right', beginAtZero: true, grid: { drawOnChartArea: false }, ticks: { callback: v => v + lineSuf } } } : {},
                    ),
                },
            });
        },
     }">
    <canvas x-ref="c"></canvas>
</div>
