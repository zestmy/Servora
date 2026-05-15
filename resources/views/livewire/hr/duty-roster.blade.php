<div>
    @once
        <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.6/Sortable.min.js"></script>
    @endonce

    @if (session()->has('success'))
        <div wire:key="flash-{{ microtime(true) }}" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)"
             class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-700 text-sm rounded-lg">
            {{ session('success') }}
        </div>
    @endif
    @if (session()->has('error'))
        <div class="mb-4 px-4 py-3 bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg">
            {{ session('error') }}
        </div>
    @endif

    {{-- Header --}}
    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        <div>
            <p class="text-xs text-gray-400">HR / Duty Roster</p>
            <h2 class="text-lg font-semibold text-gray-700 mt-1">Duty Roster</h2>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('hr.roster-stations') }}"
               class="px-3 py-2 text-sm font-medium text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                Stations
            </a>
            <a href="{{ route('hr.roster-approvers') }}"
               class="px-3 py-2 text-sm font-medium text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                Approvers
            </a>
            <a href="{{ route('hr.roster-email-recipients') }}"
               class="px-3 py-2 text-sm font-medium text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                Email Recipients
            </a>
        </div>
    </div>

    {{-- Filter Bar --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 mb-4">
        <div class="flex flex-wrap items-center gap-4">
            {{-- Outlet --}}
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Outlet</label>
                <select wire:model.live="outletId" class="text-sm rounded-lg border-gray-300 shadow-sm min-w-[180px]">
                    <option value="">Select outlet...</option>
                    @foreach ($outlets as $outlet)
                        <option value="{{ $outlet->id }}">{{ $outlet->name }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Section (BOH/FOH) --}}
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Section</label>
                <select wire:model.live="sectionId" class="text-sm rounded-lg border-gray-300 shadow-sm min-w-[140px]">
                    <option value="">All Sections</option>
                    @foreach ($sections as $section)
                        <option value="{{ $section->id }}">{{ $section->name }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Week Navigation --}}
            <div class="flex-1"></div>
            <div class="flex items-center gap-2">
                <button wire:click="previousWeek"
                        class="p-2 text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-lg transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                </button>
                <div class="text-sm font-medium text-gray-700 min-w-[180px] text-center">
                    {{ $periodLabel }}
                </div>
                <button wire:click="nextWeek"
                        class="p-2 text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-lg transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    @if ($outletId)
        @if ($roster)
            {{-- Status Bar --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 mb-4">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div class="flex items-center gap-4">
                        <div class="flex items-center gap-2">
                            <span class="text-sm text-gray-500">Status:</span>
                            <span class="inline-flex items-center px-3 py-1 text-sm font-medium rounded-full
                                @if ($roster->isDraft()) bg-gray-100 text-gray-700
                                @elseif ($roster->isSubmitted()) bg-yellow-100 text-yellow-700
                                @elseif ($roster->isApproved()) bg-green-100 text-green-700
                                @elseif ($roster->isRejected()) bg-red-100 text-red-700
                                @endif">
                                {{ $roster->status_label }}
                            </span>
                        </div>
                        @if ($roster->revision > 1)
                            <span class="text-xs text-gray-500">
                                Rev {{ $roster->revision }}
                                @if ($roster->revision_notes)
                                    <span title="{{ $roster->revision_notes }}" class="cursor-help">ⓘ</span>
                                @endif
                            </span>
                        @endif
                        @if ($roster->creator)
                            <span class="text-xs text-gray-400">
                                Created by {{ $roster->creator->name }}
                            </span>
                        @endif
                        @if ($roster->lastEditor && $roster->last_edited_at)
                            <span class="text-xs text-gray-400">
                                · Last edited by {{ $roster->lastEditor->name }}
                                {{ $roster->last_edited_at->diffForHumans() }}
                            </span>
                        @endif
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        @if ($roster->isDraft())
                            <button wire:click="submitRoster"
                                    class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
                                Submit for Approval
                            </button>
                        @endif

                        @if ($roster->isSubmitted() && $canApprove)
                            <button wire:click="approveRoster"
                                    class="px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700 transition">
                                Approve
                            </button>
                            <button wire:click="openRejectModal"
                                    class="px-4 py-2 bg-red-600 text-white text-sm font-medium rounded-lg hover:bg-red-700 transition">
                                Reject
                            </button>
                        @endif

                        @if ($roster->isSubmitted() || $roster->isRejected())
                            <button wire:click="revertToDraft"
                                    class="px-3 py-2 text-sm font-medium text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                                Revert to Draft
                            </button>
                        @endif

                        <button wire:click="exportPdf"
                                class="px-3 py-2 text-sm font-medium text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50 transition flex items-center gap-1">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            PDF
                        </button>

                        <button wire:click="openEmailModal"
                                class="px-3 py-2 text-sm font-medium text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50 transition flex items-center gap-1">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                            </svg>
                            Email
                        </button>
                    </div>
                </div>

                @if ($roster->isRejected() && $roster->rejection_reason)
                    <div class="mt-3 p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">
                        <strong>Rejection reason:</strong> {{ $roster->rejection_reason }}
                    </div>
                @endif
            </div>

            {{-- Roster Grid --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-100 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-48">
                                    Employee / Station
                                </th>
                                @foreach ($weekDays as $day)
                                    <th class="px-2 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider w-24">
                                        <div>{{ $day['dayName'] }}</div>
                                        <div class="text-gray-400">{{ $day['dayNum'] }}</div>
                                        @if (isset($dayRemarks[$day['date']]))
                                            <div class="mt-1">
                                                <span class="inline-block px-1.5 py-0.5 text-[10px] rounded
                                                    @if ($dayRemarks[$day['date']]->remark_type === 'public_holiday') bg-red-100 text-red-700
                                                    @elseif ($dayRemarks[$day['date']]->remark_type === 'stocktake') bg-blue-100 text-blue-700
                                                    @elseif ($dayRemarks[$day['date']]->remark_type === 'event') bg-purple-100 text-purple-700
                                                    @else bg-gray-100 text-gray-700 @endif"
                                                    title="{{ $dayRemarks[$day['date']]->remark_text }}">
                                                    {{ Str::limit($dayRemarks[$day['date']]->remark_text, 6) }}
                                                </span>
                                            </div>
                                        @endif
                                        @if ($roster->isDraft())
                                            <button wire:click="openRemarkForm('{{ $day['date'] }}')"
                                                    class="mt-1 text-[10px] text-indigo-500 hover:text-indigo-700">
                                                {{ isset($dayRemarks[$day['date']]) ? 'edit' : '+ remark' }}
                                            </button>
                                        @endif
                                    </th>
                                @endforeach
                                <th class="px-2 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider w-16">
                                    Regular
                                </th>
                                <th class="px-2 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider w-16">
                                    OT
                                </th>
                                <th class="px-2 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider w-16">
                                    Total
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50"
                               x-data="{
                                   dragging: false,
                                   init() {
                                       if (typeof Sortable !== 'undefined' && this.$refs.sortableBody && @js($roster->isDraft())) {
                                           new Sortable(this.$refs.sortableBody, {
                                               animation: 150,
                                               handle: '.drag-handle',
                                               ghostClass: 'bg-indigo-50',
                                               onEnd: (evt) => {
                                                   const rows = [...evt.from.querySelectorAll('tr[data-employee-id]')];
                                                   const orderedIds = rows.map(row => parseInt(row.dataset.employeeId));
                                                   @this.call('reorderEmployees', orderedIds);
                                               }
                                           });
                                       }
                                   }
                               }"
                               x-ref="sortableBody">
                            @forelse ($entriesBySection as $sectionData)
                                {{-- Section Header --}}
                                <tr class="bg-gray-100">
                                    <td colspan="{{ count($weekDays) + 4 }}" class="px-4 py-2">
                                        <span class="text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                            {{ $sectionData['section_name'] }}
                                        </span>
                                        <span class="text-xs text-gray-400 ml-2">({{ count($sectionData['employees']) }})</span>
                                    </td>
                                </tr>

                                @foreach ($sectionData['employees'] as $empId => $empData)
                                    <tr class="hover:bg-gray-50 group" data-employee-id="{{ $empId }}">
                                        <td class="px-4 py-3">
                                            <div class="flex items-center gap-2">
                                                @if ($roster->isDraft())
                                                    <span class="drag-handle cursor-grab text-gray-300 hover:text-gray-500">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16" />
                                                        </svg>
                                                    </span>
                                                @endif
                                                <div class="flex-1">
                                                    <div class="font-medium text-gray-900">{{ $empData['employee']?->name ?? 'Unknown' }}</div>
                                                    @php
                                                        $stationNames = collect($empData['entries'])->pluck('station.name')->filter()->unique()->implode(', ');
                                                    @endphp
                                                    @if ($stationNames)
                                                        <div class="text-xs text-gray-500">{{ $stationNames }}</div>
                                                    @endif
                                                </div>
                                                @if ($roster->isDraft())
                                                    <button wire:click="removeEmployeeRow({{ $empId }})"
                                                            wire:confirm="Remove {{ $empData['employee']?->name }} from this roster?"
                                                            class="opacity-0 group-hover:opacity-100 p-1 text-gray-400 hover:text-red-500 transition"
                                                            title="Remove from roster">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                                        </svg>
                                                    </button>
                                                @endif
                                            </div>
                                        </td>
                                        @foreach ($weekDays as $day)
                                            <td class="px-2 py-3 text-center">
                                                @if (isset($empData['entries'][$day['date']]))
                                                    @php
                                                        $entry = $empData['entries'][$day['date']];
                                                        // Determine cell styling based on shift type or leave type
                                                        $cellClass = 'bg-gray-100 text-gray-600';
                                                        if ($entry->is_off_day) {
                                                            // Different colors for different leave types
                                                            $cellClass = match($entry->leave_type) {
                                                                'off' => 'bg-gray-200 text-gray-600',
                                                                'al' => 'bg-amber-100 text-amber-700',
                                                                'rph' => 'bg-pink-100 text-pink-700',
                                                                'mc' => 'bg-red-100 text-red-600',
                                                                'rdo' => 'bg-orange-100 text-orange-700',
                                                                'ch' => 'bg-cyan-100 text-cyan-700',
                                                                default => 'bg-gray-200 text-gray-600',
                                                            };
                                                        } elseif ($entry->shift_start) {
                                                            $hour = (int) \Carbon\Carbon::parse($entry->shift_start)->format('G');
                                                            if ($hour < 10) {
                                                                $cellClass = 'bg-emerald-100 text-emerald-700 hover:bg-emerald-200';
                                                            } elseif ($hour < 14) {
                                                                $cellClass = 'bg-sky-100 text-sky-700 hover:bg-sky-200';
                                                            } else {
                                                                $cellClass = 'bg-violet-100 text-violet-700 hover:bg-violet-200';
                                                            }
                                                        }
                                                    @endphp
                                                    <button wire:click="openEditEntry({{ $entry->id }})"
                                                            class="w-full py-1.5 px-1 rounded text-xs font-medium {{ $cellClass }}
                                                                {{ ($roster->isApproved() && !$canAmend) ? 'cursor-not-allowed' : '' }}"
                                                            {{ ($roster->isApproved() && !$canAmend) ? 'disabled' : '' }}>
                                                        {{ $entry->shift_short }}
                                                    </button>
                                                @else
                                                    @if ($roster->isDraft())
                                                        <button wire:click="openAddEntry('{{ $day['date'] }}', {{ $empId }})"
                                                                class="w-full py-1 px-2 text-xs text-gray-400 hover:bg-gray-100 rounded">
                                                            +
                                                        </button>
                                                    @else
                                                        <span class="text-gray-300">-</span>
                                                    @endif
                                                @endif
                                            </td>
                                        @endforeach
                                        <td class="px-2 py-3 text-center font-medium text-gray-700">
                                            {{ number_format($empData['regular_hours'], 1) }}h
                                        </td>
                                        <td class="px-2 py-3 text-center font-medium {{ $empData['total_ot'] > 0 ? 'text-orange-600' : 'text-gray-400' }}">
                                            {{ number_format($empData['total_ot'], 1) }}h
                                        </td>
                                        <td class="px-2 py-3 text-center font-medium text-indigo-600">
                                            {{ number_format($empData['total_hours'], 1) }}h
                                        </td>
                                    </tr>
                                @endforeach
                            @empty
                                <tr>
                                    <td colspan="{{ count($weekDays) + 4 }}" class="px-4 py-8 text-center text-gray-500">
                                        No entries yet.
                                        @if ($roster->isDraft())
                                            Click a cell to add shifts.
                                        @endif
                                    </td>
                                </tr>
                            @endforelse

                            {{-- Add Employee Row (for draft) --}}
                            @if ($roster->isDraft() && $employees->isNotEmpty())
                                <tr class="bg-gray-50">
                                    <td colspan="{{ count($weekDays) + 4 }}" class="px-4 py-3">
                                        <button wire:click="openAddEntry('{{ $weekDays[0]['date'] ?? '' }}')"
                                                class="text-sm text-indigo-600 hover:text-indigo-800 font-medium">
                                            + Add Employee Entry
                                        </button>
                                    </td>
                                </tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Amendments History --}}
            @if ($roster->amendments->isNotEmpty())
                <div class="mt-4 bg-white rounded-xl shadow-sm border border-gray-100 p-4">
                    <h3 class="text-sm font-medium text-gray-700 mb-3">Amendment History</h3>
                    <div class="space-y-2">
                        @foreach ($roster->amendments->sortByDesc('created_at')->take(5) as $amendment)
                            <div class="text-xs text-gray-600 p-2 bg-gray-50 rounded">
                                <div class="flex justify-between">
                                    <span class="font-medium">{{ $amendment->entry->employee?->name ?? 'Unknown' }} - {{ $amendment->entry->day_date->format('D, M j') }}</span>
                                    <span class="text-gray-400">{{ $amendment->created_at->format('M j, H:i') }}</span>
                                </div>
                                <div class="mt-1 text-gray-500">{{ $amendment->reason }}</div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

        @else
            {{-- No Roster --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-8 text-center">
                <div class="text-gray-500 mb-4">No roster exists for this week.</div>
                <button wire:click="createRoster"
                        class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
                    Create Roster for {{ $periodLabel }}
                </button>
            </div>
        @endif
    @else
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-8 text-center text-gray-500">
            Please select an outlet to manage duty rosters.
        </div>
    @endif

    {{-- Entry Form Modal --}}
    @if ($showEntryForm)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 overflow-y-auto" wire:click.self="closeEntryForm">
            <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg my-8 overflow-hidden" @click.stop>
                <div class="px-6 py-4 bg-gray-50 border-b flex items-center justify-between">
                    <h3 class="font-semibold text-gray-700">
                        {{ $editingEntryId ? 'Edit Shift' : 'Add Shift' }}
                        @if ($f_day_date)
                            <span class="text-gray-500 font-normal">— {{ \Carbon\Carbon::parse($f_day_date)->format('D, M j') }}</span>
                        @endif
                    </h3>
                    <button wire:click="closeEntryForm" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                @if ($showAmendmentForm)
                    {{-- Amendment Reason Form --}}
                    <div class="p-6">
                        <div class="mb-4 p-3 bg-yellow-50 border border-yellow-200 rounded-lg text-sm text-yellow-700">
                            This roster is already approved. Please provide a reason for this amendment.
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Amendment Reason *</label>
                            <textarea wire:model="amendment_reason" rows="3"
                                      class="w-full text-sm rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                      placeholder="e.g. Staff requested day change, operational requirement..."></textarea>
                            @error('amendment_reason') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                        </div>
                        <div class="flex justify-end gap-3 mt-4">
                            <button type="button" wire:click="closeEntryForm"
                                    class="px-4 py-2 text-sm font-medium text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50">
                                Cancel
                            </button>
                            <button wire:click="confirmAmendment"
                                    class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">
                                Confirm Amendment
                            </button>
                        </div>
                    </div>
                @else
                    <form wire:submit="saveEntry" class="p-6 space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div class="col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Employee *</label>
                                <select wire:model="f_employee_id" class="w-full text-sm rounded-lg border-gray-300 shadow-sm">
                                    <option value="">Select employee...</option>
                                    @foreach ($employees as $emp)
                                        <option value="{{ $emp->id }}">{{ $emp->name }}</option>
                                    @endforeach
                                </select>
                                @error('f_employee_id') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Station</label>
                                <select wire:model="f_station_id" class="w-full text-sm rounded-lg border-gray-300 shadow-sm">
                                    <option value="">No station</option>
                                    @foreach ($stations as $station)
                                        <option value="{{ $station->id }}">{{ $station->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="flex items-end">
                                <label class="flex items-center cursor-pointer">
                                    <input type="checkbox" wire:model.live="f_is_off_day"
                                           class="rounded border-gray-300 text-red-600 focus:ring-red-500" />
                                    <span class="ml-2 text-sm text-gray-700">Leave/Off</span>
                                </label>
                            </div>
                        </div>

                        @if ($f_is_off_day)
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Leave Type</label>
                                <select wire:model="f_leave_type" class="w-full text-sm rounded-lg border-gray-300 shadow-sm">
                                    @foreach ($leaveTypes as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endif

                        @if (!$f_is_off_day)
                            <div class="grid grid-cols-3 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Shift Start</label>
                                    <input type="time" wire:model="f_shift_start"
                                           class="w-full text-sm rounded-lg border-gray-300 shadow-sm" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Shift End</label>
                                    <input type="time" wire:model="f_shift_end"
                                           class="w-full text-sm rounded-lg border-gray-300 shadow-sm" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Rest (min)</label>
                                    <input type="number" wire:model="f_rest_duration" min="0" max="480"
                                           class="w-full text-sm rounded-lg border-gray-300 shadow-sm" />
                                </div>
                            </div>

                            <div class="p-3 bg-gray-50 rounded-lg">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-sm text-gray-600">Planned OT</span>
                                    <label class="flex items-center cursor-pointer">
                                        <input type="checkbox" wire:model.live="f_planned_ot_manual"
                                               class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                                        <span class="ml-2 text-xs text-gray-500">Manual override</span>
                                    </label>
                                </div>
                                @if ($f_planned_ot_manual)
                                    <input type="number" wire:model="f_planned_ot" step="0.5" min="0" max="24"
                                           class="w-full text-sm rounded-lg border-gray-300 shadow-sm"
                                           placeholder="Hours" />
                                @else
                                    <div class="text-sm text-gray-500">OT will be auto-calculated based on normal hours setting.</div>
                                @endif
                            </div>
                        @endif

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                            <input type="text" wire:model="f_notes"
                                   class="w-full text-sm rounded-lg border-gray-300 shadow-sm"
                                   placeholder="Optional notes" />
                        </div>

                        <div class="flex justify-between pt-4 border-t">
                            @if ($editingEntryId && $roster?->isDraft())
                                <button type="button" wire:click="deleteEntry({{ $editingEntryId }})"
                                        wire:confirm="Remove this entry?"
                                        class="px-4 py-2 text-sm font-medium text-red-600 hover:text-red-800">
                                    Delete
                                </button>
                            @else
                                <div></div>
                            @endif
                            <div class="flex gap-3">
                                <button type="button" wire:click="closeEntryForm"
                                        class="px-4 py-2 text-sm font-medium text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50">
                                    Cancel
                                </button>
                                <button type="submit"
                                        class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">
                                    {{ $editingEntryId ? 'Update' : 'Add' }}
                                </button>
                            </div>
                        </div>
                    </form>
                @endif
            </div>
        </div>
    @endif

    {{-- Day Remark Modal --}}
    @if ($showRemarkForm)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 overflow-y-auto" wire:click.self="closeRemarkForm">
            <div class="bg-white rounded-xl shadow-2xl w-full max-w-md my-8 overflow-hidden" @click.stop>
                <div class="px-6 py-4 bg-gray-50 border-b flex items-center justify-between">
                    <h3 class="font-semibold text-gray-700">
                        Day Remark — {{ \Carbon\Carbon::parse($remark_date)->format('D, M j') }}
                    </h3>
                    <button wire:click="closeRemarkForm" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <form wire:submit="saveRemark" class="p-6 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Type</label>
                        <select wire:model="remark_type" class="w-full text-sm rounded-lg border-gray-300 shadow-sm">
                            <option value="public_holiday">Public Holiday</option>
                            <option value="stocktake">Stocktake</option>
                            <option value="event">Event</option>
                            <option value="custom">Custom</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Description *</label>
                        <input type="text" wire:model="remark_text"
                               class="w-full text-sm rounded-lg border-gray-300 shadow-sm"
                               placeholder="e.g. Christmas Day, Monthly Stocktake" />
                        @error('remark_text') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                    </div>
                    <div class="flex justify-between pt-4 border-t">
                        @if (isset($dayRemarks[$remark_date]))
                            <button type="button" wire:click="deleteRemark('{{ $remark_date }}')"
                                    class="px-4 py-2 text-sm font-medium text-red-600 hover:text-red-800">
                                Delete
                            </button>
                        @else
                            <div></div>
                        @endif
                        <div class="flex gap-3">
                            <button type="button" wire:click="closeRemarkForm"
                                    class="px-4 py-2 text-sm font-medium text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50">
                                Cancel
                            </button>
                            <button type="submit"
                                    class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">
                                Save
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- Reject Modal --}}
    @if ($showRejectModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 overflow-y-auto" wire:click.self="closeRejectModal">
            <div class="bg-white rounded-xl shadow-2xl w-full max-w-md my-8 overflow-hidden" @click.stop>
                <div class="px-6 py-4 bg-gray-50 border-b flex items-center justify-between">
                    <h3 class="font-semibold text-gray-700">Reject Roster</h3>
                    <button wire:click="closeRejectModal" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <form wire:submit="rejectRoster" class="p-6 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Rejection Reason *</label>
                        <textarea wire:model="rejection_reason" rows="3"
                                  class="w-full text-sm rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                  placeholder="Please explain why this roster is being rejected..."></textarea>
                        @error('rejection_reason') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                    </div>
                    <div class="flex justify-end gap-3 pt-4 border-t">
                        <button type="button" wire:click="closeRejectModal"
                                class="px-4 py-2 text-sm font-medium text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50">
                            Cancel
                        </button>
                        <button type="submit"
                                class="px-4 py-2 bg-red-600 text-white text-sm font-medium rounded-lg hover:bg-red-700">
                            Reject Roster
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- Email Modal --}}
    @if ($showEmailModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 overflow-y-auto" wire:click.self="closeEmailModal">
            <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg my-8 overflow-hidden" @click.stop>
                <div class="px-6 py-4 bg-gray-50 border-b flex items-center justify-between">
                    <h3 class="font-semibold text-gray-700">Email Duty Roster</h3>
                    <button wire:click="closeEmailModal" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <div class="p-6 space-y-4">
                    <p class="text-sm text-gray-600">Send the roster PDF to selected recipients:</p>

                    <div>
                        <label class="flex items-center cursor-pointer">
                            <input type="checkbox" wire:model="email_to_employees"
                                   class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                            <span class="ml-2 text-sm text-gray-700">
                                All assigned employees
                                <span class="text-gray-400">({{ $roster?->entries->pluck('employee_id')->unique()->count() ?? 0 }} employees)</span>
                            </span>
                        </label>
                    </div>

                    @if ($emailRecipients->isNotEmpty())
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Custom Recipients</label>
                            <div class="space-y-2 max-h-32 overflow-y-auto">
                                @foreach ($emailRecipients as $recipient)
                                    <label class="flex items-center cursor-pointer">
                                        <input type="checkbox" wire:model="email_recipient_ids" value="{{ $recipient->id }}"
                                               class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                                        <span class="ml-2 text-sm text-gray-700">
                                            {{ $recipient->email }}
                                            @if ($recipient->role_label)
                                                <span class="text-gray-400">({{ $recipient->role_label }})</span>
                                            @endif
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Additional Emails</label>
                        <input type="text" wire:model="email_additional"
                               class="w-full text-sm rounded-lg border-gray-300 shadow-sm"
                               placeholder="email1@example.com, email2@example.com" />
                        <p class="text-xs text-gray-500 mt-1">Separate multiple emails with commas</p>
                    </div>

                    <div class="flex justify-end gap-3 pt-4 border-t">
                        <button type="button" wire:click="closeEmailModal"
                                class="px-4 py-2 text-sm font-medium text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50">
                            Cancel
                        </button>
                        <button wire:click="sendEmail"
                                class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">
                            Send Email
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
