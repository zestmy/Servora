<div>
    @if (session()->has('success'))
        <div wire:key="flash-{{ microtime(true) }}" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)"
             class="mb-4 px-4 py-3 bg-success-50 border border-success-200 text-success-700 text-sm rounded-lg">
            {{ session('success') }}
        </div>
    @endif
    @if (session()->has('error'))
        <div wire:key="flash-err-{{ microtime(true) }}" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)"
             class="mb-4 px-4 py-3 bg-danger-50 border border-danger-200 text-danger-700 text-sm rounded-lg">
            {{ session('error') }}
        </div>
    @endif

    {{-- Pending-hours notice: PDF/print exports are approved-only, so any hours
         awaiting approval in the current view won't appear in a downloaded PDF. --}}
    @if ($totalPendingHours > 0)
        <div class="mb-4 px-4 py-3 bg-warning-50 border border-warning-200 text-warning-800 text-sm rounded-lg flex items-start gap-2">
            <svg class="w-4 h-4 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
            <span>
                <strong>{{ number_format($totalPendingHours, 1) }} hrs</strong> of OT in this view are still pending approval and will <strong>not</strong> appear in PDF/print exports (which include approved claims only). Approve the claims first, then download.
            </span>
        </div>
    @endif

    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-lg font-bold text-gray-800">Overtime Claims</h1>
            <p class="text-xs text-gray-600 mt-0.5">Submit and manage staff overtime claims</p>
        </div>
        <div class="flex items-center gap-2">
            {{-- TWO STATEMENTS, on purpose.

                 "Print Approved" is the signed-and-filed document and always
                 means approved claims for a date range; people rely on it
                 meaning exactly that, so it was left alone.

                 "Print Filtered" answers the other question — print what I am
                 looking at — including statuses the approved-only document
                 will never show: the pending queue, the rejected ones, or
                 everything at once. Same per-employee layout either way.

                 A plain link rather than a modal: the filters have already
                 been chosen on the screen behind it, and asking for them
                 again in a dialog is asking twice. --}}
            <a href="{{ route('hr.ot-claims.filtered-pdf', $this->currentFilter()->toQuery()) }}"
               target="_blank" rel="noopener"
               class="btn-secondary"
               title="Statement for exactly what this list is showing, including its status filter.">
                Print Filtered
            </a>
            <button wire:click="openPdfModal"
                    class="btn-secondary">
                Print Approved
            </button>
            <button wire:click="openSummaryModal"
                    class="btn-secondary">
                Summary Report
            </button>
            @canDo('hr.view')
            <a href="{{ route('hr.employees') }}"
               class="btn-secondary">
                Employee List
            </a>
            @endcanDo
            <button wire:click="openAddEmployee"
                    class="btn-secondary">
                + Add Employee
            </button>
            <button wire:click="openCreate"
                    class="btn-primary">
                + New OT Claim
            </button>
        </div>
    </div>

    {{-- Quick Date Range --}}
    <div class="flex items-center gap-1.5 mb-3 flex-wrap">
        @foreach ([
            'today'      => 'Today',
            'yesterday'  => 'Yesterday',
            'last_7'     => 'Last 7 Days',
            'this_week'  => 'This Week',
            'last_week'  => 'Last Week',
            'this_month' => 'This Month',
            'last_month' => 'Last Month',
            'this_year'  => 'This Year',
            'last_year'  => 'Last Year',
            'all'        => 'All',
        ] as $rangeKey => $rangeLabel)
            <button wire:click="setQuickRange('{{ $rangeKey }}')"
                    class="px-3 py-1.5 text-xs font-medium rounded-lg border transition
                           {{ $quickRange === $rangeKey
                               ? 'bg-brand-600 text-white border-brand-600'
                               : 'bg-white text-gray-600 border-gray-200 hover:bg-gray-50' }}">
                {{ $rangeLabel }}
            </button>
        @endforeach
    </div>

    {{-- Filters Section --}}
    <div class="card p-4 mb-4">
        <div class="flex flex-wrap gap-3 items-center">
            {{-- Outlet filter (no "All Outlets" - only specific outlets) --}}
            @if ($multiOutlet)
                <select wire:model.live="outletFilter" class="rounded-lg border-gray-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500">
                    @foreach ($outlets as $outlet)
                        <option value="{{ $outlet->id }}">{{ $outlet->name }}</option>
                    @endforeach
                </select>
            @endif

            <select wire:model.live="statusFilter" class="rounded-lg border-gray-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500">
                <option value="">All Statuses</option>
                <option value="draft">Draft</option>
                <option value="submitted">Pending</option>
                <option value="approved">Approved</option>
                <option value="rejected">Rejected</option>
            </select>

            <select wire:model.live="sectionFilter" class="rounded-lg border-gray-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500">
                <option value="">All Sections</option>
                @foreach ($sections as $s)
                    <option value="{{ $s->id }}">{{ $s->name }}</option>
                @endforeach
            </select>

            <select wire:model.live="employmentStatusFilter" class="rounded-lg border-gray-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500">
                <option value="">All Employment</option>
                <option value="exclude_outsourcing">All Exclude Outsourcing</option>
                @foreach (\App\Models\Employee::EMPLOYMENT_STATUSES as $esValue => $esLabel)
                    <option value="{{ $esValue }}">{{ $esLabel }}</option>
                @endforeach
                <option value="none">No Status</option>
            </select>

            <select wire:model.live="employeeFilter" class="rounded-lg border-gray-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500">
                <option value="">All Employees</option>
                @foreach ($allEmployees as $emp)
                    <option value="{{ $emp->id }}">{{ $emp->name }}@if ($emp->section) — {{ $emp->section->name }}@endif@unless ($emp->is_active) (inactive)@endunless</option>
                @endforeach
            </select>

            <div class="flex items-center gap-2">
                <span class="text-sm text-gray-500">From</span>
                <input type="date" wire:model.live="dateFrom" max="{{ date('Y-m-d') }}" class="rounded-lg border-gray-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500" />
                <span class="text-sm text-gray-500">To</span>
                <input type="date" wire:model.live="dateTo" max="{{ date('Y-m-d') }}" class="rounded-lg border-gray-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500" />
            </div>
        </div>
    </div>

    {{-- Stats Section --}}
    <div class="card p-4 mb-6">
        {{-- Date Range Display --}}
        <div class="flex items-center justify-between mb-4 pb-3 border-b border-gray-100">
            <h3 class="text-sm font-semibold text-gray-700">OT Summary</h3>
            <p class="text-sm text-gray-500">
                Date Range: <span class="font-medium text-gray-700">{{ \Carbon\Carbon::parse($statsDateFrom)->format('d M Y') }}</span>
                to <span class="font-medium text-gray-700">{{ \Carbon\Carbon::parse($statsDateTo)->format('d M Y') }}</span>
            </p>
        </div>

        {{-- Stats Cards --}}
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            {{-- Card 1: Total OT Submitted --}}
            <div class="bg-gray-50 rounded-lg border border-gray-200 p-4">
                <div class="flex items-center justify-between mb-3">
                    <p class="text-xs text-gray-500 uppercase tracking-wider font-medium">Total OT Submitted</p>
                    <p class="text-lg font-bold text-gray-800">{{ number_format($totalSubmittedHours, 1) }} hrs</p>
                </div>
                @if($sectionStats->isNotEmpty())
                <div class="space-y-2 border-t border-gray-200 pt-3">
                    @foreach($sectionStats as $stat)
                    <div class="flex items-center justify-between">
                        <span class="text-xs text-gray-600">{{ $stat->section_name }}</span>
                        <span class="text-sm font-semibold text-gray-800">{{ number_format($stat->total_hours, 1) }}</span>
                    </div>
                    @endforeach
                </div>
                @else
                <p class="text-xs text-gray-600 italic">No data</p>
                @endif
            </div>

            {{-- Card 2: Approved OT --}}
            <div class="bg-success-50 rounded-lg border border-success-200 p-4">
                <div class="flex items-center justify-between mb-3">
                    <p class="text-xs text-success-600 uppercase tracking-wider font-medium">Approved OT</p>
                    <p class="text-lg font-bold text-success-600">{{ number_format($totalApprovedHours, 1) }} hrs</p>
                </div>
                @if($sectionStats->isNotEmpty())
                <div class="space-y-2 border-t border-success-200 pt-3">
                    @foreach($sectionStats as $stat)
                    <div class="flex items-center justify-between">
                        <span class="text-xs text-gray-600">{{ $stat->section_name }}</span>
                        <span class="text-sm font-semibold text-success-600">{{ number_format($stat->approved_hours, 1) }}</span>
                    </div>
                    @endforeach
                </div>
                @else
                <p class="text-xs text-gray-600 italic">No data</p>
                @endif
            </div>

            {{-- Card 3: Pending Approval --}}
            <div class="bg-warning-50 rounded-lg border border-warning-200 p-4">
                <div class="flex items-center justify-between mb-3">
                    <p class="text-xs text-warning-600 uppercase tracking-wider font-medium">Pending Approval</p>
                    <p class="text-lg font-bold text-warning-600">{{ number_format($totalPendingHours, 1) }} hrs</p>
                </div>
                @if($sectionStats->isNotEmpty())
                <div class="space-y-2 border-t border-warning-200 pt-3">
                    @foreach($sectionStats as $stat)
                    <div class="flex items-center justify-between">
                        <span class="text-xs text-gray-600">{{ $stat->section_name }}</span>
                        <span class="text-sm font-semibold text-warning-600">{{ number_format($stat->pending_hours, 1) }}</span>
                    </div>
                    @endforeach
                </div>
                @else
                <p class="text-xs text-gray-600 italic">No data</p>
                @endif
            </div>

            {{-- Card 4: Rejected OT --}}
            <div class="bg-danger-50 rounded-lg border border-danger-200 p-4">
                <div class="flex items-center justify-between mb-3">
                    <p class="text-xs text-danger-600 uppercase tracking-wider font-medium">Rejected OT</p>
                    <p class="text-lg font-bold text-danger-600">{{ number_format($totalRejectedHours, 1) }} hrs</p>
                </div>
                @if($sectionStats->isNotEmpty())
                <div class="space-y-2 border-t border-danger-200 pt-3">
                    @foreach($sectionStats as $stat)
                    <div class="flex items-center justify-between">
                        <span class="text-xs text-gray-600">{{ $stat->section_name }}</span>
                        <span class="text-sm font-semibold text-danger-600">{{ number_format($stat->rejected_hours, 1) }}</span>
                    </div>
                    @endforeach
                </div>
                @else
                <p class="text-xs text-gray-600 italic">No data</p>
                @endif
            </div>
        </div>
    </div>

    {{-- ── Overtime Trend Chart ─────────────────────────────────────────────── --}}
    @once
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
    @endonce

    <div class="card p-5 mb-5">
        {{-- Card header --}}
        <div class="flex items-center justify-between mb-4">
            <div>
                <h2 class="text-sm font-semibold text-gray-800">Overtime Trend — Last 12 Weeks</h2>
                <p class="text-xs text-gray-600 mt-0.5">
                    Approved OT hours only, by type
                    @if ($sectionFilter && ($sn = $sections->firstWhere('id', (int) $sectionFilter)?->name))
                        · <span class="text-brand-500 font-medium">{{ $sn }}</span>
                    @endif
                    @if ($employeeFilter && ($en = $allEmployees->firstWhere('id', (int) $employeeFilter)?->name))
                        · <span class="text-brand-500 font-medium">{{ $en }}</span>
                    @endif
                </p>
            </div>
            {{-- Mini stats --}}
            <div class="flex items-center gap-5">
                <div class="text-right">
                    <p class="text-xs text-gray-600">This Week</p>
                    <p class="text-sm font-bold text-gray-800 tabular-nums">{{ number_format($thisWeekHours, 1) }} hrs</p>
                    @if ($wowChange !== null)
                        <p class="text-xs font-medium {{ $wowChange > 0 ? 'text-danger-500' : 'text-success-600' }}">
                            {{ $wowChange > 0 ? '↑' : '↓' }} {{ abs($wowChange) }}% vs last week
                        </p>
                    @endif
                </div>
                <div class="text-right">
                    <p class="text-xs text-gray-600">12-wk Avg / Week</p>
                    <p class="text-sm font-bold text-gray-800 tabular-nums">{{ number_format($avgWeekHours, 1) }} hrs</p>
                </div>
                @if ($peakWeekLabel)
                    <div class="text-right">
                        <p class="text-xs text-gray-600">Peak Week</p>
                        <p class="text-sm font-bold text-warning-600 tabular-nums">{{ number_format($peakWeekHours, 1) }} hrs</p>
                        <p class="text-xs text-gray-600">w/c {{ $peakWeekLabel }}</p>
                    </div>
                @endif
            </div>
        </div>

        {{-- Chart. Keyed by the data so Livewire replaces this block (and
             Alpine re-runs init) whenever a filter changes — a morphed canvas
             would otherwise keep the stale, now-dead Chart.js instance. --}}
        <div class="relative h-56" wire:key="ot-trend-{{ md5(json_encode($trendChartData)) }}"
             x-data="{
                chartInstance: null,
                init() {
                    const data = @js($trendChartData);
                    const ctx = this.$refs.canvas.getContext('2d');
                    this.chartInstance = new Chart(ctx, {
                        type: 'bar',
                        data: {
                            labels: data.labels,
                            datasets: [
                                {
                                    label: 'Normal Day',
                                    data: data.normal,
                                    backgroundColor: 'rgba(34,161,157,0.75)',
                                    borderRadius: 3,
                                    stack: 'ot',
                                },
                                {
                                    label: 'Rest Day',
                                    data: data.rest,
                                    backgroundColor: 'rgba(251,146,60,0.75)',
                                    borderRadius: 3,
                                    stack: 'ot',
                                },
                                {
                                    label: 'Public Holiday',
                                    data: data.holiday,
                                    backgroundColor: 'rgba(239,68,68,0.75)',
                                    borderRadius: 3,
                                    stack: 'ot',
                                },
                            ],
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    position: 'bottom',
                                    labels: { boxWidth: 12, padding: 16, font: { size: 11 } },
                                },
                                tooltip: {
                                    callbacks: {
                                        footer(items) {
                                            const total = items.reduce((s, i) => s + i.parsed.y, 0);
                                            return total > 0 ? 'Total: ' + total.toFixed(1) + ' hrs' : '';
                                        }
                                    }
                                }
                            },
                            scales: {
                                x: {
                                    stacked: true,
                                    grid: { display: false },
                                    ticks: { font: { size: 10 }, maxRotation: 45 },
                                },
                                y: {
                                    stacked: true,
                                    beginAtZero: true,
                                    grid: { color: 'rgba(0,0,0,0.05)' },
                                    ticks: {
                                        font: { size: 10 },
                                        callback: v => v + ' hrs',
                                    },
                                },
                            },
                        },
                    });
                },
             }"
             x-init="init()">
            <canvas x-ref="canvas"></canvas>
        </div>

        {{-- Top employees by date range, one column per section --}}
        @if ($topBySection->isNotEmpty())
            <div class="mt-5 border-t border-gray-100 pt-4">
                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">
                    Top OT by Section — {{ \Carbon\Carbon::parse($statsDateFrom)->format('d M Y') }} to {{ \Carbon\Carbon::parse($statsDateTo)->format('d M Y') }}
                </p>
                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-x-8 gap-y-5">
                    @foreach ($topBySection as $sectionName => $rows)
                        <div wire:key="top-ot-{{ md5($sectionName) }}">
                            <p class="text-xs font-semibold text-brand-600 mb-2">{{ $sectionName }}</p>
                            @php $maxHours = $rows->max('hours'); @endphp
                            <div class="space-y-2">
                                @foreach ($rows as $rank => $row)
                                    @php $pct = $maxHours > 0 ? ($row->hours / $maxHours * 100) : 0; @endphp
                                    <div class="flex items-center gap-2">
                                        <span class="text-xs text-gray-600 w-4 text-right">{{ $rank + 1 }}</span>
                                        <span class="text-xs font-medium text-gray-700 w-32 truncate" title="{{ $row->employee?->name }}">{{ $row->employee?->name ?? '—' }}</span>
                                        <div class="flex-1 bg-gray-100 rounded-full h-2">
                                            <div class="h-2 rounded-full {{ $pct >= 80 ? 'bg-danger-400' : ($pct >= 50 ? 'bg-warning-400' : 'bg-brand-400') }}"
                                                 style="width: {{ number_format($pct, 1) }}%"></div>
                                        </div>
                                        <span class="text-xs font-semibold tabular-nums text-gray-700 w-14 text-right">{{ number_format($row->hours, 1) }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    {{-- Bulk Actions Bar --}}
    @if (count($selected) > 0 && $isApprover)
        <div class="bg-brand-50 border border-brand-200 rounded-lg px-4 py-3 mb-4 flex items-center justify-between">
            <span class="text-sm text-brand-700 font-medium">{{ count($selected) }} claim(s) selected</span>
            <div class="flex items-center gap-2">
                <button wire:click="bulkApprove" wire:confirm="Approve {{ count($selected) }} selected claim(s)?"
                        class="px-3 py-1.5 bg-success-600 text-white text-xs font-medium rounded-lg hover:bg-success-700 transition">
                    Approve Selected
                </button>
                <button wire:click="openBulkReject"
                        class="btn-danger btn-sm">
                    Reject Selected
                </button>
                <button wire:click="$set('selected', [])"
                        class="px-3 py-1.5 text-gray-600 text-xs font-medium hover:text-gray-800 transition">
                    Clear
                </button>
            </div>
        </div>
    @endif

    {{-- Table — horizontally scrollable on mobile so every column stays reachable. --}}
    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table-surface min-w-[960px]">
                <thead>
                    <tr>
                        @if ($isApprover)
                            <th class="px-3 py-3 text-center w-8">
                                <input type="checkbox"
                                    x-data
                                    x-on:change="
                                        const checkboxes = document.querySelectorAll('.claim-checkbox');
                                        checkboxes.forEach(cb => { cb.checked = $event.target.checked; cb.dispatchEvent(new Event('change')); });
                                    "
                                    class="rounded border-gray-300 text-brand-600 focus:ring-brand-500" />
                            </th>
                        @endif
                        <th class="px-4 py-3 text-left cursor-pointer hover:text-gray-700" wire:click="sortBy('claim_date')">
                            Date
                            @if ($sortField === 'claim_date')
                                <span class="ml-0.5">{{ $sortDirection === 'asc' ? '▲' : '▼' }}</span>
                            @endif
                        </th>
                        <th class="px-4 py-3 text-left cursor-pointer hover:text-gray-700" wire:click="sortBy('employee')">
                            Employee
                            @if ($sortField === 'employee')
                                <span class="ml-0.5">{{ $sortDirection === 'asc' ? '▲' : '▼' }}</span>
                            @endif
                        </th>
                        <th class="px-4 py-3 text-center">Time</th>
                        <th class="px-4 py-3 text-center cursor-pointer hover:text-gray-700" wire:click="sortBy('total_ot_hours')">
                            Hours
                            @if ($sortField === 'total_ot_hours')
                                <span class="ml-0.5">{{ $sortDirection === 'asc' ? '▲' : '▼' }}</span>
                            @endif
                        </th>
                        <th class="px-4 py-3 text-center">Type</th>
                        <th class="px-4 py-3 text-left">Reason</th>
                        <th class="px-4 py-3 text-center">Status</th>
                        <th class="px-4 py-3 text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($claims as $claim)
                        <tr wire:key="claim-{{ $claim->id }}" class="hover:bg-gray-50 transition">
                            @if ($isApprover)
                                <td class="px-3 py-3 text-center">
                                    @if ($claim->status === 'submitted')
                                        <input type="checkbox" value="{{ $claim->id }}" wire:model.live="selected"
                                               class="claim-checkbox rounded border-gray-300 text-brand-600 focus:ring-brand-500" />
                                    @endif
                                </td>
                            @endif
                            <td class="px-4 py-3 text-gray-700 font-medium whitespace-nowrap">
                                {{ $claim->claim_date->format('d M Y') }}
                                <span class="text-gray-600 font-normal">({{ $claim->claim_date->format('D') }})</span>
                                @foreach (\App\Models\CalendarEvent::onDate($calendarEvents, $claim->claim_date, $claim->employee?->outlet_id) as $ev)
                                    <span class="block mt-0.5 px-1.5 py-0.5 text-[10px] font-medium rounded
                                        {{ $ev->category === 'holiday' ? 'bg-danger-50 text-danger-600' : 'bg-brand-50 text-brand-600' }}"
                                          title="{{ $ev->categoryLabel() }}{{ $ev->description ? ' — ' . $ev->description : '' }}">
                                        {{ $ev->title }}
                                    </span>
                                @endforeach
                            </td>
                            <td class="px-4 py-3 text-gray-700">
                                {{ $claim->employee?->name ?? '—' }}
                                {{-- Who raised it AND when. A claim disputed
                                     weeks later is argued over the timing as
                                     much as the hours — "this went in after the
                                     shift had already been paid" — and a name
                                     with no time cannot answer that. --}}
                                <p class="text-[10px] text-gray-600">
                                    by {{ $claim->submitter?->name ?? 'unknown' }}
                                    @if ($claim->created_at)
                                        on {{ $claim->created_at->format('d M Y, g:ia') }}
                                    @endif
                                </p>
                            </td>
                            <td class="px-4 py-3 text-center text-gray-600 whitespace-nowrap">
                                {{ substr($claim->ot_time_start, 0, 5) }} – {{ substr($claim->ot_time_end, 0, 5) }}
                            </td>
                            <td class="px-4 py-3 text-center font-semibold text-gray-800">
                                {{ number_format($claim->total_ot_hours, 1) }}h
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span class="px-2 py-0.5 text-[10px] font-medium rounded-full
                                    {{ match($claim->ot_type) {
                                        'public_holiday' => 'bg-danger-50 text-danger-600',
                                        'rest_day'       => 'bg-warning-50 text-warning-600',
                                        default          => 'bg-gray-100 text-gray-600',
                                    } }}">
                                    {{ $claim->otTypeLabel() }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-gray-600 max-w-[200px] truncate" title="{{ $claim->reason }}">
                                {{ $claim->reason }}
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span class="px-2 py-0.5 text-[10px] font-medium rounded-full
                                    {{ match($claim->status) {
                                        'draft'     => 'bg-gray-100 text-gray-600',
                                        'submitted' => 'bg-warning-100 text-warning-700',
                                        'approved'  => 'bg-success-100 text-success-700',
                                        'rejected'  => 'bg-danger-100 text-danger-600',
                                        default     => 'bg-gray-100 text-gray-500',
                                    } }}">
                                    {{ $claim->status === 'submitted' ? 'Pending' : ucfirst($claim->status) }}
                                </span>
                                {{-- "Approved" alone does not say whether this becomes money or
                                     hours, and the two are settled in completely different
                                     places. Only shown for time off: payroll is the norm and a
                                     badge on every ordinary row would be noise. --}}
                                @if ($claim->status === 'approved' && $claim->isTimeOff())
                                    <span class="mt-0.5 block px-2 py-0.5 text-[10px] font-medium rounded-full bg-brand-50 text-brand-800">
                                        Time Off — not paid
                                    </span>
                                @endif
                                @if ($claim->status === 'rejected' && $claim->rejected_reason)
                                    <p class="text-[10px] text-danger-400 mt-0.5">{{ Str::limit($claim->rejected_reason, 30) }}</p>
                                @endif
                                @if ($claim->status === 'approved' && $claim->approver)
                                    <p class="text-[10px] text-gray-600 mt-0.5">by {{ $claim->approver->name }}</p>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-center">
                                <div class="flex items-center justify-center gap-1">
                                    @if ($claim->status === 'draft')
                                        <button wire:click="openEdit({{ $claim->id }})" class="text-brand-500 hover:text-brand-700 text-xs font-medium">Edit</button>
                                        <button wire:click="submitClaim({{ $claim->id }})" class="text-blue-500 hover:text-blue-700 text-xs font-medium">Submit</button>
                                    @endif
                                    @if ($claim->status === 'submitted' && ($canApproveMap[$claim->id] ?? false))
                                        @php $otIsTimeOff = (bool) $claim->employee?->overtime_as_time_off; @endphp

                                        {{-- Plain Approve settles on the employee's own terms,
                                             so somebody on time-off terms cannot be paid in cash
                                             by an approver who did not know. The label says which
                                             it will be rather than leaving it to be discovered on
                                             a payslip. --}}
                                        <button wire:click="approveClaim({{ $claim->id }})"
                                                class="text-success-600 hover:text-success-800 text-xs font-medium">
                                            {{ $otIsTimeOff ? 'Approve (time off)' : 'Approve' }}
                                        </button>

                                        {{-- Redundant for somebody already on time-off terms, so
                                             it is simply absent there rather than a second button
                                             that does the same thing. --}}
                                        @unless ($otIsTimeOff)
                                            <button wire:click="approveClaimAsTimeOff({{ $claim->id }})"
                                                    wire:confirm="Approve as time off? These hours go to {{ addslashes($claim->employee?->name ?? 'this employee') }}'s time-off balance and will NOT be paid in payroll."
                                                    class="text-brand-600 hover:text-brand-800 text-xs font-medium">
                                                Approve as Time Off
                                            </button>
                                        @endunless

                                        <button wire:click="openReject({{ $claim->id }})" class="text-danger-500 hover:text-danger-700 text-xs font-medium">Reject</button>
                                    @endif
                                    @if (in_array($claim->status, ['draft', 'rejected']) || $canDeleteAny)
                                        <button wire:click="deleteClaim({{ $claim->id }})"
                                                data-confirm-delete="Delete this OT claim? This cannot be undone."
                                                class="text-danger-400 hover:text-danger-600 text-xs font-medium">Delete</button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $isApprover ? 9 : 8 }}" class="px-4 py-12 text-center text-gray-600">
                                <p class="text-2xl mb-2">&#128337;</p>
                                <p class="font-medium">No overtime claims found</p>
                                <p class="text-xs mt-1">Click "+ New OT Claim" to submit one.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($claims->hasPages())
            <div class="px-4 py-3 border-t border-gray-100">
                {{ $claims->links() }}
            </div>
        @endif
    </div>

    {{-- Create/Edit Modal --}}
    @if ($showModal)
        @teleport('body')
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40" wire:click.self="$set('showModal', false)">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-lg mx-4 p-6">
                <h3 class="text-base font-semibold text-gray-800 mb-4">
                    {{ $editingId ? 'Edit OT Claim' : 'New OT Claim' }}
                </h3>

                <div class="space-y-4">
                    {{-- Employee --}}
                    <div>
                        <x-input-label for="ot_employee" value="Employee *" />
                        <select id="ot_employee" wire:model="employee_id"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-brand-500 focus:ring-brand-500">
                            <option value="">— Select Employee —</option>
                            @foreach ($employees as $emp)
                                <option value="{{ $emp->id }}">{{ $emp->name }}@if($emp->designation) — {{ $emp->designation }}@endif@if($emp->section) · {{ $emp->section->name }}@endif</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('employee_id')" class="mt-1" />
                    </div>

                    {{-- Date --}}
                    <div>
                        <x-input-label for="ot_date" value="Date *" />
                        <x-text-input id="ot_date" wire:model="claim_date" type="date" class="mt-1 block w-full" max="{{ date('Y-m-d') }}" />
                        <x-input-error :messages="$errors->get('claim_date')" class="mt-1" />
                    </div>

                    {{-- Time Start / End --}}
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="ot_start" value="OT Start *" />
                            <x-text-input id="ot_start" wire:model.live="ot_time_start" type="time" class="mt-1 block w-full" />
                            <x-input-error :messages="$errors->get('ot_time_start')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="ot_end" value="OT End *" />
                            <x-text-input id="ot_end" wire:model.live="ot_time_end" type="time" class="mt-1 block w-full" />
                            <x-input-error :messages="$errors->get('ot_time_end')" class="mt-1" />
                        </div>
                    </div>

                    {{-- Total Hours --}}
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="ot_hours" value="Total OT Hours *" />
                            <x-text-input id="ot_hours" wire:model="total_ot_hours" type="number" step="0.25" min="0.25" max="24"
                                          class="mt-1 block w-full" />
                            <p class="text-[10px] text-gray-600 mt-0.5">Auto-calculated, editable override</p>
                            <x-input-error :messages="$errors->get('total_ot_hours')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="ot_type" value="OT Type *" />
                            <select id="ot_type" wire:model="ot_type"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-brand-500 focus:ring-brand-500">
                                <option value="normal_day">Normal Day</option>
                                <option value="public_holiday">Public Holiday</option>
                                <option value="rest_day">Rest Day</option>
                            </select>
                            <x-input-error :messages="$errors->get('ot_type')" class="mt-1" />
                        </div>
                    </div>

                    {{-- Reason --}}
                    <div>
                        <x-input-label for="ot_reason" value="Reason for OT *" />
                        <textarea id="ot_reason" wire:model="reason" rows="3"
                                  class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-brand-500 focus:ring-brand-500"
                                  placeholder="Describe the reason for overtime..."></textarea>
                        <x-input-error :messages="$errors->get('reason')" class="mt-1" />
                    </div>

                    {{-- Recent activity (edit only) — bottom of form --}}
                    <x-audit-timeline :type="\App\Models\OvertimeClaim::class" :id="$editingId" title="Claim Activity" />
                </div>

                <div class="flex justify-end gap-2 mt-6">
                    <button wire:click="$set('showModal', false)"
                            class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800 transition">
                        Cancel
                    </button>
                    <button wire:click="save('submit')"
                            class="px-4 py-2 border border-blue-500 text-blue-600 text-sm font-medium rounded-lg hover:bg-blue-50 transition">
                        Save & Submit
                    </button>
                    <button wire:click="save"
                            class="btn-primary">
                        Save Draft
                    </button>
                </div>
            </div>
        </div>
        @endteleport
    @endif

    {{-- Reject Modal --}}
    @if ($showRejectModal)
        @teleport('body')
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40" wire:click.self="$set('showRejectModal', false)">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-md mx-4 p-6">
                <h3 class="text-base font-semibold text-gray-800 mb-4">Reject OT Claim</h3>
                <div>
                    <x-input-label for="reject_reason" value="Reason for Rejection *" />
                    <textarea id="reject_reason" wire:model="rejected_reason" rows="3"
                              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-brand-500 focus:ring-brand-500"
                              placeholder="Explain why this claim is being rejected..."></textarea>
                    <x-input-error :messages="$errors->get('rejected_reason')" class="mt-1" />
                </div>
                <div class="flex justify-end gap-2 mt-4">
                    <button wire:click="$set('showRejectModal', false)"
                            class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800 transition">
                        Cancel
                    </button>
                    <button wire:click="rejectClaim"
                            class="btn-danger">
                        Reject Claim
                    </button>
                </div>
            </div>
        </div>
        @endteleport
    @endif

    {{-- Bulk Reject Modal --}}
    @if ($showBulkRejectModal)
        @teleport('body')
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40" wire:click.self="$set('showBulkRejectModal', false)">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-md mx-4 p-6">
                <h3 class="text-base font-semibold text-gray-800 mb-4">Reject {{ count($selected) }} Claim(s)</h3>
                <div>
                    <x-input-label for="bulk_reject_reason" value="Reason for Rejection *" />
                    <textarea id="bulk_reject_reason" wire:model="bulk_rejected_reason" rows="3"
                              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-brand-500 focus:ring-brand-500"
                              placeholder="This reason will be applied to all selected claims..."></textarea>
                    <x-input-error :messages="$errors->get('bulk_rejected_reason')" class="mt-1" />
                </div>
                <div class="flex justify-end gap-2 mt-4">
                    <button wire:click="$set('showBulkRejectModal', false)"
                            class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800 transition">
                        Cancel
                    </button>
                    <button wire:click="bulkReject"
                            class="btn-danger">
                        Reject All Selected
                    </button>
                </div>
            </div>
        </div>
        @endteleport
    @endif

    {{-- Employee Add/Edit Modal --}}
    @if ($showEmployeeModal)
        @teleport('body')
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40" wire:click.self="$set('showEmployeeModal', false)">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-md mx-4 p-6">
                <h3 class="text-base font-semibold text-gray-800 mb-4">
                    {{ $editingEmployeeId ? 'Edit Employee' : 'Add Employee' }}
                </h3>

                <div class="space-y-3">
                    <div>
                        <x-input-label for="emp_name" value="Name *" />
                        <x-text-input id="emp_name" wire:model="emp_name" type="text" class="mt-1 block w-full" placeholder="Employee name" />
                        <x-input-error :messages="$errors->get('emp_name')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="emp_designation" value="Designation" />
                        <x-text-input id="emp_designation" wire:model="emp_designation" type="text" class="mt-1 block w-full" placeholder="e.g. Kitchen Helper, Waiter" />
                        <x-input-error :messages="$errors->get('emp_designation')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="emp_section_id" value="Section" />
                        <select id="emp_section_id" wire:model="emp_section_id"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-brand-500 focus:ring-brand-500">
                            <option value="">— None —</option>
                            @foreach ($sections as $s)
                                <option value="{{ $s->id }}">{{ $s->name }}</option>
                            @endforeach
                        </select>
                        <p class="text-[10px] text-gray-600 mt-1">
                            Manage the list at <a href="{{ route('settings.sections') }}" class="text-brand-600 hover:underline">Settings → Sections</a>.
                        </p>
                        <x-input-error :messages="$errors->get('emp_section_id')" class="mt-1" />
                    </div>
                </div>

                <div class="flex justify-end gap-2 mt-5">
                    <button wire:click="$set('showEmployeeModal', false)"
                            class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800 transition">
                        Cancel
                    </button>
                    <button wire:click="saveEmployee"
                            class="btn-primary">
                        {{ $editingEmployeeId ? 'Update' : 'Add Employee' }}
                    </button>
                </div>
            </div>
        </div>
        @endteleport
    @endif

    {{-- PDF Print Modal --}}
    @if ($showPdfModal)
        @teleport('body')
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40" wire:click.self="$set('showPdfModal', false)"
             x-data="{ empId: '{{ $pdfEmployeeId }}', fromDate: '{{ $pdfFrom }}', toDate: '{{ $pdfTo }}', outletId: '{{ $outletFilter }}' }">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-md mx-4 p-6">
                <h3 class="text-base font-semibold text-gray-800 mb-4">Print Approved OT Claims</h3>

                <div class="space-y-4">
                    <div>
                        <x-input-label for="pdf_employee" value="Employee" />
                        <select id="pdf_employee" x-model="empId"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-brand-500 focus:ring-brand-500">
                            <option value="">All Employees</option>
                            @foreach ($allEmployees->where('is_active', true) as $emp)
                                <option value="{{ $emp->id }}">{{ $emp->name }}</option>
                            @endforeach
                        </select>
                        <p class="text-[10px] text-gray-600 mt-0.5">Leave blank to print all employees (one page each)</p>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="pdf_from" value="From Date *" />
                            <input id="pdf_from" x-model="fromDate" type="date"
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-brand-500 focus:ring-brand-500" />
                        </div>
                        <div>
                            <x-input-label for="pdf_to" value="To Date *" />
                            <input id="pdf_to" x-model="toDate" type="date"
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-brand-500 focus:ring-brand-500" />
                        </div>
                    </div>
                </div>

                <div class="flex justify-end gap-2 mt-6">
                    <button wire:click="$set('showPdfModal', false)"
                            class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800 transition">
                        Cancel
                    </button>
                    <a x-bind:href="'{{ url('/hr/overtime-claims/pdf') }}/' + (empId || 'all') + '?from=' + fromDate + '&to=' + toDate + (outletId ? '&outlet=' + outletId : '')"
                       target="_blank"
                       class="btn-primary">
                        Download PDF
                    </a>
                </div>
            </div>
        </div>
        @endteleport
    @endif

    {{-- Summary PDF Modal --}}
    @if ($showSummaryModal)
        @teleport('body')
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40"
             wire:click.self="$set('showSummaryModal', false)">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-md mx-4 p-6">
                <div class="flex items-center gap-2 mb-4">
                    <div class="p-1.5 bg-brand-50 rounded-lg">
                        <svg class="w-4 h-4 text-brand-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                    </div>
                    <h3 class="text-base font-semibold text-gray-800">OT Claims — Summary Report</h3>
                </div>
                <p class="text-xs text-gray-600 mb-4">All approved OT claims for the selected period, grouped by type with hours subtotals and grand total.</p>

                <div class="flex flex-wrap items-center gap-1.5 mb-4">
                    @foreach (\App\Livewire\Hr\OvertimeClaims::SUMMARY_PERIODS as $periodKey => $periodLabel)
                        <button type="button" wire:click="setSummaryPeriod('{{ $periodKey }}')"
                                class="px-3 py-1.5 text-xs font-medium rounded-lg border transition
                                       {{ $summaryPeriod === $periodKey
                                           ? 'bg-brand-600 text-white border-brand-600'
                                           : 'bg-white text-gray-600 border-gray-200 hover:bg-gray-50' }}">
                            {{ $periodLabel }}
                        </button>
                    @endforeach
                    @if ($summaryPeriod === 'custom')
                        <span class="px-3 py-1.5 text-xs font-medium rounded-lg border bg-brand-600 text-white border-brand-600">Custom</span>
                    @endif
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <x-input-label for="sum_from" value="From Date *" />
                        <input id="sum_from" wire:model.live="summaryFrom" type="date"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-brand-500 focus:ring-brand-500" />
                    </div>
                    <div>
                        <x-input-label for="sum_to" value="To Date *" />
                        <input id="sum_to" wire:model.live="summaryTo" type="date"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-brand-500 focus:ring-brand-500" />
                    </div>
                </div>

                <div class="flex justify-end gap-2 mt-6">
                    <button wire:click="$set('showSummaryModal', false)"
                            class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800 transition">
                        Cancel
                    </button>
                    <a href="{{ $this->getSummaryPdfUrl() }}" target="_blank"
                       @class(['btn-primary', 'pointer-events-none opacity-50' => ! $summaryFrom || ! $summaryTo])>
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        Download PDF
                    </a>
                </div>
            </div>
        </div>
        @endteleport
    @endif

    {{-- Employee list lives on the dedicated /hr/employees page (full screen, CSV import, filters).
         The old in-page modal was prone to being clipped on small laptops — removed. --}}
</div>
