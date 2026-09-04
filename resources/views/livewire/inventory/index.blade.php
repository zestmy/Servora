<div>
    {{-- Flash --}}
    @if (session()->has('success'))
        <div wire:key="flash-{{ microtime(true) }}" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)"
             class="mb-4 px-4 py-3 bg-success-50 border border-success-200 text-success-700 text-sm rounded-lg">
            {{ session('success') }}
        </div>
    @endif

    {{-- Header --}}
    <x-page-header title="Stock Management"
                   eyebrow="Inventory"
                   subtitle="Counts, wastage, staff meals, transfers and captured purchases.">
        <x-slot:actions>
            @if ($tab === 'stock-takes')
                {{-- One inventory out of however many completed sheets the range
                     holds, for filing. Carries the filters the table is showing.
                     Drafts are not in it, so when none are finished the button
                     says so rather than handing back an empty PDF. --}}
                @if ($consolidatedUrl && $records->total() > 0)
                    @if ($completedInRange > 0)
                        <a href="{{ $consolidatedUrl }}" class="btn-secondary">
                            <x-icon name="printer" size="h-4 w-4" />
                            Consolidated Inventory
                        </a>
                        <a href="{{ $consolidatedExcelUrl }}" class="btn-secondary"
                           title="Same inventory as a workbook, with each sheet's count broken out in its own column for checking.">
                            <x-icon name="download" size="h-4 w-4" />
                            Consolidate to Excel
                        </a>
                    @else
                        <span class="btn-secondary opacity-50 cursor-not-allowed"
                              title="Nothing to file yet: the export covers completed counts, and every count in this range is still a draft.">
                            <x-icon name="printer" size="h-4 w-4" />
                            Consolidated Inventory
                        </span>
                        <span class="btn-secondary opacity-50 cursor-not-allowed"
                              title="Nothing to file yet: the export covers completed counts, and every count in this range is still a draft.">
                            <x-icon name="download" size="h-4 w-4" />
                            Consolidate to Excel
                        </span>
                    @endif
                @endif
                @canDo('inventory.stock_takes.record')
                    <a href="{{ route('inventory.stock-takes.create') }}" class="btn-primary">+ New Stock Take</a>
                @endcanDo
            @elseif ($tab === 'wastage')
                @canDo('inventory.wastage.record')
                    <a href="{{ route('inventory.wastage.create') }}" class="btn-primary">+ Record Wastage</a>
                @endcanDo
            @elseif ($tab === 'staff-meals')
                @canDo('inventory.staff_meals.record')
                    <a href="{{ route('inventory.staff-meals.create') }}" class="btn-primary">+ Record Staff Meal</a>
                @endcanDo
            @elseif ($tab === 'transfers')
                @canDo('inventory.transfers.record')
                    {{-- btn-primary, not a hardcoded teal: the accent is a token
                         so that changing it is one edit in tailwind.config.js. --}}
                    <a href="{{ route('inventory.transfers.create') }}" class="btn-primary">+ New Transfer</a>
                @endcanDo
            @elseif ($tab === 'purchases')
                {{-- Who we are actually paying, over whatever the filters are
                     currently showing. Hidden on an empty range rather than
                     handing back a page of zeroes. --}}
                @if ($supplierSummaryUrl && $records->total() > 0)
                    <a href="{{ $supplierSummaryUrl }}" class="btn-secondary">
                        <x-icon name="printer" size="h-4 w-4" />
                        Purchases by Supplier
                    </a>
                @endif
                @canDo('inventory.purchases.record')
                    <a href="{{ route('inventory.purchases.create') }}" class="btn-primary">+ Record Purchase</a>
                @endcanDo
            @endif
        </x-slot:actions>
    </x-page-header>

    {{-- Tabs. A segmented control rather than five underlined buttons in five
         different accent colours — the colour used to change with the tab,
         which made the accent mean "which tab" instead of "this is the
         action". --}}
    {{-- THE SCROLLER HAS TO BE A SEPARATE, BLOCK-LEVEL WRAPPER. `.seg` is
         `inline-flex`, so it shrink-to-fits its content and `overflow-x-auto`
         on it could never engage — five tabs measured 486px inside a 358px
         card and simply pushed the page sideways, taking the stock-take table
         out past the right edge with them. Scrolling belongs to a block box
         that fills the width; the pill strip inside it stays its natural
         size and slides. --}}
    <div class="mb-4 overflow-x-auto">
        <div class="seg">
            @foreach ($tabs as $key => $label)
                <button wire:click="$set('tab', '{{ $key }}')"
                        class="seg-item whitespace-nowrap {{ $tab === $key ? 'seg-item-on' : '' }}">
                    {{ $label }}
                </button>
            @endforeach
        </div>
    </div>

    {{-- Stats: about the tab you are on, under the filters you have set.

         Five fixed cards used to describe five different tabs at once, three of
         them about tabs a company may never have used — so most of the row read
         as zeros and dashes no matter what you were doing. They were also
         hardcoded to this month and ignored every filter, so narrowing the
         table to last week left the cards answering about the month. --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
        <div class="stat">
            <p class="stat-label">{{ $stats['label'] }} {{ $stats['rangeLabel'] }}</p>
            <p class="stat-value">{{ number_format($stats['count']) }}</p>
            @if ($stats['previousCount'] !== null)
                @php $delta = $stats['count'] - $stats['previousCount']; @endphp
                {{-- Deliberately uncoloured. More wastage and more purchases are
                     the same arithmetic and opposite news, so a green "up" would
                     be wrong half the time. The label says what is counted; the
                     reader knows which way is good. --}}
                <p class="text-xs text-gray-600 mt-1 tabular-nums">
                    {{ $delta > 0 ? '▲' : ($delta < 0 ? '▼' : '—') }}
                    {{ $delta === 0 ? 'no change' : abs($delta) . ' vs the period before' }}
                </p>
            @endif
        </div>

        @if ($stats['moneyLabel'])
            <div class="stat">
                <p class="stat-label">{{ $stats['moneyLabel'] }} {{ $stats['rangeLabel'] }}</p>
                <p class="stat-value tabular-nums">RM {{ number_format($stats['value'] ?? 0, 2) }}</p>
                @if ($stats['previousValue'] !== null)
                    <p class="text-xs text-gray-600 mt-1 tabular-nums">
                        Previous period: RM {{ number_format($stats['previousValue'], 2) }}
                    </p>
                @endif

                {{-- Where that money sits. An outlet that counts its kitchen, bar
                     and store as separate sheets is not asking for one number;
                     these are the same rows the total above is made of, so the
                     parts always add up to it. --}}
                @if (count($departmentValues) > 1)
                    <div class="mt-3 pt-3 border-t border-gray-100 space-y-1.5">
                        @foreach (array_slice($departmentValues, 0, 6) as $dept)
                            <div>
                                <div class="flex items-baseline justify-between gap-3 text-xs">
                                    <span class="text-gray-600 truncate">{{ $dept['name'] }}</span>
                                    <span class="tabular-nums font-medium text-gray-800 shrink-0">
                                        RM {{ number_format($dept['value'], 2) }}
                                    </span>
                                </div>
                                <div class="mt-1 h-1 rounded-full bg-gray-100 overflow-hidden">
                                    <div class="h-full rounded-full bg-brand-500"
                                         style="width: {{ number_format(max($dept['share'], 1), 2, '.', '') }}%"></div>
                                </div>
                            </div>
                        @endforeach
                        @if (count($departmentValues) > 6)
                            <p class="text-xs text-gray-600 pt-0.5">
                                +{{ count($departmentValues) - 6 }} more
                                {{ Str::plural('department', count($departmentValues) - 6) }}
                            </p>
                        @endif
                    </div>
                @endif
            </div>
        @endif

        @if ($highlight)
            <div class="stat">
                <p class="stat-label">{{ $highlight['label'] }}</p>
                <p class="stat-value {{ $highlight['tone'] === 'warning' ? 'text-warning-600' : '' }}">
                    {{ $highlight['value'] }}
                </p>
            </div>
        @endif
    </div>

    {{-- Filters. One strip for every tab: a filter is written once and every
         tab that can answer it offers it. Department lives on four of the five
         tables and every form fills it, but only Wastage ever filtered by it;
         supplier is stored and used on Purchases and filtered nowhere. --}}
    <div class="toolbar mb-4">
        <div class="flex flex-wrap items-center gap-2">
            @foreach (\App\Livewire\Inventory\Index::quickRangeOptions() as $key => $label)
                <button wire:click="setQuickRange('{{ $key }}')"
                        class="px-3 py-1.5 text-xs font-medium rounded-full border transition
                               {{ $quickRange === $key
                                    ? 'bg-brand-600 text-white border-brand-600'
                                    : 'bg-white text-gray-600 border-gray-200 hover:border-brand-300 hover:text-brand-700' }}">
                    {{ $label }}
                </button>
            @endforeach

            @if ($quickRange === '' && ($dateFrom || $dateTo))
                <span class="badge-neutral">Custom range</span>
            @endif
        </div>

        {{-- The row aligns its controls rather than stretching them. A flex row
             defaults to align-items: stretch, and the date pair below wrapped to
             two lines when it ran out of room — so every sibling grew to match
             it and the search box and both selects rendered as tall empty boxes.
             The dates are capped and told not to wrap from lg, which stops the
             two-line case arising; the alignment stops it mattering if it does.
             Mobile keeps the column layout and its full-width controls. --}}
        <div class="mt-3 flex flex-col lg:flex-row lg:items-center gap-3">
            <input type="text" wire:model.live.debounce.300ms="search"
                   placeholder="{{ $tab === 'transfers' ? 'Search transfer number…' : ($tab === 'purchases' ? 'Search reference or supplier…' : 'Search reference number…') }}"
                   class="input flex-1" />

            @if ($filterOutlets->isNotEmpty())
                <select wire:model.live="outletFilter" class="input lg:w-44">
                    <option value="">All branches</option>
                    @foreach ($filterOutlets as $o)
                        <option value="{{ $o->id }}">{{ $o->name }}</option>
                    @endforeach
                </select>
            @endif

            @if ($tabConfig['dept'] && $departments->isNotEmpty())
                <select wire:model.live="departmentFilter" class="input lg:w-44">
                    <option value="">All departments</option>
                    @foreach ($departments as $d)
                        <option value="{{ $d->id }}">{{ $d->name }}</option>
                    @endforeach
                    {{-- Offered explicitly: records that predate the column
                         would otherwise be unreachable by any filter value. --}}
                    <option value="none">— No department —</option>
                </select>
            @endif

            @if ($tabConfig['supplier'] && $suppliers->isNotEmpty())
                <select wire:model.live="supplierFilter" class="input lg:w-44">
                    <option value="">All suppliers</option>
                    @foreach ($suppliers as $sup)
                        <option value="{{ $sup->id }}">{{ $sup->name }}</option>
                    @endforeach
                </select>
            @endif

            @if ($tabConfig['status'])
                <select wire:model.live="statusFilter" class="input lg:w-40">
                    <option value="">All statuses</option>
                    <option value="draft">Draft</option>
                    <option value="in_transit">In transit</option>
                    <option value="received">Received</option>
                    <option value="cancelled">Cancelled</option>
                </select>
            @endif

            {{-- Wraps and shrinks. A date input has an intrinsic minimum width,
                 and this pair sits in a column-flex filter row whose line
                 takes its cross size from the widest item — so on a 360px
                 phone the pair stretched every other filter with it and
                 pushed the page over the edge. --}}
            <div class="flex min-w-0 flex-wrap lg:flex-nowrap items-center gap-1">
                <input type="date" wire:model.live="dateFrom" class="input lg:w-40" aria-label="From date" />
                <span class="text-xs text-gray-600">to</span>
                <input type="date" wire:model.live="dateTo" class="input lg:w-40" aria-label="To date" />
            </div>

            <button wire:click="resetFilters" class="btn-ghost whitespace-nowrap">Reset</button>
        </div>
    </div>

    {{-- ── Purchases by Supplier — interactive chart ───────────────────────
         Screen counterpart to the "Purchases by Supplier" PDF: same grouping
         (PurchaseSupplierBreakdown), same colours, but hoverable and — unlike
         anything a PDF can offer — clickable. A bar for a linked supplier sets
         supplierFilter, the same property the dropdown above already drives;
         a hand-typed vendor has no id to filter by, so its bar sets the search
         box instead. The "Other" bar folds two or more suppliers together and
         carries neither, so clicking it does nothing — there is no one
         supplier for it to narrow the table to. --}}
    @if ($tab === 'purchases' && count($supplierChartData['labels']) > 0)
        @once
            <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
        @endonce

        <div class="card p-5 mb-4">
            <div class="flex items-center justify-between mb-3">
                <div>
                    <h2 class="text-sm font-semibold text-gray-800">Purchases by Supplier</h2>
                    <p class="text-xs text-gray-600 mt-0.5">
                        {{ $stats['rangeLabel'] }} · click a bar to filter the table below
                    </p>
                </div>
                @if ($supplierFilter !== '')
                    <button wire:click="$set('supplierFilter', '')" class="text-xs text-brand-600 hover:text-brand-700 font-medium whitespace-nowrap">
                        Clear supplier filter ✕
                    </button>
                @endif
            </div>

            {{-- Keyed on the data (which already changes with every filter) plus
                 the two properties a bar-click can set but that are not
                 themselves part of the chart's own data shape — otherwise a
                 click that sets supplierFilter to a value the chart's OWN data
                 hash does not depend on would leave Alpine's stale closure
                 pointing at the pre-click $wire state for the highlight below. --}}
            <div class="relative"
                 style="height: {{ max(160, count($supplierChartData['labels']) * 34 + 20) }}px"
                 wire:key="supplier-chart-{{ md5(json_encode($supplierChartData)) }}-{{ $supplierFilter }}"
                 x-data="{
                    chartInstance: null,
                    init() {
                        // Livewire/Alpine can process this element's x-init more
                        // than once during the same boot — reliably, on a cold
                        // load, before any interaction — without an intervening
                        // teardown. Chart.js refuses a second instance on a
                        // canvas that already has one, so any survivor from an
                        // earlier pass is destroyed first rather than trusting
                        // init() to run exactly once.
                        const already = Chart.getChart(this.$refs.canvas);
                        if (already) { already.destroy(); }

                        const data = @js($supplierChartData);
                        const activeSupplierId = @js($supplierFilter !== '' ? (int) $supplierFilter : null);
                        const ctx = this.$refs.canvas.getContext('2d');
                        this.chartInstance = new Chart(ctx, {
                            type: 'bar',
                            data: {
                                labels: data.labels,
                                datasets: [{
                                    data: data.spend,
                                    backgroundColor: data.colors,
                                    borderRadius: 3,
                                    borderWidth: data.supplierIds.map(id => activeSupplierId !== null && id === activeSupplierId ? 2 : 0),
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
                                    const supplierId = data.supplierIds[i];
                                    const name = data.names[i];
                                    if (supplierId === null && name === null) return;
                                    this.$wire.filterBySupplier(supplierId, name);
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
                                                const buys = data.purchases[i];
                                                const amount = item.parsed.x.toLocaleString('en-MY', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                                                return [
                                                    'RM ' + amount + '  (' + share + '% of spend)',
                                                    buys + ' ' + (buys === 1 ? 'purchase' : 'purchases'),
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

    {{-- Prep Items tab removed — now under Recipes --}}

    {{-- ── Stock Takes Tab ───────────────────────────────────────────────── --}}
    @if ($tab === 'stock-takes')
        <div class="card overflow-hidden">
            <table class="table-surface min-w-full">
                <thead>
                    <tr>
                        <th class="px-4 py-3 text-left">Date</th>
                        <th class="px-4 py-3 text-left">Reference</th>
                        <th class="px-4 py-3 text-center">Status</th>
                        <th class="px-4 py-3 text-center">Items</th>
                        <th class="px-4 py-3 text-right">Stock Value (RM)</th>
                        <th class="px-4 py-3 text-right">Variance Cost (RM)</th>
                        <th class="px-4 py-3 text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($records as $record)
                        @php
                            $statusColor = match($record->status) {
                                'draft'       => 'bg-gray-100 text-gray-600',
                                'in_progress' => 'bg-yellow-100 text-yellow-700',
                                'completed'   => 'bg-success-100 text-success-700',
                                default       => 'bg-gray-100 text-gray-500',
                            };
                            $varianceCost = floatval($record->total_variance_cost);
                            $stockCost    = floatval($record->total_stock_cost);
                        @endphp
                        <tr class="hover:bg-gray-50 transition">
                            <td class="px-4 py-3 font-medium text-gray-700">
                                {{ $record->stock_take_date->format('d M Y') }}
                                @if ($record->stock_take_date->isToday())
                                    <span class="ml-1 text-xs text-brand-400">Today</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-gray-500">{{ $record->reference_number ?: '—' }}</td>
                            <td class="px-4 py-3 text-center">
                                <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold {{ $statusColor }}">
                                    {{ ucfirst(str_replace('_', ' ', $record->status)) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-center text-gray-600">{{ $record->lines_count }}</td>
                            <td class="px-4 py-3 text-right tabular-nums font-medium text-gray-800">
                                {{ $stockCost > 0 ? number_format($stockCost, 2) : '—' }}
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums font-medium {{ $varianceCost >= 0 ? 'text-success-600' : 'text-danger-600' }}">
                                {{ $varianceCost >= 0 ? '+' : '' }}{{ number_format($varianceCost, 2) }}
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-center gap-2">
                                    <a href="{{ route('inventory.stock-takes.show', $record->id) }}" title="View / Edit"
                                       class="text-brand-500 hover:text-brand-700 transition">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                    </a>
                                    {{-- A finished count is a document: the PDF is for
                                         filing, the workbook for anyone who needs to
                                         work the numbers. Only once it is completed —
                                         a draft is still being counted. --}}
                                    @if ($record->status === 'completed')
                                        <a href="{{ route('inventory.stock-takes.result', $record->id) }}"
                                           title="Download PDF" aria-label="Download this count as PDF"
                                           class="text-gray-500 hover:text-gray-700 transition">
                                            <x-icon name="printer" size="h-4 w-4" />
                                        </a>
                                        <a href="{{ route('inventory.stock-takes.result-excel', $record->id) }}"
                                           title="Download Excel" aria-label="Download this count as Excel"
                                           class="text-success-600 hover:text-success-700 transition">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                            </svg>
                                        </a>
                                    @endif
                                    @if ($record->status === 'draft' || $canDeleteRecords)
                                        <button wire:click="deleteStockTake({{ $record->id }})"
                                                data-confirm-delete="{{ $record->status === 'draft' ? 'Delete this stock take? This cannot be undone.' : 'Delete this COMPLETED stock take? This cannot be undone.' }}"
                                                title="{{ $record->status === 'draft' ? 'Delete' : 'Delete completed stock take' }}"
                                                class="text-danger-400 hover:text-danger-600 transition">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-12 text-center text-gray-600">
                                <div class="text-3xl mb-2">📋</div>
                                <p class="font-medium">No stock takes yet</p>
                                <p class="text-xs mt-1">
                                    @canDo('inventory.stock_takes.record')
                                    <a href="{{ route('inventory.stock-takes.create') }}" class="text-brand-500 underline">Start a new stock take</a>
                                    @endcanDo
                                </p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            @if (method_exists($records, 'hasPages') && $records->hasPages())
                <div class="px-4 py-3 border-t border-gray-100">
                    {{ $records->links() }}
                </div>
            @endif
        </div>
    @endif

    {{-- ── Purchases Tab ─────────────────────────────────────────────────── --}}
    @if ($tab === 'purchases')
        <div class="card overflow-hidden">
            <table class="table-surface min-w-full">
                <thead>
                    <tr>
                        <th class="px-4 py-3 text-left">Date</th>
                        <th class="px-4 py-3 text-left">Department</th>
                        <th class="px-4 py-3 text-left">Supplier</th>
                        <th class="px-4 py-3 text-left">Reference</th>
                        <th class="px-4 py-3 text-right">Amount (RM)</th>
                        <th class="px-4 py-3 text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($records as $record)
                        <tr class="hover:bg-gray-50 transition">
                            <td class="px-4 py-3 font-medium text-gray-700">
                                {{ $record->purchase_date->format('d M Y') }}
                                @if ($record->purchase_date->isToday())
                                    <span class="ml-1 text-xs text-brand-400">Today</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-gray-600">{{ $record->department?->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-500">{{ $record->supplier_label }}</td>
                            <td class="px-4 py-3 text-gray-500">{{ $record->reference_number ?: '—' }}</td>
                            <td class="px-4 py-3 text-right tabular-nums font-medium text-gray-800">
                                {{ number_format(floatval($record->amount), 2) }}
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-center gap-2">
                                    <a href="{{ route('inventory.purchases.show', $record->id) }}" title="View / Edit"
                                       class="text-brand-500 hover:text-brand-700 transition">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                    </a>
                                    @if ($canDelete['purchases'])
                                    <button wire:click="deletePurchase({{ $record->id }})"
                                            data-confirm-delete="Delete this purchase record? This cannot be undone."
                                            title="Delete"
                                            class="text-danger-400 hover:text-danger-600 transition">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-12 text-center text-gray-600">
                                <div class="text-3xl mb-2">🧾</div>
                                <p class="font-medium">No purchases recorded yet</p>
                                <p class="text-xs mt-1">
                                    @canDo('inventory.purchases.record')
                                    <a href="{{ route('inventory.purchases.create') }}" class="text-brand-500 underline">Record a purchase</a>
                                    @endcanDo
                                </p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            @if (method_exists($records, 'hasPages') && $records->hasPages())
                <div class="px-4 py-3 border-t border-gray-100">
                    {{ $records->links() }}
                </div>
            @endif
        </div>
    @endif

    {{-- ── Staff Meals Tab ──────────────────────────────────────────────── --}}
    @if ($tab === 'staff-meals')
        <div class="card overflow-hidden">
            <table class="table-surface min-w-full">
                <thead>
                    <tr>
                        <th class="px-4 py-3 text-left">Date</th>
                        <th class="px-4 py-3 text-left">Reference</th>
                        <th class="px-4 py-3 text-center">Items</th>
                        <th class="px-4 py-3 text-right">Total Cost (RM)</th>
                        <th class="px-4 py-3 text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($records as $record)
                        <tr class="hover:bg-gray-50 transition">
                            <td class="px-4 py-3 font-medium text-gray-700">
                                {{ $record->meal_date->format('d M Y') }}
                                @if ($record->meal_date->isToday())
                                    <span class="ml-1 text-xs text-brand-400">Today</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-gray-500">{{ $record->reference_number ?: '—' }}</td>
                            <td class="px-4 py-3 text-center text-gray-600">{{ $record->lines_count }}</td>
                            <td class="px-4 py-3 text-right tabular-nums font-medium text-purple-600">
                                {{ number_format($record->total_cost, 2) }}
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-center gap-2">
                                    <a href="{{ route('inventory.staff-meals.show', $record->id) }}" title="Edit"
                                       class="text-brand-500 hover:text-brand-700 transition">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                    </a>
                                    @if ($canDelete['staff_meals'])
                                    <button wire:click="deleteStaffMeal({{ $record->id }})"
                                            data-confirm-delete="Delete this staff meal record? This cannot be undone."
                                            title="Delete"
                                            class="text-danger-400 hover:text-danger-600 transition">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-12 text-center text-gray-600">
                                <div class="text-3xl mb-2">🍽️</div>
                                <p class="font-medium">No staff meal records yet</p>
                                <p class="text-xs mt-1">
                                    @canDo('inventory.staff_meals.record')
                                    <a href="{{ route('inventory.staff-meals.create') }}" class="text-brand-500 underline">Record today's staff meals</a>
                                    @endcanDo
                                </p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>

                @if ($records->count() > 0)
                    <tfoot class="bg-gray-50 border-t-2 border-gray-200 text-sm font-semibold text-gray-700">
                        <tr>
                            <td colspan="3" class="px-4 py-3 text-right text-xs text-gray-500 font-normal">
                                Page total ({{ $records->count() }} records)
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums text-purple-600">
                                {{ number_format($records->sum('total_cost'), 2) }}
                            </td>
                            <td></td>
                        </tr>
                    </tfoot>
                @endif
            </table>

            @if (method_exists($records, 'hasPages') && $records->hasPages())
                <div class="px-4 py-3 border-t border-gray-100">
                    {{ $records->links() }}
                </div>
            @endif
        </div>
    @endif

    {{-- ── Transfers Tab ─────────────────────────────────────────────────── --}}
    @if ($tab === 'transfers')
        <div class="card overflow-hidden">
            <table class="table-surface min-w-full">
                <thead>
                    <tr>
                        <th class="px-4 py-3 text-left">Date</th>
                        <th class="px-4 py-3 text-left">Transfer #</th>
                        <th class="px-4 py-3 text-left">From</th>
                        <th class="px-4 py-3 text-left">To</th>
                        <th class="px-4 py-3 text-center">Status</th>
                        <th class="px-4 py-3 text-center">Items</th>
                        <th class="px-4 py-3 text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($records as $transfer)
                        @php
                            $statusColor = match($transfer->status) {
                                'draft'      => 'bg-gray-100 text-gray-600',
                                'in_transit' => 'bg-yellow-100 text-yellow-700',
                                'received'   => 'bg-success-100 text-success-700',
                                'cancelled'  => 'bg-danger-100 text-danger-600',
                                default      => 'bg-gray-100 text-gray-500',
                            };
                        @endphp
                        <tr class="hover:bg-gray-50 transition">
                            <td class="px-4 py-3 font-medium text-gray-700">
                                {{ $transfer->transfer_date->format('d M Y') }}
                                @if ($transfer->transfer_date->isToday())
                                    <span class="ml-1 text-xs text-brand-400">Today</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-gray-500">{{ $transfer->transfer_number }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $transfer->fromOutlet?->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $transfer->toOutlet?->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-center">
                                <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold {{ $statusColor }}">
                                    {{ ucfirst(str_replace('_', ' ', $transfer->status)) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-center text-gray-600">{{ $transfer->lines_count }}</td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-center gap-2">
                                    <a href="{{ route('inventory.transfers.show', $transfer->id) }}" title="View / Edit"
                                       class="text-brand-500 hover:text-brand-700 transition">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                    </a>
                                    @if ($transfer->status === 'draft' || $canDelete['transfers'])
                                        <button wire:click="deleteTransfer({{ $transfer->id }})"
                                                data-confirm-delete="{{ $transfer->status === 'draft' ? 'Delete this transfer? This cannot be undone.' : 'Delete this ' . str_replace('_', ' ', $transfer->status) . ' transfer? This cannot be undone.' }}"
                                                title="{{ $transfer->status === 'draft' ? 'Delete' : 'Delete transfer' }}"
                                                class="text-danger-400 hover:text-danger-600 transition">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-12 text-center text-gray-600">
                                <div class="text-3xl mb-2">🔄</div>
                                <p class="font-medium">No transfers yet</p>
                                <p class="text-xs mt-1">
                                    @canDo('inventory.transfers.record')
                                    <a href="{{ route('inventory.transfers.create') }}" class="text-teal-600 underline">Create your first transfer</a>
                                    @endcanDo
                                </p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            @if (method_exists($records, 'hasPages') && $records->hasPages())
                <div class="px-4 py-3 border-t border-gray-100">
                    {{ $records->links() }}
                </div>
            @endif
        </div>
    @endif

    {{-- ── Wastage Tab ───────────────────────────────────────────────────── --}}
    @if ($tab === 'wastage')
        <div class="card overflow-hidden">
            <table class="table-surface min-w-full">
                <thead>
                    <tr>
                        <th class="px-4 py-3 text-left">Date</th>
                        <th class="px-4 py-3 text-left">Reference</th>
                        <th class="px-4 py-3 text-left">Department</th>
                        <th class="px-4 py-3 text-left">Reason</th>
                        <th class="px-4 py-3 text-center">Items</th>
                        <th class="px-4 py-3 text-right">Total Cost (RM)</th>
                        <th class="px-4 py-3 text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($records as $record)
                        <tr class="hover:bg-gray-50 transition">
                            <td class="px-4 py-3 font-medium text-gray-700">
                                {{ $record->wastage_date->format('d M Y') }}
                                @if ($record->wastage_date->isToday())
                                    <span class="ml-1 text-xs text-brand-400">Today</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-gray-500">{{ $record->reference_number ?: '—' }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $record->department?->name ?: '—' }}</td>
                            {{-- Reason lives on the LINES, so one record can carry several.
                                 Distinct values, first two with a count for the rest — enough
                                 to scan the table without turning a cell into a list. --}}
                            <td class="px-4 py-3 text-gray-600">
                                @php $reasons = $record->lines->pluck('reason')->filter()->unique()->values(); @endphp
                                @if ($reasons->isEmpty())
                                    <span class="text-gray-400">—</span>
                                @else
                                    <span title="{{ $reasons->implode(', ') }}">{{ $reasons->take(2)->implode(', ') }}@if ($reasons->count() > 2)<span class="text-gray-400"> +{{ $reasons->count() - 2 }}</span>@endif</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-center text-gray-600">{{ $record->lines_count }}</td>
                            <td class="px-4 py-3 text-right tabular-nums font-medium text-danger-600">
                                {{ number_format($record->total_cost, 2) }}
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-center gap-2">
                                    <a href="{{ route('inventory.wastage.show', $record->id) }}" title="Edit"
                                       class="text-brand-500 hover:text-brand-700 transition">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                    </a>
                                    @if ($canDelete['wastage'])
                                    <button wire:click="deleteWastage({{ $record->id }})"
                                            data-confirm-delete="Delete this wastage record? This cannot be undone."
                                            title="Delete"
                                            class="text-danger-400 hover:text-danger-600 transition">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-12 text-center text-gray-600">
                                <div class="text-3xl mb-2">🗑️</div>
                                <p class="font-medium">No wastage records yet</p>
                                <p class="text-xs mt-1">
                                    @canDo('inventory.wastage.record')
                                    <a href="{{ route('inventory.wastage.create') }}" class="text-brand-500 underline">Record today's wastage</a>
                                    @endcanDo
                                </p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>

                @if ($records->count() > 0)
                    <tfoot class="bg-gray-50 border-t-2 border-gray-200 text-sm font-semibold text-gray-700">
                        <tr>
                            <td colspan="5" class="px-4 py-3 text-right text-xs text-gray-500 font-normal">
                                Page total ({{ $records->count() }} records)
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums text-danger-600">
                                {{ number_format($records->sum('total_cost'), 2) }}
                            </td>
                            <td></td>
                        </tr>
                    </tfoot>
                @endif
            </table>

            @if (method_exists($records, 'hasPages') && $records->hasPages())
                <div class="px-4 py-3 border-t border-gray-100">
                    {{ $records->links() }}
                </div>
            @endif
        </div>
    @endif

</div>
