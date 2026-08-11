<div>
    @once
        <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.6/Sortable.min.js"></script>
    @endonce

    @if (session()->has('success'))
        <div wire:key="flash-{{ microtime(true) }}" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)"
             class="mb-4 px-4 py-3 bg-success-50 border border-success-200 text-success-700 text-sm rounded-lg">
            {{ session('success') }}
        </div>
    @endif
    @if (session()->has('error'))
        <div class="mb-4 px-4 py-3 bg-danger-50 border border-danger-200 text-danger-700 text-sm rounded-lg">
            {{ session('error') }}
        </div>
    @endif

    {{-- Header --}}
    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        <div>
            <p class="text-xs text-gray-600">HR / Attendance Record</p>
            <h2 class="text-lg font-semibold text-gray-700 mt-1 flex items-center gap-2">
                Attendance Record
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-brand-100 text-brand-700">
                    {{ number_format($employees->count()) }}
                </span>
            </h2>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <x-download-link :href="route('hr.attendance.export-pdf', ['search' => $search, 'outlet' => $outletFilter, 'section' => $sectionFilter, 'employment_status' => $employmentStatusFilter, 'from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d'), 'service_charge' => $showServiceCharge ? 1 : 0])"
                    title="Export PDF"
                    class="px-2.5 md:px-3 py-2 text-sm font-medium text-danger-600 border border-danger-200 rounded-lg hover:bg-danger-50 transition flex items-center gap-1.5">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                </svg>
                <span class="hidden sm:inline">PDF</span>
            </x-download-link>
            <button wire:click="openCodeCreate"
                    class="btn-secondary">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
                <span class="hidden sm:inline">Manage Codes</span>
            </button>
            {{-- Its own ability, not hr.compensation: splitting a pool needs
                 points and shares, never a salary. --}}
            @if ($canManageServiceCharge)
                <button wire:click="$toggle('showServiceCharge')"
                        class="px-2.5 md:px-3 py-2 text-sm font-medium rounded-lg transition flex items-center gap-1.5 border {{ $showServiceCharge ? 'bg-teal-600 border-teal-600 text-white hover:bg-teal-700' : 'text-teal-700 border-teal-200 hover:bg-teal-50' }}">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span class="hidden sm:inline">Service Charge</span>
                </button>
            @endif
            {{-- Says which table, because it only touches one. Hours are a
                 quantity and "Present" is not one — see fillPresent(). --}}
            @canDo('hr.attendance.record')
            <button wire:click="fillPresent"
                    wire:confirm="Mark every empty day on the salaried table as Present? Hourly staff are not touched."
                    class="btn-primary">
                Fill Empty with ✓
            </button>
            @endcanDo
        </div>
    </div>

    {{-- Filter / period bar --}}
    <div class="card p-4 mb-4">
        <div class="flex flex-col lg:flex-row lg:items-center flex-wrap gap-3">
            <div class="flex-1 min-w-[170px]">
                <input type="text" wire:model.live.debounce.300ms="search"
                       placeholder="Search name, staff ID, position…"
                       class="w-full text-sm rounded-lg border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500" />
            </div>
            <select wire:model.live="outletFilter" class="text-sm rounded-lg border-gray-300 shadow-sm">
                @if ($canViewAll)
                    <option value="">All Outlets</option>
                @endif
                @foreach ($outlets as $o)
                    <option value="{{ $o->id }}">{{ $o->name }}</option>
                @endforeach
            </select>
            <select wire:model.live="sectionFilter" class="text-sm rounded-lg border-gray-300 shadow-sm">
                <option value="">All Sections</option>
                @foreach ($sections as $s)
                    <option value="{{ $s->id }}">{{ $s->name }}</option>
                @endforeach
            </select>
            <select wire:model.live="employmentStatusFilter" class="text-sm rounded-lg border-gray-300 shadow-sm">
                <option value="">All Employment</option>
                <option value="exclude_outsourcing">All Exclude Outsourcing</option>
                @foreach (\App\Models\Employee::EMPLOYMENT_STATUSES as $esValue => $esLabel)
                    <option value="{{ $esValue }}">{{ $esLabel }}</option>
                @endforeach
                <option value="none">No Status</option>
            </select>

            {{-- Period picker --}}
            <div class="flex items-center gap-2 lg:ml-auto">
                <div class="inline-flex rounded-lg border border-gray-300 overflow-hidden text-sm">
                    <button wire:click="$set('periodMode', 'month')"
                            class="px-3 py-2 {{ $periodMode === 'month' ? 'bg-brand-600 text-white font-medium' : 'bg-white text-gray-600 hover:bg-gray-50' }}">
                        Month
                    </button>
                    <button wire:click="$set('periodMode', 'range')"
                            class="px-3 py-2 border-l border-gray-300 {{ $periodMode === 'range' ? 'bg-brand-600 text-white font-medium' : 'bg-white text-gray-600 hover:bg-gray-50' }}">
                        Custom
                    </button>
                </div>
                <button wire:click="previousPeriod" title="Previous period"
                        class="p-2 text-gray-500 border border-gray-300 rounded-lg hover:bg-gray-50">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                </button>
                @if ($periodMode === 'month')
                    <input type="month" wire:model.live="month"
                           class="text-sm rounded-lg border-gray-300 shadow-sm" />
                @else
                    <input type="date" wire:model.live="rangeFrom" class="text-sm rounded-lg border-gray-300 shadow-sm" />
                    <span class="text-gray-600 text-sm">–</span>
                    <input type="date" wire:model.live="rangeTo" class="text-sm rounded-lg border-gray-300 shadow-sm" />
                @endif
                <button wire:click="nextPeriod" title="Next period"
                        class="p-2 text-gray-500 border border-gray-300 rounded-lg hover:bg-gray-50">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                </button>
            </div>
        </div>
    </div>

    {{-- Paint palette: pick a code, then click day cells to apply it. An editing tool
         end to end, so it is absent rather than disabled without hr.attendance.record. --}}
    @if ($this->canRecord())
    <div class="card p-3 mb-4">
        <div class="flex flex-wrap items-center gap-1.5">
            <span class="text-xs text-gray-600 uppercase tracking-wider mr-1.5">Mark as</span>
            @foreach ($activeCodes as $code)
                @php $meta = $code->colorMeta(); @endphp
                <button wire:key="palette-{{ $code->id }}"
                        wire:click="selectCode({{ $code->id }})"
                        title="{{ $code->label }}"
                        class="px-2.5 py-1 rounded-md text-xs font-bold transition {{ $meta['tw'] }} {{ $selectedCodeId === $code->id ? 'ring-2 ring-brand-500 ring-offset-1' : 'opacity-80 hover:opacity-100' }}">
                    {{ $code->code }}
                </button>
            @endforeach
            <button wire:click="selectCode(null)"
                    title="Eraser — click a cell to clear it"
                    class="px-2.5 py-1 rounded-md text-xs font-bold border border-dashed transition {{ $selectedCodeId === null ? 'ring-2 ring-brand-500 ring-offset-1 border-gray-400 text-gray-600' : 'border-gray-300 text-gray-600 hover:text-gray-900' }}">
                ⌫ Clear
            </button>
            <span class="text-xs text-gray-600 ml-auto hidden md:inline">
                {{ $from->format('d M Y') }} – {{ $to->format('d M Y') }} · click a cell to mark it
            </span>
        </div>
    </div>
    @endif

    {{-- Said plainly rather than left to be inferred from a grid that quietly does
         nothing when clicked. --}}
    @unless ($this->canRecord())
        <div class="alert-info mb-4">
            <x-icon name="info" class="h-5 w-5 shrink-0" />
            <p>You can read the attendance record but not change it. Marking attendance,
               filling or clearing a range and managing codes all need the
               <span class="font-medium">Edit attendance</span> ability.</p>
        </div>
    @endunless

    {{-- Grid --}}
    <div class="card overflow-hidden mb-4"
         wire:loading.class="opacity-60" wire:target="setCell, fillPresent, clearRange">
        {{-- Headed only when there is a second table to tell it apart from.
             A lone table on a screen called Attendance Record does not need a
             label saying it is the attendance record. --}}
        @if ($hourlyEmployees->isNotEmpty())
            <div class="px-3 py-2 border-b border-gray-200 bg-gray-50">
                <h3 class="text-sm font-semibold text-gray-800">Salaried staff</h3>
                <p class="help">Marked with a code — present, off, MC. Pay does not depend on these.</p>
            </div>
        @endif
        <div class="overflow-x-auto">
            <table class="table-surface border-collapse">
                <thead>
                    <tr>
                        <th class="px-2 py-2 text-left w-8 border-b border-gray-200">#</th>
                        <th class="px-3 py-2 text-left min-w-[170px] border-b border-gray-200 sticky left-0 bg-gray-50 z-10">Name</th>
                        <th class="px-2 py-2 text-left min-w-[90px] border-b border-gray-200">Position</th>
                        <th class="px-2 py-2 text-left min-w-[70px] border-b border-gray-200">Emp ID</th>
                        <th class="px-2 py-2 text-left min-w-[90px] border-b border-gray-200">Outlet</th>
                        <th class="px-2 py-2 text-left min-w-[64px] border-b border-gray-200">Section</th>
                        <th class="px-2 py-2 text-left min-w-[76px] border-b border-gray-200">Date Join</th>
                        @if ($canViewPay)
                            <th class="px-2 py-2 text-right min-w-[54px] border-b border-gray-200">Svc Pts</th>
                            <th class="px-2 py-2 text-right min-w-[86px] border-b border-gray-200">Basic Salary</th>
                        @endif
                        @foreach ($dates as $d)
                            <th wire:key="dh-{{ $d->format('Ymd') }}"
                                class="px-0 py-1.5 text-center w-9 min-w-[34px] border-b border-l border-gray-200 {{ $d->isSunday() ? 'bg-danger-50 text-danger-500' : ($d->isSaturday() ? 'bg-warning-50/60 text-warning-600' : '') }} {{ $d->isToday() ? '!bg-brand-50 !text-brand-600' : '' }}">
                                <div class="text-[11px] font-bold leading-tight">{{ $d->day }}</div>
                                <div class="text-[9px] font-normal leading-tight">{{ $d->format('D') }}</div>
                            </th>
                        @endforeach
                        <th class="px-2 py-2 text-center min-w-[44px] border-b border-l-2 border-gray-200" title="Days marked Present">✓</th>
                        <th class="px-2 py-2 text-center min-w-[44px] border-b border-gray-200 text-danger-500" title="Days marked Absent">ABS</th>
                    </tr>
                </thead>
                <tbody
                       x-data x-init="new Sortable($el, {
                           handle: '.row-drag-handle',
                           animation: 150,
                           ghostClass: 'bg-brand-50',
                           onEnd(e) {
                               const ids = Array.from(e.from.querySelectorAll('tr[data-employee-id]'))
                                   .map(row => parseInt(row.dataset.employeeId));
                               $wire.reorderRows(ids);
                           }
                       })">
                    @forelse ($monthlyEmployees as $emp)
                        <tr wire:key="row-{{ $emp->id }}" data-employee-id="{{ $emp->id }}" class="hover:bg-gray-50/70">
                            <td class="px-2 py-1.5 text-gray-600 text-xs whitespace-nowrap">
                                <span class="row-drag-handle inline-flex align-middle cursor-grab active:cursor-grabbing text-gray-500 hover:text-gray-900"
                                      title="Drag to reorder">
                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 8h16M4 16h16"/></svg>
                                </span>
                                <span class="align-middle">{{ $loop->iteration }}</span>
                            </td>
                            <td class="px-3 py-1.5 font-medium text-gray-800 whitespace-nowrap sticky left-0 bg-white z-10">{{ $emp->name }}</td>
                            <td class="px-2 py-1.5 text-gray-500 text-xs whitespace-nowrap">{{ $emp->designation ?? '—' }}</td>
                            <td class="px-2 py-1.5 text-gray-500 font-mono text-xs">{{ $emp->staff_id ?? '—' }}</td>
                            <td class="px-2 py-1.5 text-gray-500 text-xs whitespace-nowrap">{{ $emp->outlet?->name ?? '—' }}</td>
                            <td class="px-2 py-1.5 text-gray-500 text-xs">{{ $emp->section?->name ?? '—' }}</td>
                            <td class="px-2 py-1.5 text-gray-500 text-xs whitespace-nowrap">{{ $emp->join_date?->format('d M y') ?? '—' }}</td>
                            @if ($canViewPay)
                                <td class="px-2 py-1.5 text-gray-500 text-xs text-right tabular-nums">{{ $emp->service_points_entitlement !== null ? number_format((float) $emp->service_points_entitlement, 2) : '—' }}</td>
                                <td class="px-2 py-1.5 text-gray-500 text-xs text-right tabular-nums whitespace-nowrap">
                                    @if ($emp->basic_salary !== null)
                                        {{ number_format((float) $emp->basic_salary, 2) }}<span class="text-[10px] text-gray-600 ml-0.5">{{ \App\Models\Employee::PAY_TYPE_SUFFIXES[$emp->pay_type] ?? '' }}</span>
                                    @else
                                        —
                                    @endif
                                </td>
                            @endif
                            @foreach ($dates as $d)
                                @php
                                    $key    = $emp->id . ':' . $d->format('Y-m-d');
                                    $codeId = $cellMap[$key] ?? null;
                                    $code   = $codeId ? ($codesById[$codeId] ?? null) : null;
                                    $meta   = $code?->colorMeta();
                                @endphp
                                <td wire:key="c-{{ $emp->id }}-{{ $d->format('Ymd') }}"
                                    class="p-0 border-l border-gray-100 text-center">
                                    @if ($this->canRecord())
                                        <button wire:click="setCell({{ $emp->id }}, '{{ $d->format('Y-m-d') }}')"
                                                title="{{ $emp->name }} · {{ $d->format('D, d M Y') }}{{ $code ? ' · ' . $code->label : '' }}"
                                                class="w-full h-8 text-[11px] font-bold transition
                                                       {{ $code ? $meta['tw'] : ($d->isSunday() ? 'bg-danger-50/40 hover:bg-brand-50' : 'hover:bg-brand-50') }}">
                                            {{ $code?->code }}
                                        </button>
                                    @else
                                        {{-- Same colour and letter, no hover and nothing to click:
                                             the record reads identically, it just cannot be painted. --}}
                                        <div title="{{ $emp->name }} · {{ $d->format('D, d M Y') }}{{ $code ? ' · ' . $code->label : '' }}"
                                             class="w-full h-8 flex items-center justify-center text-[11px] font-bold
                                                    {{ $code ? $meta['tw'] : ($d->isSunday() ? 'bg-danger-50/40' : '') }}">
                                            {{ $code?->code }}
                                        </div>
                                    @endif
                                </td>
                            @endforeach
                            <td class="px-2 py-1.5 text-center text-xs font-semibold text-success-700 border-l-2 border-gray-200">{{ $presentCounts[$emp->id] ?? 0 }}</td>
                            <td class="px-2 py-1.5 text-center text-xs font-semibold {{ ($absentCounts[$emp->id] ?? 0) > 0 ? 'text-danger-600' : 'text-gray-500' }}">{{ $absentCounts[$emp->id] ?? 0 }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ ($canViewPay ? 11 : 9) + count($dates) }}" class="px-4 py-10 text-center text-gray-600 text-sm">
                                No active employees match the selected filters.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- ── Hourly staff ──────────────────────────────────────────────────
         Its own table, present only when somebody is on it.

         A monthly row asks "were you here" and answers with a tick. An hourly
         row asks "how long for" and answers with a number that gets multiplied
         by a rate. Interleaving them put ticks and hours under one heading with
         totals underneath that could not mean anything for both — and the
         column that decides somebody's pay would have looked exactly like the
         column that does not.

         The cell takes EITHER, typed: "4.5" for hours, "MC" for a code. See
         AttendanceRecords::setHourlyCell() for why it is one input and not a
         number field beside a palette. --}}
    @if ($hourlyEmployees->isNotEmpty())
        <div class="card overflow-hidden mb-4" wire:loading.class="opacity-60" wire:target="setHourlyCell">
            <div class="px-3 py-2 border-b border-gray-200 bg-gray-50 flex flex-wrap items-baseline justify-between gap-2">
                <div>
                    <h3 class="text-sm font-semibold text-gray-800">Hourly staff</h3>
                    <p class="help">
                        Type the hours worked — <span class="font-mono">4.5</span>, <span class="font-mono">6</span> —
                        or a code such as <span class="font-mono">MC</span> for a day not worked. Blank is unpaid.
                    </p>
                </div>
                @if ($canViewPay)
                    <p class="text-xs text-gray-600">
                        These hours are what payroll multiplies by the hourly rate.
                    </p>
                @endif
            </div>

            <div class="overflow-x-auto">
                <table class="table-surface border-collapse">
                    <thead>
                        <tr>
                            <th class="px-2 py-2 text-left w-8 border-b border-gray-200">#</th>
                            <th class="px-3 py-2 text-left min-w-[170px] border-b border-gray-200 sticky left-0 bg-gray-50 z-10">Name</th>
                            <th class="px-2 py-2 text-left min-w-[90px] border-b border-gray-200">Position</th>
                            <th class="px-2 py-2 text-left min-w-[70px] border-b border-gray-200">Emp ID</th>
                            <th class="px-2 py-2 text-left min-w-[90px] border-b border-gray-200">Outlet</th>
                            <th class="px-2 py-2 text-left min-w-[64px] border-b border-gray-200">Section</th>
                            <th class="px-2 py-2 text-left min-w-[76px] border-b border-gray-200">Date Join</th>
                            @if ($canViewPay)
                                <th class="px-2 py-2 text-right min-w-[54px] border-b border-gray-200">Svc Pts</th>
                                <th class="px-2 py-2 text-right min-w-[86px] border-b border-gray-200">Rate</th>
                            @endif
                            @foreach ($dates as $d)
                                <th wire:key="hh-{{ $d->format('Ymd') }}"
                                    class="px-0 py-1.5 text-center w-9 min-w-[38px] border-b border-l border-gray-200 {{ $d->isSunday() ? 'bg-danger-50 text-danger-500' : ($d->isSaturday() ? 'bg-warning-50/60 text-warning-600' : '') }} {{ $d->isToday() ? '!bg-brand-50 !text-brand-600' : '' }}">
                                    <div class="text-[11px] font-bold leading-tight">{{ $d->day }}</div>
                                    <div class="text-[9px] font-normal leading-tight">{{ $d->format('D') }}</div>
                                </th>
                            @endforeach
                            <th class="px-2 py-2 text-center min-w-[44px] border-b border-l-2 border-gray-200" title="Days with hours entered">Days</th>
                            <th class="px-2 py-2 text-center min-w-[58px] border-b border-gray-200" title="Total hours this period">Hours</th>
                            @if ($canViewPay)
                                <th class="px-2 py-2 text-right min-w-[86px] border-b border-gray-200" title="Hours × rate">Pay</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($hourlyEmployees as $emp)
                            @php
                                $totalHours = $hourTotals[$emp->id] ?? 0;
                                $daysWorked = collect($dates)->filter(fn ($d) => isset($hoursMap[$emp->id . ':' . $d->format('Y-m-d')]))->count();
                                $rate       = $emp->basic_salary !== null ? (float) $emp->basic_salary : null;
                            @endphp
                            <tr wire:key="hrow-{{ $emp->id }}" class="hover:bg-gray-50/70">
                                <td class="px-2 py-1.5 text-gray-600 text-xs whitespace-nowrap">{{ $loop->iteration }}</td>
                                <td class="px-3 py-1.5 font-medium text-gray-800 whitespace-nowrap sticky left-0 bg-white z-10">{{ $emp->name }}</td>
                                <td class="px-2 py-1.5 text-gray-500 text-xs whitespace-nowrap">{{ $emp->designation ?? '—' }}</td>
                                <td class="px-2 py-1.5 text-gray-500 font-mono text-xs">{{ $emp->staff_id ?? '—' }}</td>
                                <td class="px-2 py-1.5 text-gray-500 text-xs whitespace-nowrap">{{ $emp->outlet?->name ?? '—' }}</td>
                                <td class="px-2 py-1.5 text-gray-500 text-xs">{{ $emp->section?->name ?? '—' }}</td>
                                <td class="px-2 py-1.5 text-gray-500 text-xs whitespace-nowrap">{{ $emp->join_date?->format('d M y') ?? '—' }}</td>
                                @if ($canViewPay)
                                    <td class="px-2 py-1.5 text-gray-500 text-xs text-right tabular-nums">{{ $emp->service_points_entitlement !== null ? number_format((float) $emp->service_points_entitlement, 2) : '—' }}</td>
                                    <td class="px-2 py-1.5 text-gray-500 text-xs text-right tabular-nums whitespace-nowrap">
                                        {{-- Named as a rate, because on this table that is what
                                             basic_salary is. Showing "Basic Salary 12.00" over a row
                                             of hours invites somebody to read 12 as the month. --}}
                                        @if ($rate !== null)
                                            {{ number_format($rate, 2) }}<span class="text-[10px] text-gray-600 ml-0.5">/ hr</span>
                                        @else
                                            <span class="text-danger-600" title="No rate on file — payroll cannot price these hours">— set a rate</span>
                                        @endif
                                    </td>
                                @endif
                                @foreach ($dates as $d)
                                    @php
                                        $key    = $emp->id . ':' . $d->format('Y-m-d');
                                        $hours  = $hoursMap[$key] ?? null;
                                        $codeId = $cellMap[$key] ?? null;
                                        $code   = $codeId ? ($codesById[$codeId] ?? null) : null;
                                        $meta   = $code?->colorMeta();
                                        // Trailing zeros dropped: a column of "4.50" is harder to
                                        // scan than one of "4.5", and these are read down a page.
                                        $shown  = $hours !== null
                                            ? rtrim(rtrim(number_format($hours, 2, '.', ''), '0'), '.')
                                            : ($code?->code ?? '');
                                    @endphp
                                    <td wire:key="hc-{{ $emp->id }}-{{ $d->format('Ymd') }}"
                                        class="p-0 border-l border-gray-100 text-center {{ $code ? $meta['tw'] : ($d->isSunday() ? 'bg-danger-50/40' : '') }}">
                                        @if ($this->canRecord())
                                            <input type="text" inputmode="decimal" autocomplete="off"
                                                   value="{{ $shown }}"
                                                   title="{{ $emp->name }} · {{ $d->format('D, d M Y') }} — hours, or a code such as MC"
                                                   x-on:change="$wire.setHourlyCell({{ $emp->id }}, '{{ $d->format('Y-m-d') }}', $event.target.value)"
                                                   x-on:focus="$event.target.select()"
                                                   class="w-full h-8 border-0 bg-transparent p-0 text-center text-[11px] font-semibold
                                                          text-gray-800 focus:bg-brand-50 focus:ring-1 focus:ring-inset focus:ring-brand-500">
                                        @else
                                            <div title="{{ $emp->name }} · {{ $d->format('D, d M Y') }}"
                                                 class="w-full h-8 flex items-center justify-center text-[11px] font-semibold text-gray-800">
                                                {{ $shown }}
                                            </div>
                                        @endif
                                    </td>
                                @endforeach
                                <td class="px-2 py-1.5 text-center text-xs font-semibold text-gray-700 border-l-2 border-gray-200">{{ $daysWorked ?: '—' }}</td>
                                <td class="px-2 py-1.5 text-center text-xs font-bold tabular-nums {{ $totalHours > 0 ? 'text-brand-700' : 'text-gray-500' }}">
                                    {{ $totalHours > 0 ? rtrim(rtrim(number_format($totalHours, 2, '.', ''), '0'), '.') : '—' }}
                                </td>
                                @if ($canViewPay)
                                    <td class="px-2 py-1.5 text-right text-xs font-semibold tabular-nums whitespace-nowrap {{ $rate === null ? 'text-danger-600' : 'text-gray-800' }}">
                                        {{ $rate !== null ? number_format($totalHours * $rate, 2) : '—' }}
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- Service Charge distribution --}}
    @if ($showServiceCharge && $serviceCharge)
        <div class="bg-white rounded-xl shadow-sm border border-teal-100 overflow-hidden mb-4">
            <div class="px-4 py-3 bg-teal-50/60 border-b border-teal-100">
                <h3 class="text-sm font-semibold text-teal-800">
                    Service Charge · {{ $from->format('d M Y') }} – {{ $to->format('d M Y') }}
                    @if ($outletFilter !== '')
                        · {{ $outlets->firstWhere('id', (int) $outletFilter)?->name }}
                    @else
                        · All Outlets
                    @endif
                </h3>
            </div>

            {{-- Pool + deduction settings --}}
            <div class="px-4 py-3 border-b border-gray-100 flex flex-wrap items-end gap-3">
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Service Charge Collected (RM)</label>
                    <input type="number" step="0.01" min="0" wire:model="scAmount" placeholder="e.g. 12000.00"
                           class="w-40 text-sm rounded-lg border-gray-300 shadow-sm" />
                    @error('scAmount') <p class="text-xs text-danger-500 mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    {{-- The hint lives in the label, not under the box. This row is
                         items-end, so a helper line below one field pushes that
                         field's input up and breaks the row's alignment. --}}
                    <label class="block text-xs text-gray-500 mb-1">Company retention % <span class="text-gray-400">(held back)</span></label>
                    <input type="number" step="0.01" min="0" max="100" wire:model="scRetention"
                           title="Held back before the pool is shared"
                           class="w-28 text-sm rounded-lg border-gray-300 shadow-sm" />
                    @error('scRetention') <p class="text-xs text-danger-500 mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">MC deduction % / day</label>
                    <input type="number" step="0.01" min="0" max="100" wire:model="scMcPercent"
                           class="w-28 text-sm rounded-lg border-gray-300 shadow-sm" />
                    @error('scMcPercent') <p class="text-xs text-danger-500 mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Absent deduction % / day</label>
                    <input type="number" step="0.01" min="0" max="100" wire:model="scAbsPercent"
                           class="w-28 text-sm rounded-lg border-gray-300 shadow-sm" />
                    @error('scAbsPercent') <p class="text-xs text-danger-500 mt-1">{{ $message }}</p> @enderror
                </div>
                <button wire:click="saveServiceCharge"
                        class="px-4 py-2 bg-teal-600 text-white text-sm font-medium rounded-lg hover:bg-teal-700 transition">
                    Save &amp; Calculate
                </button>
                @if ($serviceCharge['row'])
                    @canDo('hr.compensation')
                    <x-download-link :href="route('hr.attendance.payout-pdf', [
                                'from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d'),
                                'outlet' => $outletFilter, 'section' => $sectionFilter,
                                'search' => $search, 'employment_status' => $employmentStatusFilter,
                            ])"
                            title="One payout slip per employee"
                            class="px-3 py-2 text-sm font-medium text-danger-600 border border-danger-200 rounded-lg hover:bg-danger-50 transition flex items-center gap-1.5">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                        </svg>
                        Payout slips
                    </x-download-link>
                    @endcanDo
                @endif
                @if ($serviceCharge['row'])
                    <div class="flex flex-wrap items-center gap-2 ml-auto text-xs">
                        <span class="px-2.5 py-1 rounded-full bg-gray-100 text-gray-600">
                            Collected RM {{ number_format($serviceCharge['collected'], 2) }}
                        </span>
                        @if ($serviceCharge['retentionPct'] > 0)
                            <span class="px-2.5 py-1 rounded-full bg-gray-100 text-gray-600">
                                − {{ rtrim(rtrim(number_format($serviceCharge['retentionPct'], 2, '.', ''), '0'), '.') }}%
                                (RM {{ number_format($serviceCharge['retentionAmt'], 2) }})
                            </span>
                        @endif
                        <span class="px-2.5 py-1 rounded-full bg-teal-100 text-teal-800 font-semibold">
                            Distributable RM {{ number_format($serviceCharge['distributable'], 2) }}
                        </span>
                        <span class="px-2.5 py-1 rounded-full bg-gray-100 text-gray-600">
                            Points {{ number_format($serviceCharge['totalPoints'], 2) }}
                            @if ($serviceCharge['fundPoints'] > 0)
                                <span class="text-gray-500">({{ number_format($serviceCharge['staffPoints'], 2) }} staff
                                + {{ number_format($serviceCharge['fundPoints'], 2) }} funds)</span>
                            @endif
                        </span>
                        <span class="px-2.5 py-1 rounded-full bg-gray-100 text-gray-600">
                            RM {{ number_format($serviceCharge['perPoint']) }} / point
                        </span>
                    </div>
                @endif
            </div>

            {{-- Fund allocations. Points, not a second percentage: a fund holding
                 2 of 102 points dilutes every staff share exactly as another
                 employee would, and the arithmetic stays in one currency. --}}
            <div class="px-4 py-3 border-b border-gray-100">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <p class="text-xs font-medium text-gray-700">Additional allocations</p>
                        <p class="text-[11px] text-gray-500">
                            Named shares that take points alongside staff — an Outlet Fund, a Breakages Fund.
                        </p>
                    </div>
                    <button type="button" wire:click="addServiceChargeFund" class="btn-secondary">+ Add allocation</button>
                </div>

                @if (count($scFunds) > 0)
                    <div class="mt-3 space-y-2">
                        @foreach ($scFunds as $i => $fund)
                            <div wire:key="sc-fund-{{ $i }}" class="flex flex-wrap items-start gap-2">
                                <div>
                                    <input type="text" wire:model="scFunds.{{ $i }}.name" placeholder="e.g. Outlet Fund"
                                           class="w-48 text-sm rounded-lg border-gray-300 shadow-sm" />
                                    @error('scFunds.' . $i . '.name') <p class="text-xs text-danger-500 mt-1">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <input type="number" step="0.01" min="0" wire:model="scFunds.{{ $i }}.points" placeholder="points"
                                           class="w-28 text-sm rounded-lg border-gray-300 shadow-sm" />
                                    @error('scFunds.' . $i . '.points') <p class="text-xs text-danger-500 mt-1">{{ $message }}</p> @enderror
                                </div>
                                @if ($serviceCharge['row'] && isset($serviceCharge['funds'][$i]))
                                    <span class="text-xs text-gray-600 py-2">
                                        = RM {{ number_format($serviceCharge['funds'][$i]['amount'], 2) }}
                                    </span>
                                @endif
                                <button type="button" wire:click="removeServiceChargeFund({{ $i }})"
                                        class="text-danger-400 hover:text-danger-600 p-2" title="Remove">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M1 7h22M9 7V4a2 2 0 012-2h2a2 2 0 012 2v3"/></svg>
                                </button>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            @if ($serviceCharge['row'])
                @php
                    // Show the lateness columns whenever lateness is PRICED, not
                    // only when somebody was late: a column that appears and
                    // disappears between periods reads as a bug, and the rate
                    // needs somewhere to live.
                    $showLate = $serviceCharge['hasLate'] || $lateRatePerMinute > 0;
                @endphp
                <div class="overflow-x-auto">
                    <table class="table-surface">
                        <thead>
                            <tr>
                                <th class="px-3 py-2 text-left">Name</th>
                                <th class="px-2 py-2 text-right">Svc Pts</th>
                                <th class="px-2 py-2 text-center">MC Days</th>
                                <th class="px-2 py-2 text-center">ABS Days</th>
                                @if ($showLate)
                                    <th class="px-2 py-2 text-center">Late (min)</th>
                                @endif
                                <th class="px-2 py-2 text-right">Deduction %</th>
                                <th class="px-2 py-2 text-right">Gross (RM)</th>
                                <th class="px-2 py-2 text-right">Deduction (RM)</th>
                                @if ($showLate)
                                    <th class="px-2 py-2 text-right">
                                        Late (RM)
                                        @if ($lateRatePerMinute > 0)
                                            <span class="block font-normal normal-case text-[10px] text-gray-500">
                                                @ RM {{ rtrim(rtrim(number_format($lateRatePerMinute, 2, '.', ''), '0'), '.') }}/min
                                            </span>
                                        @endif
                                    </th>
                                @endif
                                <th class="px-2 py-2 text-right w-40">Special deduction (RM)</th>
                                <th class="px-2 py-2 text-right">Net (RM)</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($serviceCharge['rows'] as $scRow)
                                <tr wire:key="sc-{{ $scRow['employee']->id }}" class="hover:bg-gray-50/70 {{ $scRow['points'] <= 0 ? 'opacity-50' : '' }}">
                                    <td class="px-3 py-1.5 font-medium text-gray-800 whitespace-nowrap">
                                        {{ $scRow['employee']->name }}
                                        {{-- A leaver is on this pool because they worked part of
                                             it, and by default they earned their points. The tick
                                             is the override for when that is not the agreement;
                                             .live so the RM/point above moves with it, since
                                             removing someone changes what a point is worth for
                                             everyone else. Offered for resigned staff only. --}}
                                        @if ($scRow['employee']->hasResigned())
                                            <span class="block text-[10px] text-gray-500">
                                                Resigned{{ $scRow['employee']->employment_status_date
                                                    ? ' ' . $scRow['employee']->employment_status_date->format('d M Y') : '' }}
                                            </span>
                                            <label class="mt-0.5 inline-flex items-center gap-1.5 cursor-pointer">
                                                <input type="checkbox"
                                                       wire:model.live="scExcluded.{{ $scRow['employee']->id }}"
                                                       class="rounded border-gray-300 text-danger-600 focus:ring-danger-500" />
                                                <span class="text-[11px] font-normal {{ $scRow['excluded'] ? 'text-danger-600 font-medium' : 'text-gray-600' }}">
                                                    No service point
                                                </span>
                                            </label>
                                        @endif
                                    </td>
                                    <td class="px-2 py-1.5 text-right text-gray-600">{{ $scRow['points'] > 0 ? number_format($scRow['points'], 2) : '—' }}</td>
                                    <td class="px-2 py-1.5 text-center {{ $scRow['mcDays'] > 0 ? 'text-warning-600 font-semibold' : 'text-gray-500' }}">{{ $scRow['mcDays'] }}</td>
                                    <td class="px-2 py-1.5 text-center {{ $scRow['absDays'] > 0 ? 'text-danger-600 font-semibold' : 'text-gray-500' }}">{{ $scRow['absDays'] }}</td>
                                    @if ($showLate)
                                        <td class="px-2 py-1.5 text-center {{ $scRow['lateMins'] > 0 ? 'text-danger-600 font-semibold' : 'text-gray-500' }}">{{ $scRow['lateMins'] > 0 ? $scRow['lateMins'] : '—' }}</td>
                                    @endif
                                    <td class="px-2 py-1.5 text-right {{ $scRow['dedPct'] > 0 ? 'text-danger-600 font-semibold' : 'text-gray-600' }}">
                                        {{ $scRow['dedPct'] > 0 ? rtrim(rtrim(number_format($scRow['dedPct'], 2, '.', ''), '0'), '.') . '%' : '—' }}
                                    </td>
                                    <td class="px-2 py-1.5 text-right text-gray-600 tabular-nums">{{ $scRow['points'] > 0 ? number_format($scRow['gross'], 2) : '—' }}</td>
                                    <td class="px-2 py-1.5 text-right tabular-nums {{ $scRow['dedAmt'] > 0 ? 'text-danger-600' : 'text-gray-500' }}">
                                        {{ $scRow['dedAmt'] > 0 ? '-' . number_format($scRow['dedAmt'], 2) : '—' }}
                                    </td>
                                    @if ($showLate)
                                        <td class="px-2 py-1.5 text-right tabular-nums {{ $scRow['lateAmt'] > 0 ? 'text-danger-600' : 'text-gray-500' }}">
                                            {{ $scRow['lateAmt'] > 0 ? '-' . number_format($scRow['lateAmt'], 2) : '—' }}
                                        </td>
                                    @endif
                                    {{-- Editable in place: it is agreed per person per
                                         period, so it belongs on the row it applies to
                                         rather than in a separate screen. Saved with
                                         the pool by Save & Calculate. --}}
                                    <td class="px-2 py-1.5 text-right">
                                        {{-- Nothing to deduct from once excluded, so the
                                             inputs go rather than sit there accepting a
                                             figure that would never be applied. --}}
                                        @if ($scRow['elsewhere'] ?? false)
                                            {{-- Listed because they work here, zero because
                                                 their pool is elsewhere. Naming the outlet
                                                 stops this reading as a lost payment. --}}
                                            <span class="text-[11px] text-brand-700">
                                                paid from {{ $scRow['employee']->serviceChargeOutlet?->name ?? 'another outlet' }}
                                            </span>
                                        @elseif ($scRow['excluded'])
                                            <span class="text-[11px] text-gray-500">excluded</span>
                                        @else
                                            <input type="number" step="0.01" min="0"
                                                   wire:model="scSpecial.{{ $scRow['employee']->id }}.amount"
                                                   placeholder="0.00"
                                                   class="w-24 text-xs text-right rounded border-gray-300 tabular-nums" />
                                            <input type="text" maxlength="120"
                                                   wire:model="scSpecial.{{ $scRow['employee']->id }}.note"
                                                   placeholder="reason"
                                                   class="mt-1 w-32 text-[11px] rounded border-gray-200 text-gray-600" />
                                            @error('scSpecial.' . $scRow['employee']->id . '.amount')
                                                <p class="text-[10px] text-danger-500 mt-0.5">{{ $message }}</p>
                                            @enderror
                                        @endif
                                    </td>
                                    <td class="px-2 py-1.5 text-right font-semibold text-teal-700 tabular-nums">{{ $scRow['points'] > 0 ? number_format($scRow['net'], 2) : '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="bg-gray-50 border-t-2 border-gray-200 text-sm font-semibold">
                            <tr>
                                <td class="px-3 py-2 text-gray-700" colspan="{{ $serviceCharge['hasLate'] ? 6 : 5 }}">Total</td>
                                <td class="px-2 py-2 text-right text-gray-700 tabular-nums">{{ number_format($serviceCharge['totals']['gross'], 2) }}</td>
                                <td class="px-2 py-2 text-right text-danger-600 tabular-nums">-{{ number_format($serviceCharge['totals']['deduction'], 2) }}</td>
                                @if ($showLate)
                                    <td class="px-2 py-2 text-right text-danger-600 tabular-nums">-{{ number_format($serviceCharge['totals']['lateAmt'], 2) }}</td>
                                @endif
                                <td class="px-2 py-2 text-right tabular-nums {{ $serviceCharge['totals']['specialAmt'] > 0 ? 'text-danger-600' : 'text-gray-500' }}">
                                    {{ $serviceCharge['totals']['specialAmt'] > 0 ? '-' . number_format($serviceCharge['totals']['specialAmt'], 2) : '—' }}
                                </td>
                                <td class="px-2 py-2 text-right text-teal-700 tabular-nums">{{ number_format($serviceCharge['totals']['net'], 2) }}</td>
                            </tr>
                            @foreach ($serviceCharge['funds'] as $fund)
                                {{-- Funds sit under the staff total because they are
                                     paid out of the same pool at the same rate. --}}
                                <tr class="text-gray-700 font-normal">
                                    <td class="px-3 py-1.5 italic" colspan="{{ $showLate ? 6 : 5 }}">{{ $fund['name'] }}</td>
                                    <td class="px-2 py-1.5 text-right tabular-nums">{{ number_format($fund['amount'], 2) }}</td>
                                    <td colspan="{{ $showLate ? 3 : 2 }}"></td>
                                    <td class="px-2 py-1.5 text-right tabular-nums">{{ number_format($fund['amount'], 2) }}</td>
                                </tr>
                            @endforeach
                            @if (! empty($serviceCharge['funds']))
                                <tr class="border-t border-gray-200">
                                    <td class="px-3 py-2 text-gray-700" colspan="{{ $showLate ? 10 : 8 }}">
                                        Allocated of RM {{ number_format($serviceCharge['distributable'], 2) }} distributable
                                    </td>
                                    <td class="px-2 py-2 text-right text-teal-700 tabular-nums">{{ number_format($serviceCharge['allocated'], 2) }}</td>
                                </tr>
                            @endif
                        </tfoot>
                    </table>
                </div>
                <p class="px-4 py-2 text-[11px] text-gray-600 border-t border-gray-100">
                    Distributable = collected − {{ rtrim(rtrim(number_format($serviceCharge['retentionPct'], 2, '.', ''), '0'), '.') }}% retention.
                    Gross = Service Points × RM/point (distributable ÷ total points, rounded down to the nearest RM — section, employment and search filters narrow this table but never change the RM/point value).
                    @if ($serviceCharge['fundPoints'] > 0)
                        Total points include {{ number_format($serviceCharge['fundPoints'], 2) }} allocated to funds, which are paid at the same rate.
                    @endif
                    Deduction = MC days × {{ rtrim(rtrim(number_format($serviceCharge['mcPct'], 2, '.', ''), '0'), '.') }}%
                    + Absent days × {{ rtrim(rtrim(number_format($serviceCharge['absPct'], 2, '.', ''), '0'), '.') }}% of gross, capped at 100%.
                    MC days count cells marked with a code named MC or SL, or labelled “Sick”; ABS uses the built-in Absent code.
                    @if ($showLate)
                        Late (RM) is the web clock-in charge for minutes past the rostered start, after grace — one charge per shift, taken after the percentage deduction and never below a net of zero.
                    @endif
                    Special deduction is agreed per person for this period and is taken last, never below a net of zero.
                    Employees without Service Points are excluded from the split.
                    While this panel is open, the PDF export includes this table.
                </p>
            @else
                <p class="px-4 py-4 text-sm text-gray-600">
                    Enter the total service charge collected for this period and click <span class="font-medium text-gray-500">Save &amp; Calculate</span>.
                </p>
            @endif
        </div>
    @endif

    {{-- Legend --}}
    <div class="card p-4">
        <div class="flex items-center justify-between mb-3">
            <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Legend</h3>
            @canDo('hr.attendance.record')
            <button wire:click="clearRange"
                    {{-- Names the hours explicitly. This one DOES clear both
                         tables, and deleting a part-timer's month of hours is a
                         different order of loss from clearing some ticks. --}}
                    wire:confirm="Remove EVERY attendance mark in the visible period ({{ $from->format('d M Y') }} – {{ $to->format('d M Y') }})?{{ $hourlyEmployees->isNotEmpty() ? ' This includes all hours entered for hourly staff.' : '' }} This cannot be undone."
                    class="text-xs text-danger-500 hover:text-danger-700 underline">
                Clear all marks in this period
            </button>
            @endcanDo
        </div>
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-x-4 gap-y-1.5">
            @foreach ($activeCodes as $code)
                @php $meta = $code->colorMeta(); @endphp
                <div wire:key="legend-{{ $code->id }}" class="flex items-center gap-2 text-xs text-gray-600">
                    <span class="inline-flex items-center justify-center min-w-[30px] px-1 py-0.5 rounded font-bold text-[10px] {{ $meta['tw'] }}">{{ $code->code }}</span>
                    <span class="truncate" title="{{ $code->label }}">{{ $code->label }}</span>
                </div>
            @endforeach
        </div>
    </div>

    {{-- ── Manage Codes modal (teleported to body to escape sidebar transform) --}}
    <div x-data="{ open: @entangle('showCodes') }">
    <template x-teleport="body">
    <div x-show="open" x-cloak
         @keydown.escape.window="open = false"
         class="fixed inset-0 z-[100] overflow-y-auto">
        <div class="fixed inset-0 bg-black/50" @click="open = false"></div>
        <div class="relative min-h-full flex items-start sm:items-center justify-center p-4">
            <div class="relative bg-white rounded-xl shadow-xl w-full max-w-2xl" @click.stop>
                <div class="flex items-center justify-between px-5 py-3 border-b border-gray-100">
                    <h3 class="text-sm font-semibold text-gray-800">Attendance Codes</h3>
                    <button @click="open = false" class="text-gray-600 hover:text-gray-900 p-1">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <div class="p-5">
                    {{-- Add / edit form --}}
                    <div class="bg-gray-50 rounded-lg p-4 mb-4">
                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">
                            {{ $editingCodeId ? 'Edit Code' : 'Add New Code' }}
                        </p>
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">Code</label>
                                <input type="text" wire:model="c_code" maxlength="10" placeholder="e.g. OT"
                                       class="w-full text-sm rounded-lg border-gray-300 shadow-sm" />
                                @error('c_code') <p class="text-xs text-danger-500 mt-1">{{ $message }}</p> @enderror
                            </div>
                            <div class="col-span-2 sm:col-span-1">
                                <label class="block text-xs text-gray-500 mb-1">Label</label>
                                <input type="text" wire:model="c_label" maxlength="100" placeholder="e.g. Overtime"
                                       class="w-full text-sm rounded-lg border-gray-300 shadow-sm" />
                                @error('c_label') <p class="text-xs text-danger-500 mt-1">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">Color</label>
                                <select wire:model="c_color" class="w-full text-sm rounded-lg border-gray-300 shadow-sm">
                                    @foreach (array_keys(\App\Models\AttendanceCode::COLORS) as $colorKey)
                                        <option value="{{ $colorKey }}">{{ ucfirst($colorKey) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">Sort</label>
                                <input type="number" wire:model="c_sort" min="0"
                                       class="w-full text-sm rounded-lg border-gray-300 shadow-sm" />
                            </div>
                        </div>
                        <div class="flex items-center justify-between mt-3">
                            <label class="flex items-center gap-2 text-sm text-gray-600">
                                <input type="checkbox" wire:model="c_is_active" class="rounded border-gray-300 text-brand-600" />
                                Active
                            </label>
                            <div class="flex gap-2">
                                @if ($editingCodeId)
                                    <button wire:click="openCodeCreate"
                                            class="btn-secondary btn-sm text-gray-500">Cancel</button>
                                @endif
                                @canDo('hr.attendance.record')
                                <button wire:click="saveCode"
                                        class="btn-primary btn-sm">
                                    {{ $editingCodeId ? 'Update' : 'Add Code' }}
                                </button>
                                @endcanDo
                            </div>
                        </div>
                    </div>

                    {{-- Codes list --}}
                    <div class="max-h-72 overflow-y-auto border border-gray-100 rounded-lg divide-y divide-gray-50">
                        @foreach ($codes as $code)
                            @php $meta = $code->colorMeta(); @endphp
                            <div wire:key="code-row-{{ $code->id }}"
                                 class="flex items-center gap-3 px-3 py-2 {{ ! $code->is_active ? 'opacity-50' : '' }}">
                                <span class="inline-flex items-center justify-center min-w-[36px] px-1.5 py-1 rounded font-bold text-[11px] {{ $meta['tw'] }}">{{ $code->code }}</span>
                                <span class="flex-1 text-sm text-gray-700 truncate">
                                    {{ $code->label }}
                                    @if ($code->system_key)
                                        <span class="ml-1 text-[10px] uppercase tracking-wider text-gray-600">built-in</span>
                                    @endif
                                </span>
                                <button wire:click="openCodeEdit({{ $code->id }})"
                                        class="text-xs text-brand-600 hover:text-brand-800">Edit</button>
                                @unless ($code->system_key)
                                    <button wire:click="toggleCodeActive({{ $code->id }})"
                                            class="text-xs {{ $code->is_active ? 'text-warning-600 hover:text-warning-800' : 'text-success-600 hover:text-success-800' }}">
                                        {{ $code->is_active ? 'Deactivate' : 'Activate' }}
                                    </button>
                                    @canDo('hr.attendance.record')
                                    <button wire:click="deleteCode({{ $code->id }})"
                                            wire:confirm="Delete code {{ $code->code }} ({{ $code->label }})?"
                                            class="text-xs text-danger-500 hover:text-danger-700">Delete</button>
                                    @endcanDo
                                @endunless
                            </div>
                        @endforeach
                    </div>
                    <p class="text-[11px] text-gray-600 mt-2">
                        Built-in codes (Present ✓, Day Off X, Absent ABS) can be relabelled and recoloured but not deleted.
                        Codes already used in records can only be deactivated.
                    </p>
                </div>
            </div>
        </div>
    </div>
    </template>
    </div>
</div>
