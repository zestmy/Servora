<div>
    <x-page-header title="Time Off" eyebrow="HR">
        <x-slot:actions>
            @canDo('hr.claims')
            <a href="{{ route('hr.overtime-claims') }}" class="btn-secondary">Overtime claims</a>
            @endcanDo
            <button wire:click="openApply" class="btn-primary">Apply for time off</button>
        </x-slot:actions>
    </x-page-header>

    @if (session('success'))
        <div class="alert-success mb-4">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="alert-danger mb-4">{{ session('error') }}</div>
    @endif

    <div class="panel p-4 mb-4">
        <p class="text-sm text-gray-700">
            Time off is taken against overtime the company has <strong>approved but not yet paid</strong>.
            An hour of overtime is either paid or taken off — never both.
        </p>
        <p class="text-xs text-gray-600 mt-1">
            Requested in <strong>hours</strong>, because a working day is not the same length for everyone —
            7.5, 9 and 11-hour contracts all exist here, so "a day off" is not one amount of overtime.
            Once a payroll run is approved, the overtime it pays leaves the balance for good.
        </p>
    </div>

    <div class="toolbar mb-4">
        <div class="flex flex-wrap items-end gap-3">
            <div>
                <label class="label">Status</label>
                <select wire:model.live="statusFilter" class="input">
                    <option value="">All</option>
                    @foreach (\App\Models\TimeOffRequest::STATUSES as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="label">Outlet</label>
                <select wire:model.live="outletFilter" class="input">
                    <option value="">All outlets</option>
                    @foreach ($outlets as $outlet)
                        <option value="{{ $outlet->id }}">{{ $outlet->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="label">Search</label>
                <input type="text" wire:model.live.debounce.400ms="search" class="input" placeholder="Employee name" />
            </div>
            @if ($pendingCount > 0)
                <span class="badge-warning mb-1">{{ $pendingCount }} awaiting approval</span>
            @endif
        </div>
    </div>

    {{-- Apply --}}
    @if ($showApply)
        <div class="card p-5 mb-4 space-y-3">
            <h3 class="text-sm font-semibold text-gray-700">Apply for time off</h3>

            <div class="grid grid-cols-1 sm:grid-cols-4 gap-3">
                <div>
                    <label class="label">Employee <span class="text-danger-500">*</span></label>
                    <select wire:model.live="a_employee" class="input">
                        <option value="">— Select —</option>
                        @foreach ($employees as $e)
                            <option value="{{ $e->id }}">{{ $e->name }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('a_employee')" class="mt-1" />
                </div>
                <div>
                    <label class="label">Date <span class="text-danger-500">*</span></label>
                    <input type="date" wire:model="a_date" class="input" />
                    <x-input-error :messages="$errors->get('a_date')" class="mt-1" />
                </div>
                <div>
                    <label class="label">Hours <span class="text-danger-500">*</span></label>
                    <input type="number" step="0.25" min="0.25" max="24" wire:model.live="a_hours" class="input" />
                    <x-input-error :messages="$errors->get('a_hours')" class="mt-1" />
                </div>
                <div class="flex items-end pb-1">
                    @php
                        $chosen = $a_employee ? $employees->firstWhere('id', (int) $a_employee) : null;
                    @endphp
                    @if ($chosen)
                        <div class="text-xs text-gray-700">
                            <strong>{{ rtrim(rtrim(number_format($balanceService->availableHours($chosen), 2), '0'), '.') }}</strong>
                            hour(s) available
                            <span class="block text-[11px] text-gray-500">
                                {{ rtrim(rtrim(number_format($balanceService->workingDayHours($chosen), 2), '0'), '.') }}-hour working day
                                @if ((float) $a_hours > 0)
                                    · this is {{ rtrim(rtrim(number_format($balanceService->asDays($chosen, (float) $a_hours), 2), '0'), '.') }} day(s)
                                @endif
                            </span>
                        </div>
                    @endif
                </div>
            </div>

            <div>
                <label class="label">Reason</label>
                <input type="text" maxlength="500" wire:model="a_reason" class="input" />
                <x-input-error :messages="$errors->get('a_reason')" class="mt-1" />
            </div>

            <div class="flex justify-end gap-2">
                <button wire:click="$set('showApply', false)" class="btn-ghost">Cancel</button>
                <button wire:click="apply" class="btn-primary">Submit</button>
            </div>
        </div>
    @endif

    {{-- Requests --}}
    <div class="card overflow-hidden mb-4">
        <div class="overflow-x-auto">
            <table class="table-surface min-w-[940px]">
                <thead>
                    <tr>
                        <th class="px-3 py-2 text-left">Employee</th>
                        <th class="px-2 py-2 text-left">Date</th>
                        <th class="px-2 py-2 text-right">Hours</th>
                        <th class="px-2 py-2 text-left">Taken from</th>
                        <th class="px-2 py-2 text-left">Reason</th>
                        <th class="px-2 py-2 text-left">Status</th>
                        <th class="px-2 py-2"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($requests as $req)
                        <tr wire:key="to-{{ $req->id }}" class="hover:bg-gray-50">
                            <td class="px-3 py-2 whitespace-nowrap">
                                <span class="font-medium text-gray-800">{{ $req->employee?->name ?? '—' }}</span>
                                @if ($req->employee?->staff_id)
                                    <span class="block text-[10px] text-gray-500 font-mono">{{ $req->employee->staff_id }}</span>
                                @endif
                            </td>
                            <td class="px-2 py-2 text-xs text-gray-700 whitespace-nowrap">{{ $req->off_date->format('d M Y') }}</td>
                            <td class="px-2 py-2 text-right tabular-nums text-gray-700">
                                {{ rtrim(rtrim(number_format((float) $req->hours, 2), '0'), '.') }}
                                @if ($req->employee)
                                    <span class="block text-[10px] text-gray-500">
                                        {{ rtrim(rtrim(number_format($balanceService->asDays($req->employee, (float) $req->hours), 2), '0'), '.') }} day(s)
                                    </span>
                                @endif
                            </td>
                            <td class="px-2 py-2 text-[11px] text-gray-600">
                                {{-- Which overtime funded it. "Where did my
                                     Tuesday afternoon come from" has an answer. --}}
                                @forelse ($req->allocations as $alloc)
                                    <span class="block">
                                        {{ rtrim(rtrim(number_format((float) $alloc->hours, 2), '0'), '.') }}h from
                                        {{ $alloc->claim?->claim_date?->format('d M Y') ?? 'a claim' }}
                                    </span>
                                @empty
                                    <span class="text-gray-400">—</span>
                                @endforelse
                            </td>
                            <td class="px-2 py-2 text-xs text-gray-600 max-w-[200px] truncate" title="{{ $req->reason }}">
                                {{ $req->reason ?: '—' }}
                            </td>
                            <td class="px-2 py-2">
                                @php
                                    $tone = match ($req->status) {
                                        \App\Models\TimeOffRequest::APPROVED  => 'badge-success',
                                        \App\Models\TimeOffRequest::REJECTED  => 'badge-danger',
                                        \App\Models\TimeOffRequest::CANCELLED => 'badge-info',
                                        default                               => 'badge-warning',
                                    };
                                @endphp
                                <span class="{{ $tone }}">{{ $req->statusLabel() }}</span>
                                @if ($req->approver)
                                    <span class="block text-[10px] text-gray-500 mt-0.5">by {{ $req->approver->name }}</span>
                                @endif
                                @if ($req->decision_note)
                                    <span class="block text-[10px] text-gray-500">{{ $req->decision_note }}</span>
                                @endif
                            </td>
                            <td class="px-2 py-2 text-right whitespace-nowrap">
                                @if ($req->isPending() && $canApprove)
                                    @if ($decidingId === $req->id)
                                        <input type="text" wire:model="decisionNote" placeholder="note (optional)"
                                               class="input text-xs w-36 inline-block" />
                                        <button wire:click="decide({{ $req->id }}, 'approved')"
                                                class="ml-2 text-xs font-medium text-success-700 hover:text-success-800">Approve</button>
                                        <button wire:click="decide({{ $req->id }}, 'rejected')"
                                                class="ml-2 text-xs font-medium text-danger-600 hover:text-danger-800">Reject</button>
                                        <button wire:click="$set('decidingId', null)"
                                                class="ml-2 text-xs text-gray-500 hover:text-gray-700">Cancel</button>
                                    @else
                                        <button wire:click="$set('decidingId', {{ $req->id }})"
                                                class="text-xs font-medium text-brand-600 hover:text-brand-800">Decide</button>
                                    @endif
                                @elseif ($req->isPending())
                                    <span class="text-[11px] text-gray-500">awaiting approval</span>
                                @endif
                                @if (in_array($req->status, ['pending', 'approved'], true))
                                    <button wire:click="cancel({{ $req->id }})"
                                            wire:confirm="Cancel this time off? The hours go back on the overtime balance."
                                            class="ml-3 text-xs font-medium text-gray-600 hover:text-gray-900">Cancel</button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-3 py-6 text-center text-sm text-gray-600">
                            No time off to show.
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-4 py-2 border-t border-gray-100">{{ $requests->links() }}</div>
    </div>

    {{-- Who has hours --}}
    <div class="card overflow-hidden">
        <div class="px-4 py-3 border-b border-gray-100">
            <h3 class="text-sm font-semibold text-gray-700">Available overtime</h3>
            <p class="text-xs text-gray-600 mt-0.5">
                Approved overtime not yet paid, less anything already requested. Staff with none are not listed.
            </p>
        </div>
        @if ($balances->isEmpty())
            <p class="px-4 py-6 text-center text-sm text-gray-600">
                Nobody in this view has unpaid overtime to take as time off.
            </p>
        @else
            <div class="overflow-x-auto">
                <table class="table-surface min-w-[560px]">
                    <thead>
                        <tr>
                            <th class="px-3 py-2 text-left">Employee</th>
                            <th class="px-2 py-2 text-right">Hours available</th>
                            <th class="px-2 py-2 text-right">Working day</th>
                            <th class="px-2 py-2 text-right">In days</th>
                            <th class="px-2 py-2"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($balances as $row)
                            <tr wire:key="tob-{{ $row['employee']->id }}" class="hover:bg-gray-50">
                                <td class="px-3 py-1.5 font-medium text-gray-800 whitespace-nowrap">{{ $row['employee']->name }}</td>
                                <td class="px-2 py-1.5 text-right tabular-nums font-semibold text-brand-700">
                                    {{ rtrim(rtrim(number_format($row['available'], 2), '0'), '.') }}
                                </td>
                                <td class="px-2 py-1.5 text-right tabular-nums text-gray-600">
                                    {{ rtrim(rtrim(number_format($row['day_hours'], 2), '0'), '.') }}h
                                </td>
                                <td class="px-2 py-1.5 text-right tabular-nums text-gray-700">
                                    {{ rtrim(rtrim(number_format($balanceService->asDays($row['employee'], $row['available']), 2), '0'), '.') }}
                                </td>
                                <td class="px-2 py-1.5 text-right">
                                    <button wire:click="openApply({{ $row['employee']->id }})"
                                            class="text-xs font-medium text-brand-600 hover:text-brand-800 whitespace-nowrap">Apply</button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
