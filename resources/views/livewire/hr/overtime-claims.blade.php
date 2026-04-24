<div>
    @if (session()->has('success'))
        <div wire:key="flash-{{ microtime(true) }}" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)"
             class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-700 text-sm rounded-lg">
            {{ session('success') }}
        </div>
    @endif
    @if (session()->has('error'))
        <div wire:key="flash-err-{{ microtime(true) }}" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)"
             class="mb-4 px-4 py-3 bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg">
            {{ session('error') }}
        </div>
    @endif

    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-lg font-bold text-gray-800">Overtime Claims</h1>
            <p class="text-xs text-gray-400 mt-0.5">Submit and manage staff overtime claims</p>
        </div>
        <div class="flex items-center gap-2">
            <button wire:click="openPdfModal"
                    class="px-3 py-2 border border-gray-300 text-gray-600 text-sm font-medium rounded-lg hover:bg-gray-50 transition">
                Print PDF
            </button>
            <a href="{{ route('hr.employees') }}"
               class="px-3 py-2 border border-gray-300 text-gray-600 text-sm font-medium rounded-lg hover:bg-gray-50 transition">
                Employee List
            </a>
            <button wire:click="openAddEmployee"
                    class="px-3 py-2 border border-gray-300 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-50 transition">
                + Add Employee
            </button>
            <button wire:click="openCreate"
                    class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
                + New OT Claim
            </button>
        </div>
    </div>

    {{-- Stats --}}
    <div class="grid grid-cols-3 gap-4 mb-6">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
            <p class="text-xs text-gray-400 uppercase tracking-wider">Approved Hours (Month)</p>
            <p class="text-2xl font-bold text-gray-800 mt-1">{{ number_format($totalHoursMonth, 1) }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
            <p class="text-xs text-gray-400 uppercase tracking-wider">Pending Approval</p>
            <p class="text-2xl font-bold text-amber-600 mt-1">{{ $pendingCount }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
            <p class="text-xs text-gray-400 uppercase tracking-wider">Approved (Month)</p>
            <p class="text-2xl font-bold text-green-600 mt-1">{{ $approvedCount }}</p>
        </div>
    </div>

    {{-- ── Overtime Trend Chart ─────────────────────────────────────────────── --}}
    @once
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
    @endonce

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 mb-5">
        {{-- Card header --}}
        <div class="flex items-center justify-between mb-4">
            <div>
                <h2 class="text-sm font-semibold text-gray-800">Overtime Trend — Last 12 Weeks</h2>
                <p class="text-xs text-gray-400 mt-0.5">Approved OT hours only, by type</p>
            </div>
            {{-- Mini stats --}}
            <div class="flex items-center gap-5">
                <div class="text-right">
                    <p class="text-xs text-gray-400">This Week</p>
                    <p class="text-sm font-bold text-gray-800 tabular-nums">{{ number_format($thisWeekHours, 1) }} hrs</p>
                    @if ($wowChange !== null)
                        <p class="text-xs font-medium {{ $wowChange > 0 ? 'text-red-500' : 'text-green-600' }}">
                            {{ $wowChange > 0 ? '↑' : '↓' }} {{ abs($wowChange) }}% vs last week
                        </p>
                    @endif
                </div>
                <div class="text-right">
                    <p class="text-xs text-gray-400">12-wk Avg / Week</p>
                    <p class="text-sm font-bold text-gray-800 tabular-nums">{{ number_format($avgWeekHours, 1) }} hrs</p>
                </div>
                @if ($peakWeekLabel)
                    <div class="text-right">
                        <p class="text-xs text-gray-400">Peak Week</p>
                        <p class="text-sm font-bold text-amber-600 tabular-nums">{{ number_format($peakWeekHours, 1) }} hrs</p>
                        <p class="text-xs text-gray-400">w/c {{ $peakWeekLabel }}</p>
                    </div>
                @endif
            </div>
        </div>

        {{-- Chart --}}
        <div class="relative h-56"
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
                                    backgroundColor: 'rgba(99,102,241,0.75)',
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

        {{-- Top employees this month --}}
        @if ($topEmployees->isNotEmpty())
            <div class="mt-5 border-t border-gray-100 pt-4">
                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">Top OT — {{ now()->format('F') }}</p>
                @php $maxHours = $topEmployees->max('hours'); @endphp
                <div class="space-y-2">
                    @foreach ($topEmployees as $rank => $row)
                        @php $pct = $maxHours > 0 ? ($row->hours / $maxHours * 100) : 0; @endphp
                        <div class="flex items-center gap-3">
                            <span class="text-xs text-gray-400 w-4 text-right">{{ $rank + 1 }}</span>
                            <span class="text-xs font-medium text-gray-700 w-32 truncate">{{ $row->employee?->name ?? '—' }}</span>
                            <div class="flex-1 bg-gray-100 rounded-full h-2">
                                <div class="h-2 rounded-full {{ $pct >= 80 ? 'bg-red-400' : ($pct >= 50 ? 'bg-amber-400' : 'bg-indigo-400') }}"
                                     style="width: {{ number_format($pct, 1) }}%"></div>
                            </div>
                            <span class="text-xs font-semibold tabular-nums text-gray-700 w-16 text-right">{{ number_format($row->hours, 1) }} hrs</span>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    {{-- Filters --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 mb-4">
        <div class="flex flex-wrap gap-3">
            <select wire:model.live="statusFilter" class="rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">All Statuses</option>
                <option value="draft">Draft</option>
                <option value="submitted">Pending</option>
                <option value="approved">Approved</option>
                <option value="rejected">Rejected</option>
            </select>
            <select wire:model.live="sectionFilter" class="rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">All Sections</option>
                @foreach ($sections as $s)
                    <option value="{{ $s->id }}">{{ $s->name }}</option>
                @endforeach
            </select>
            <select wire:model.live="employeeFilter" class="rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">All Employees</option>
                @foreach ($allEmployees as $emp)
                    <option value="{{ $emp->id }}">{{ $emp->name }}@if ($emp->section) — {{ $emp->section->name }}@endif</option>
                @endforeach
            </select>
            <input type="date" wire:model.live="dateFrom" class="rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="From" />
            <input type="date" wire:model.live="dateTo" class="rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="To" />
        </div>
    </div>

    {{-- Bulk Actions Bar --}}
    @if (count($selected) > 0 && $isApprover)
        <div class="bg-indigo-50 border border-indigo-200 rounded-lg px-4 py-3 mb-4 flex items-center justify-between">
            <span class="text-sm text-indigo-700 font-medium">{{ count($selected) }} claim(s) selected</span>
            <div class="flex items-center gap-2">
                <button wire:click="bulkApprove" wire:confirm="Approve {{ count($selected) }} selected claim(s)?"
                        class="px-3 py-1.5 bg-green-600 text-white text-xs font-medium rounded-lg hover:bg-green-700 transition">
                    Approve Selected
                </button>
                <button wire:click="openBulkReject"
                        class="px-3 py-1.5 bg-red-600 text-white text-xs font-medium rounded-lg hover:bg-red-700 transition">
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
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-[960px] w-full text-sm">
                <thead class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wider">
                    <tr>
                        @if ($isApprover)
                            <th class="px-3 py-3 text-center w-8">
                                <input type="checkbox"
                                    x-data
                                    x-on:change="
                                        const checkboxes = document.querySelectorAll('.claim-checkbox');
                                        checkboxes.forEach(cb => { cb.checked = $event.target.checked; cb.dispatchEvent(new Event('change')); });
                                    "
                                    class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
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
                <tbody class="divide-y divide-gray-100">
                    @forelse ($claims as $claim)
                        <tr wire:key="claim-{{ $claim->id }}" class="hover:bg-gray-50 transition">
                            @if ($isApprover)
                                <td class="px-3 py-3 text-center">
                                    @if ($claim->status === 'submitted')
                                        <input type="checkbox" value="{{ $claim->id }}" wire:model.live="selected"
                                               class="claim-checkbox rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                                    @endif
                                </td>
                            @endif
                            <td class="px-4 py-3 text-gray-700 font-medium whitespace-nowrap">
                                {{ $claim->claim_date->format('d M Y') }}
                            </td>
                            <td class="px-4 py-3 text-gray-700">
                                {{ $claim->employee?->name ?? '—' }}
                                <p class="text-[10px] text-gray-400">by {{ $claim->submitter?->name }}</p>
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
                                        'public_holiday' => 'bg-red-50 text-red-600',
                                        'rest_day'       => 'bg-amber-50 text-amber-600',
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
                                        'submitted' => 'bg-amber-100 text-amber-700',
                                        'approved'  => 'bg-green-100 text-green-700',
                                        'rejected'  => 'bg-red-100 text-red-600',
                                        default     => 'bg-gray-100 text-gray-500',
                                    } }}">
                                    {{ $claim->status === 'submitted' ? 'Pending' : ucfirst($claim->status) }}
                                </span>
                                @if ($claim->status === 'rejected' && $claim->rejected_reason)
                                    <p class="text-[10px] text-red-400 mt-0.5">{{ Str::limit($claim->rejected_reason, 30) }}</p>
                                @endif
                                @if ($claim->status === 'approved' && $claim->approver)
                                    <p class="text-[10px] text-gray-400 mt-0.5">by {{ $claim->approver->name }}</p>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-center">
                                <div class="flex items-center justify-center gap-1">
                                    @if ($claim->status === 'draft')
                                        <button wire:click="openEdit({{ $claim->id }})" class="text-indigo-500 hover:text-indigo-700 text-xs font-medium">Edit</button>
                                        <button wire:click="submitClaim({{ $claim->id }})" class="text-blue-500 hover:text-blue-700 text-xs font-medium">Submit</button>
                                    @endif
                                    @if ($claim->status === 'submitted' && ($canApproveMap[$claim->id] ?? false))
                                        <button wire:click="approveClaim({{ $claim->id }})" class="text-green-600 hover:text-green-800 text-xs font-medium">Approve</button>
                                        <button wire:click="openReject({{ $claim->id }})" class="text-red-500 hover:text-red-700 text-xs font-medium">Reject</button>
                                    @endif
                                    @if (in_array($claim->status, ['draft', 'rejected']) || $canDeleteAny)
                                        <button wire:click="deleteClaim({{ $claim->id }})"
                                                wire:confirm="Delete this OT claim? This cannot be undone."
                                                class="text-red-400 hover:text-red-600 text-xs font-medium">Delete</button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $isApprover ? 9 : 8 }}" class="px-4 py-12 text-center text-gray-400">
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
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
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
                        <x-text-input id="ot_date" wire:model="claim_date" type="date" class="mt-1 block w-full" />
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
                            <p class="text-[10px] text-gray-400 mt-0.5">Auto-calculated, editable override</p>
                            <x-input-error :messages="$errors->get('total_ot_hours')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="ot_type" value="OT Type *" />
                            <select id="ot_type" wire:model="ot_type"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
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
                                  class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                  placeholder="Describe the reason for overtime..."></textarea>
                        <x-input-error :messages="$errors->get('reason')" class="mt-1" />
                    </div>
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
                            class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
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
                              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
                              placeholder="Explain why this claim is being rejected..."></textarea>
                    <x-input-error :messages="$errors->get('rejected_reason')" class="mt-1" />
                </div>
                <div class="flex justify-end gap-2 mt-4">
                    <button wire:click="$set('showRejectModal', false)"
                            class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800 transition">
                        Cancel
                    </button>
                    <button wire:click="rejectClaim"
                            class="px-4 py-2 bg-red-600 text-white text-sm font-medium rounded-lg hover:bg-red-700 transition">
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
                              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
                              placeholder="This reason will be applied to all selected claims..."></textarea>
                    <x-input-error :messages="$errors->get('bulk_rejected_reason')" class="mt-1" />
                </div>
                <div class="flex justify-end gap-2 mt-4">
                    <button wire:click="$set('showBulkRejectModal', false)"
                            class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800 transition">
                        Cancel
                    </button>
                    <button wire:click="bulkReject"
                            class="px-4 py-2 bg-red-600 text-white text-sm font-medium rounded-lg hover:bg-red-700 transition">
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
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">— None —</option>
                            @foreach ($sections as $s)
                                <option value="{{ $s->id }}">{{ $s->name }}</option>
                            @endforeach
                        </select>
                        <p class="text-[10px] text-gray-400 mt-1">
                            Manage the list at <a href="{{ route('settings.sections') }}" class="text-indigo-600 hover:underline">Settings → Sections</a>.
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
                            class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
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
             x-data="{ empId: '{{ $pdfEmployeeId }}', fromDate: '{{ $pdfFrom }}', toDate: '{{ $pdfTo }}' }">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-md mx-4 p-6">
                <h3 class="text-base font-semibold text-gray-800 mb-4">Print Approved OT Claims</h3>

                <div class="space-y-4">
                    <div>
                        <x-input-label for="pdf_employee" value="Employee" />
                        <select id="pdf_employee" x-model="empId"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">All Employees</option>
                            @foreach ($allEmployees->where('is_active', true) as $emp)
                                <option value="{{ $emp->id }}">{{ $emp->name }}</option>
                            @endforeach
                        </select>
                        <p class="text-[10px] text-gray-400 mt-0.5">Leave blank to print all employees (one page each)</p>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="pdf_from" value="From Date *" />
                            <input id="pdf_from" x-model="fromDate" type="date"
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500" />
                        </div>
                        <div>
                            <x-input-label for="pdf_to" value="To Date *" />
                            <input id="pdf_to" x-model="toDate" type="date"
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500" />
                        </div>
                    </div>
                </div>

                <div class="flex justify-end gap-2 mt-6">
                    <button wire:click="$set('showPdfModal', false)"
                            class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800 transition">
                        Cancel
                    </button>
                    <a x-bind:href="'{{ url('/hr/overtime-claims/pdf') }}/' + (empId || 'all') + '?from=' + fromDate + '&to=' + toDate"
                       target="_blank"
                       class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition inline-flex items-center">
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
