<div>
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
            <p class="text-xs text-gray-600">HR / Service Charge</p>
            <h2 class="text-lg font-semibold text-gray-700 mt-1">Service Charge</h2>
            <p class="help mt-0.5">
                The pool for one outlet and period. MC, absent and working days come from the
                @can('hr.attendance')<a href="{{ route('hr.attendance') }}" class="text-brand-600 hover:underline">Attendance Record</a>@else Attendance Record @endcan.
            </p>
        </div>
    </div>

    {{-- Filter / period bar. Outlet and period only: a pool is keyed on
         exactly those two, and nothing else narrows who it pays. --}}
    <div class="card p-4 mb-4">
        <div class="flex flex-col lg:flex-row lg:items-center flex-wrap gap-3">
            <select wire:model.live="outletFilter" class="text-sm rounded-lg border-gray-300 shadow-sm">
                @if ($canViewAll)
                    <option value="">All Outlets</option>
                @endif
                @foreach ($outlets as $o)
                    <option value="{{ $o->id }}">{{ $o->name }}</option>
                @endforeach
            </select>
            {{-- Period picker.

                 IT WRAPS, and on a phone that is what keeps the whole filter
                 bar inside the screen. The row above is `flex-col` there, and
                 a column flex line takes its cross size from its widest item —
                 so this one control, 425px at its narrowest with two date
                 inputs in Custom mode, was stretching the search box and all
                 three dropdowns to 425px with it and pushing the page sideways.
                 min-w-0 on the inputs for the same reason: a date field will
                 not shrink below its intrinsic width without it. --}}
            <div class="flex flex-wrap items-center gap-2 min-w-0 lg:ml-auto lg:flex-nowrap">
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
                {{-- Back, the field, forward — one group, so on a phone the
                     wrap falls between the mode toggle and the navigator
                     rather than between the two arrows. They are a pair, and
                     one on each line reads as a fault.

                     It still wraps INTERNALLY, because it has to: in Custom
                     mode this holds two date inputs, and a date input has an
                     intrinsic minimum width that min-w-0 cannot argue with —
                     pinned to one line the group measured 468px and put the
                     page back over the edge. Month mode, which is the default
                     and the common case, fits on one line and keeps the
                     arrows together. --}}
                <div class="flex min-w-0 flex-wrap items-center gap-2 lg:flex-nowrap">
                    <button wire:click="previousPeriod" title="Previous period"
                            class="flex-shrink-0 p-2 text-gray-500 border border-gray-300 rounded-lg hover:bg-gray-50">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                    </button>
                    @if ($periodMode === 'month')
                        <input type="month" wire:model.live="month"
                               class="text-sm rounded-lg border-gray-300 shadow-sm" />
                    @else
                        <input type="date" wire:model.live="rangeFrom" class="text-sm rounded-lg border-gray-300 shadow-sm" />
                        {{-- Hidden once the two dates stack, where a dash trailing the end
                             of a row separates a field from nothing. --}}
                        <span class="hidden flex-shrink-0 text-gray-600 text-sm sm:inline">–</span>
                        <input type="date" wire:model.live="rangeTo" class="text-sm rounded-lg border-gray-300 shadow-sm" />
                    @endif
                    <button wire:click="nextPeriod" title="Next period"
                            class="flex-shrink-0 p-2 text-gray-500 border border-gray-300 rounded-lg hover:bg-gray-50">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Service Charge distribution --}}
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
                    <label class="block text-xs text-gray-500 mb-1">Service Charge Collected (RM)
                        @if (in_array('amount', $scPendingSettings ?? [], true))
                            <span class="font-medium text-warning-700">· not applied</span>
                        @endif
                    </label>
                    <input type="number" step="0.01" min="0" wire:model.blur="scAmount" placeholder="e.g. 12000.00"
                           class="w-40 text-sm rounded-lg border-gray-300 shadow-sm" />
                    @error('scAmount') <p class="text-xs text-danger-500 mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    {{-- The hint lives in the label, not under the box. This row is
                         items-end, so a helper line below one field pushes that
                         field's input up and breaks the row's alignment. --}}
                    <label class="block text-xs text-gray-500 mb-1">Company retention % <span class="text-gray-400">(held back)</span>
                        @if (in_array('retention', $scPendingSettings ?? [], true))
                            <span class="font-medium text-warning-700">· not applied</span>
                        @endif
                    </label>
                    <input type="number" step="0.01" min="0" max="100" wire:model.blur="scRetention"
                           title="Held back before the pool is shared"
                           class="w-28 text-sm rounded-lg border-gray-300 shadow-sm" />
                    @error('scRetention') <p class="text-xs text-danger-500 mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">MC deduction % / day
                        @if (in_array('mc', $scPendingSettings ?? [], true))
                            <span class="font-medium text-warning-700">· not applied</span>
                        @endif
                    </label>
                    <input type="number" step="0.01" min="0" max="100" wire:model.blur="scMcPercent"
                           class="w-28 text-sm rounded-lg border-gray-300 shadow-sm" />
                    @error('scMcPercent') <p class="text-xs text-danger-500 mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Absent deduction % / day
                        @if (in_array('abs', $scPendingSettings ?? [], true))
                            <span class="font-medium text-warning-700">· not applied</span>
                        @endif
                    </label>
                    <input type="number" step="0.01" min="0" max="100" wire:model.blur="scAbsPercent"
                           class="w-28 text-sm rounded-lg border-gray-300 shadow-sm" />
                    @error('scAbsPercent') <p class="text-xs text-danger-500 mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    {{-- The hint is in the label and the title, not on a line
                         below: this row is items-end, so a helper under one
                         field lifts that field's input out of alignment. --}}
                    <label class="block text-xs text-gray-500 mb-1">Min working days <span class="text-gray-400">(0 = none)</span>
                        @if ($scPendingMinDays ?? false)
                            <span class="font-medium text-warning-700">· not applied</span>
                        @endif
                    </label>
                    <input type="number" step="1" min="0" max="{{ \App\Livewire\Hr\AttendanceRecords::MAX_DAYS }}"
                           wire:model.blur="scMinWorkingDays"
                           title="Days someone must have worked in this period to share the pool. Paid leave counts; unrecorded (UNR) days, days off, absences and unpaid leave do not — which is what keeps a joiner or a leaver from taking a full share of a month they were barely in."
                           class="w-28 text-sm rounded-lg border-gray-300 shadow-sm" />
                    @error('scMinWorkingDays') <p class="text-xs text-danger-500 mt-1">{{ $message }}</p> @enderror
                </div>
                {{-- Sized to the inputs beside it (h-[38px]) so the row stays
                     aligned on items-end. --}}
                <label class="flex items-center gap-2 h-[38px] text-xs text-gray-700 cursor-pointer select-none"
                       title="MC, absence, lateness and special deductions go back into the pool and are shared by everyone in it, so they raise the final RM per point. Unticked, what is deducted stays with the company.">
                    <input type="checkbox" wire:model.live="scRedistribute"
                           class="rounded border-gray-300 text-brand-600 focus:ring-brand-500" />
                    Redistribute deductions to pool
                    @if ($scPendingRedistribute ?? false)
                        <span class="font-medium text-warning-700">· not applied</span>
                    @endif
                </label>
                <button wire:click="saveServiceCharge"
                        class="px-4 py-2 bg-teal-600 text-white text-sm font-medium rounded-lg hover:bg-teal-700 transition">
                    Save &amp; Calculate
                </button>

                {{-- A calculated period keeps its figures, so the way to move
                     them is to say so. Without this the only route back would
                     be re-saving the pool, and somebody would find it by
                     accident — which is exactly how a signed-off period used
                     to re-price itself. --}}
                @if ($serviceCharge['frozen'] ?? false)
                    <button wire:click="recalculateServiceCharge"
                            wire:confirm="Recalculate this period against current staff? The figures will change if anyone has joined, left, or had their points edited. Approved payroll runs are not affected."
                            class="px-4 py-2 border border-gray-300 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-50 transition">
                        Recalculate
                    </button>
                @endif
                @if ($serviceCharge['row'])
                    <x-download-link :href="route('hr.attendance.distribution-pdf', [
                                'from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d'),
                                'outlet' => $outletFilter,
                            ])"
                            title="The distribution table on its own sheet"
                            class="px-3 py-2 text-sm font-medium text-danger-600 border border-danger-200 rounded-lg hover:bg-danger-50 transition flex items-center gap-1.5">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                        </svg>
                        Distribution PDF
                    </x-download-link>
                    {{-- The same document as a sheet. Green against the PDF's
                         red, and the same table icon, because that is the pair
                         the Employees list already uses — two identically
                         styled download buttons side by side is how the wrong
                         one gets clicked. --}}
                    <x-download-link :href="route('hr.attendance.distribution-excel', [
                                'from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d'),
                                'outlet' => $outletFilter,
                            ])"
                            title="The distribution as a spreadsheet, with a status column and the pool's arithmetic"
                            class="px-3 py-2 text-sm font-medium text-success-700 border border-success-200 rounded-lg hover:bg-success-50 transition flex items-center gap-1.5">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M3 14h18m-9-4v8m-8 0h16a2 2 0 002-2V8a2 2 0 00-2-2H4a2 2 0 00-2 2v8a2 2 0 002 2z" />
                        </svg>
                        Distribution Excel
                    </x-download-link>
                @endif
                @if ($serviceCharge['row'])
                    @canDo('hr.compensation')
                    <x-download-link :href="route('hr.attendance.payout-pdf', [
                                'from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d'),
                                'outlet' => $outletFilter,
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
                        @if (($serviceCharge['redistribute'] ?? false) && ($serviceCharge['redistributed'] ?? 0) > 0)
                            <span class="px-2.5 py-1 rounded-full bg-gray-100 text-gray-600"
                                  title="Deductions returned to the pool and shared by everyone in it">
                                + RM {{ number_format($serviceCharge['redistributed'], 2) }} deductions redistributed
                            </span>
                            <span class="px-2.5 py-1 rounded-full bg-teal-100 text-teal-800 font-semibold"
                                  title="RM {{ number_format($serviceCharge['basePerPoint']) }} per point before deductions were redistributed">
                                RM {{ number_format($serviceCharge['basePerPoint']) }} → RM {{ number_format($serviceCharge['perPoint']) }} / point
                            </span>
                        @else
                            <span class="px-2.5 py-1 rounded-full bg-gray-100 text-gray-600">
                                RM {{ number_format($serviceCharge['perPoint']) }} / point
                            </span>
                        @endif
                        {{-- Says the figures are FIXED, and when they were
                             fixed. Without it there is nothing on screen to
                             distinguish a calculated period from one being
                             recomputed live, which is what made a changed
                             number look like a bug rather than a recalculation. --}}
                        @if ($serviceCharge['frozen'] ?? false)
                            <span class="px-2.5 py-1 rounded-full bg-success-50 text-success-700"
                                  title="These figures were calculated and kept. They will not change when staff records change.">
                                Calculated {{ $serviceCharge['calculatedAt']?->format('d M Y, g:ia') }}
                                @if ($serviceCharge['calculatedBy'])
                                    by {{ $serviceCharge['calculatedBy'] }}
                                @endif
                            </span>
                            {{-- The figures beside this are the KEPT ones, so an
                                 edit made since is held rather than applied. Said
                                 out loud because the alternative is a tick that
                                 looks broken: the control is .live, the table does
                                 not move, and nothing on screen explains the gap. --}}
                            @if (count($scPendingExclusions ?? []) || ($scPendingMinDays ?? false) || ($scPendingRedistribute ?? false) || count($scPendingLate ?? []) || count($scPendingSpecial ?? []) || ($scPendingFunds ?? false) || count($scPendingSettings ?? []))
                                <span class="px-2.5 py-1 rounded-full bg-warning-50 text-warning-800 font-medium"
                                      title="A calculated period keeps its figures until it is recalculated.">
                                    @php
                                        $pendingBits = [];
                                        $settingNames = [
                                            'amount' => 'the amount collected', 'retention' => 'the retention %',
                                            'mc' => 'the MC %', 'abs' => 'the absent %',
                                        ];
                                        foreach ($scPendingSettings ?? [] as $key) {
                                            $pendingBits[] = $settingNames[$key];
                                        }
                                        if (count($scPendingExclusions ?? [])) {
                                            $pendingBits[] = count($scPendingExclusions) . ' service point '
                                                . \Illuminate\Support\Str::plural('tick', count($scPendingExclusions));
                                        }
                                        if ($scPendingMinDays ?? false) {
                                            $pendingBits[] = 'the minimum';
                                        }
                                        if (count($scPendingLate ?? [])) {
                                            $pendingBits[] = count($scPendingLate) . ' late '
                                                . \Illuminate\Support\Str::plural('entry', count($scPendingLate));
                                        }
                                        if (count($scPendingSpecial ?? [])) {
                                            $pendingBits[] = count($scPendingSpecial) . ' special '
                                                . \Illuminate\Support\Str::plural('deduction', count($scPendingSpecial));
                                        }
                                        if ($scPendingFunds ?? false) {
                                            $pendingBits[] = 'the allocations';
                                        }
                                        if ($scPendingRedistribute ?? false) {
                                            $pendingBits[] = 'redistribution';
                                        }
                                    @endphp
                                    Not applied yet: {{ collect($pendingBits)->join(', ', ' and ') }} —
                                    press Save &amp; Calculate to re-price this period.
                                </span>
                            @endif
                        @endif
                    </div>
                @endif
            </div>

            {{-- Fund allocations. Points, not a second percentage: a fund holding
                 2 of 102 points dilutes every staff share exactly as another
                 employee would, and the arithmetic stays in one currency. --}}
            <div class="px-4 py-3 border-b border-gray-100">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <p class="text-xs font-medium text-gray-700">
                            Additional allocations
                            @if ($scPendingFunds ?? false)
                                <span class="ml-1 text-[10px] font-medium text-warning-700">not applied</span>
                            @endif
                        </p>
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
                                    <input type="text" wire:model.blur="scFunds.{{ $i }}.name" placeholder="e.g. Outlet Fund"
                                           class="w-48 text-sm rounded-lg border-gray-300 shadow-sm" />
                                    @error('scFunds.' . $i . '.name') <p class="text-xs text-danger-500 mt-1">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <input type="number" step="0.01" min="0" wire:model.blur="scFunds.{{ $i }}.points" placeholder="points"
                                           class="w-28 text-sm rounded-lg border-gray-300 shadow-sm" />
                                    @error('scFunds.' . $i . '.points') <p class="text-xs text-danger-500 mt-1">{{ $message }}</p> @enderror
                                </div>
                                {{-- The kept amount is matched by POSITION, so it is shown
                                     only while this row still reads as the fund it was
                                     calculated for. After a removal or a rename, position
                                     i may hold a different fund, and printing the old
                                     one's RM beside it would be a wrong figure. --}}
                                @if ($serviceCharge['row'] && isset($serviceCharge['funds'][$i])
                                     && trim((string) ($fund['name'] ?? '')) === $serviceCharge['funds'][$i]['name']
                                     && round((float) ($fund['points'] ?? 0), 2) === round((float) $serviceCharge['funds'][$i]['points'], 2))
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

                    // The days column earns its width only where a minimum is
                    // in force — and then it must be there, because it is the
                    // figure the qualifying decision was made on and the one
                    // anybody querying a zero row will ask to see.
                    $scMinDays = (int) ($serviceCharge['minDays'] ?? 0);
                    $showDays  = $scMinDays > 0;
                    $scLeadSpan = 5 + ($showLate ? 1 : 0) + ($showDays ? 1 : 0);
                @endphp
                <div class="overflow-x-auto">
                    <table class="table-surface">
                        <thead>
                            <tr>
                                <th class="px-3 py-2 text-left">Name</th>
                                <th class="px-2 py-2 text-right">Svc Pts</th>
                                @if ($showDays)
                                    <th class="px-2 py-2 text-center">
                                        Days
                                        <span class="block font-normal normal-case text-[10px] text-gray-500">min {{ $scMinDays }}</span>
                                    </th>
                                @endif
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
                                        @if ($scRow['employee']->hasResigned())
                                            <span class="block text-[10px] text-gray-500">
                                                Resigned{{ $scRow['employee']->employment_status_date
                                                    ? ' ' . $scRow['employee']->employment_status_date->format('d M Y') : '' }}
                                            </span>
                                        @endif
                                        {{-- Somebody is on this pool because they worked part of
                                             the period, and by default they earned their points.
                                             The tick is the override for when that is not the
                                             agreement; .live so the RM/point above moves with it,
                                             since removing someone changes what a point is worth
                                             for everyone else.

                                             Offered for EVERYBODY the pool pays, not only leavers:
                                             this is the instrument that takes points out of the
                                             divisor, and there are ordinary reasons to want it for
                                             staff still on the books. Not offered to somebody paid
                                             from another outlet — they are already taking nothing
                                             here, and their exclusion belongs to their own pool. --}}
                                        @unless ($scRow['elsewhere'] ?? false)
                                            <label class="mt-0.5 flex items-center gap-1.5 cursor-pointer">
                                                <input type="checkbox"
                                                       wire:model.live="scExcluded.{{ $scRow['employee']->id }}"
                                                       class="rounded border-gray-300 text-danger-600 focus:ring-danger-500" />
                                                <span class="text-[11px] font-normal {{ $scRow['excluded'] ? 'text-danger-600 font-medium' : 'text-gray-600' }}">
                                                    No service point
                                                </span>
                                                {{-- On THIS row, so the answer to "why is
                                                     that one excluded and mine is not" is
                                                     next to the tick rather than only in a
                                                     banner above the table. --}}
                                                @if (in_array($scRow['employee']->id, $scPendingExclusions ?? [], true))
                                                    <span class="text-[10px] font-medium text-warning-700">not applied</span>
                                                @endif
                                            </label>
                                        @endunless
                                    </td>
                                    <td class="px-2 py-1.5 text-right text-gray-600">{{ $scRow['points'] > 0 ? number_format($scRow['points'], 2) : '—' }}</td>
                                    @if ($showDays)
                                        <td class="px-2 py-1.5 text-center {{ ($scRow['belowMinDays'] ?? false) ? 'text-danger-600 font-semibold' : 'text-gray-600' }}"
                                            title="Days worked in this period. Paid leave counts; unrecorded (UNR) days, days off, absences and unpaid leave do not.">
                                            {{ (int) ($scRow['workDays'] ?? 0) }}
                                        </td>
                                    @endif
                                    <td class="px-2 py-1.5 text-center {{ $scRow['mcDays'] > 0 ? 'text-warning-600 font-semibold' : 'text-gray-500' }}">{{ $scRow['mcDays'] }}</td>
                                    <td class="px-2 py-1.5 text-center {{ $scRow['absDays'] > 0 ? 'text-danger-600 font-semibold' : 'text-gray-500' }}">{{ $scRow['absDays'] }}</td>
                                    @if ($showLate)
                                        @php $clockedMins = max(0, $scRow['lateMins'] - ($scRow['manualLateMins'] ?? 0)); @endphp
                                        @if ($scRow['excluded'])
                                            <td class="px-2 py-1.5 text-center {{ $scRow['lateMins'] > 0 ? 'text-danger-600 font-semibold' : 'text-gray-500' }}">{{ $scRow['lateMins'] > 0 ? $scRow['lateMins'] : '—' }}</td>
                                        @else
                                            {{-- Typed-in minutes, for lateness the web clock did not
                                                 see. The clocked minutes are shown under it, because
                                                 the two are added together, not one instead of the
                                                 other. Saved with the pool by Save & Calculate. --}}
                                            <td class="px-2 py-1.5 text-center">
                                                <input type="number" step="1" min="0"
                                                       wire:model.blur="scManualLate.{{ $scRow['employee']->id }}"
                                                       placeholder="0"
                                                       title="Late minutes to add by hand for this period, charged at the clock's per-minute rate with no per-shift cap"
                                                       class="w-16 text-xs text-center rounded border-gray-300 tabular-nums" />
                                                @if ($clockedMins > 0)
                                                    <span class="block text-[10px] text-danger-600 mt-0.5">+ {{ $clockedMins }} clocked</span>
                                                @endif
                                                @if (in_array($scRow['employee']->id, $scPendingLate ?? [], true))
                                                    <span class="block text-[10px] font-medium text-warning-700 mt-0.5">not applied</span>
                                                @endif
                                                @error('scManualLate.' . $scRow['employee']->id)
                                                    <p class="text-[10px] text-danger-500 mt-0.5">{{ $message }}</p>
                                                @enderror
                                            </td>
                                        @endif
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
                                        @elseif ($scRow['belowMinDays'] ?? false)
                                            {{-- A rule, not a decision about this person, so
                                                 it says which rule and what they had. "Excluded"
                                                 on its own would send somebody looking for a
                                                 tick nobody ever put there. --}}
                                            <span class="text-[11px] text-gray-500">
                                                {{ (int) ($scRow['workDays'] ?? 0) }} of {{ $scMinDays }} working days
                                            </span>
                                        @elseif ($scRow['excluded'])
                                            <span class="text-[11px] text-gray-500">excluded</span>
                                        @else
                                            <input type="number" step="0.01" min="0"
                                                   wire:model.blur="scSpecial.{{ $scRow['employee']->id }}.amount"
                                                   placeholder="0.00"
                                                   class="w-24 text-xs text-right rounded border-gray-300 tabular-nums" />
                                            <input type="text" maxlength="120"
                                                   wire:model.blur="scSpecial.{{ $scRow['employee']->id }}.note"
                                                   placeholder="reason"
                                                   class="mt-1 w-32 text-[11px] rounded border-gray-200 text-gray-600" />
                                            @if (in_array($scRow['employee']->id, $scPendingSpecial ?? [], true))
                                                <span class="block text-[10px] font-medium text-warning-700 mt-0.5">not applied</span>
                                            @endif
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
                                <td class="px-3 py-2 text-gray-700" colspan="{{ $scLeadSpan }}">Total</td>
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
                                    <td class="px-3 py-1.5 italic" colspan="{{ $scLeadSpan }}">{{ $fund['name'] }}</td>
                                    <td class="px-2 py-1.5 text-right tabular-nums">{{ number_format($fund['amount'], 2) }}</td>
                                    <td colspan="{{ $showLate ? 3 : 2 }}"></td>
                                    <td class="px-2 py-1.5 text-right tabular-nums">{{ number_format($fund['amount'], 2) }}</td>
                                </tr>
                            @endforeach
                            @if (! empty($serviceCharge['funds']))
                                <tr class="border-t border-gray-200">
                                    <td class="px-3 py-2 text-gray-700" colspan="{{ 8 + ($showLate ? 2 : 0) + ($showDays ? 1 : 0) }}">
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
                    Gross = Service Points × RM/point (distributable ÷ total points, rounded down to the nearest RM).
                    @if ($serviceCharge['fundPoints'] > 0)
                        Total points include {{ number_format($serviceCharge['fundPoints'], 2) }} allocated to funds, which are paid at the same rate.
                    @endif
                    Deduction = MC days × {{ rtrim(rtrim(number_format($serviceCharge['mcPct'], 2, '.', ''), '0'), '.') }}%
                    + Absent days × {{ rtrim(rtrim(number_format($serviceCharge['absPct'], 2, '.', ''), '0'), '.') }}% of gross, capped at 100%.
                    MC days count cells marked with a code named MC or SL, or labelled “Sick”; ABS uses the built-in Absent code.
                    @if ($showLate)
                        Late (RM) is the web clock-in charge for minutes past the rostered start, after grace — one charge per shift — plus any minutes typed into Late (min), charged at the same per-minute rate with no per-shift cap. It is taken after the percentage deduction and never below a net of zero.
                    @endif
                    Special deduction is agreed per person for this period and is taken last, never below a net of zero.
                    @if ($showDays)
                        Anyone with fewer than {{ $scMinDays }} working days in this period takes no share, and their points come out of the
                        divisor with them, so the RM/point above is what the qualifying staff actually share.
                        Days worked count cells with any mark except Unrecorded (UNR), Day Off, Absent and Unpaid Leave — an unrecorded day is one
                        the person was not here for at all, before they joined or after they left. Leave days count.
                    @endif
                    Employees without Service Points are excluded from the split.
                    MC, absent and working days are read from the
                    @can('hr.attendance')<a href="{{ route('hr.attendance') }}" class="text-brand-600 hover:underline">Attendance Record</a>@else Attendance Record @endcan for the same period.
                </p>
            @else
                <p class="px-4 py-4 text-sm text-gray-600">
                    Enter the total service charge collected for this period and click <span class="font-medium text-gray-500">Save &amp; Calculate</span>.
                </p>
            @endif
        </div>
</div>
