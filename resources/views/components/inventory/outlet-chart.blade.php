@props(['title', 'chartData', 'outletFilter', 'wireKey'])

{{--
    An interactive value-per-outlet bar chart, shared by the Staff Meals and
    Transfers tabs — both group by outlet and both click through the same
    filterByOutletChart()/outletFilter pair on the Livewire component, so one
    component covers both instead of two copies of the same ~90 lines of
    Chart.js/Alpine that would drift apart the next time either changed.

    Same visual pattern as the Purchases-by-Supplier and department-by-tab
    charts in resources/views/livewire/inventory/index.blade.php — those stay
    inline rather than moving here, since they are already working and tested
    and their click semantics (a name fallback for suppliers, 'none' for "no
    department") don't quite match this component's plain outlet-id shape.
--}}

@if (count($chartData['labels']) > 0)
    @once
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
    @endonce

    <div class="card p-5 mb-4">
        <div class="flex items-center justify-between mb-3">
            <div>
                <h2 class="text-sm font-semibold text-gray-800">{{ $title }}</h2>
                <p class="text-xs text-gray-600 mt-0.5">click a bar to filter the table below</p>
            </div>
            @if ($outletFilter !== '')
                <button wire:click="$set('outletFilter', '')" class="text-xs text-brand-600 hover:text-brand-700 font-medium whitespace-nowrap">
                    Clear outlet filter ✕
                </button>
            @endif
        </div>

        <div class="relative"
             style="height: {{ max(160, count($chartData['labels']) * 34 + 20) }}px"
             wire:key="{{ $wireKey }}-{{ md5(json_encode($chartData)) }}-{{ $outletFilter }}"
             x-data="{
                chartInstance: null,
                init() {
                    const already = Chart.getChart(this.$refs.canvas);
                    if (already) { already.destroy(); }

                    const data = @js($chartData);
                    const activeOutletId = @js($outletFilter !== '' ? (int) $outletFilter : null);
                    const ctx = this.$refs.canvas.getContext('2d');
                    this.chartInstance = new Chart(ctx, {
                        type: 'bar',
                        data: {
                            labels: data.labels,
                            datasets: [{
                                data: data.values,
                                backgroundColor: data.colors,
                                borderRadius: 3,
                                borderWidth: data.outletIds.map(id => activeOutletId !== null && id === activeOutletId ? 2 : 0),
                                borderColor: '#0f172a',
                                barThickness: 20,
                            }],
                        },
                        options: {
                            indexAxis: 'y',
                            responsive: true,
                            maintainAspectRatio: false,
                            onClick: (evt, elements) => {
                                if (!elements.length) return;
                                const i = elements[0].index;
                                const outletId = data.outletIds[i];
                                if (outletId === null) return;
                                this.$wire.filterByOutletChart(outletId);
                            },
                            onHover: (evt, elements) => {
                                evt.native.target.style.cursor = elements.length ? 'pointer' : 'default';
                            },
                            plugins: {
                                legend: { display: false },
                                tooltip: {
                                    callbacks: {
                                        label(item) {
                                            const i = item.dataIndex;
                                            const share = data.shares[i];
                                            const n = data.counts[i];
                                            const amount = item.parsed.x.toLocaleString('en-MY', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                                            return [
                                                'RM ' + amount + '  (' + share + '% of total)',
                                                n + ' ' + data.noun + (n === 1 ? '' : 's'),
                                            ];
                                        },
                                    },
                                },
                            },
                            scales: {
                                x: {
                                    beginAtZero: true,
                                    grid: { color: 'rgba(0,0,0,0.05)' },
                                    ticks: {
                                        font: { size: 10 },
                                        callback: v => 'RM ' + v.toLocaleString(),
                                    },
                                },
                                y: {
                                    grid: { display: false },
                                    ticks: { font: { size: 10.5 } },
                                },
                            },
                        },
                    });
                },
             }"
             x-init="init()">
            <canvas x-ref="canvas"></canvas>
        </div>
    </div>
@endif
