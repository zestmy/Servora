<div>
    <x-page-header title="Leave" eyebrow="HR">
        <x-slot:actions>
            @if ($canApprove)
                <button wire:click="openGrant" class="btn-secondary">Grant entitlement</button>
            @endif
            <button wire:click="openApply" class="btn-primary">Apply for leave</button>
        </x-slot:actions>
    </x-page-header>

    @if (session('success'))
        <div class="alert-success mb-4">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="alert-danger mb-4">{{ session('error') }}</div>
    @endif

    {{-- Filters --}}
    <div class="toolbar mb-4">
        <div class="flex flex-wrap items-end gap-3">
            <div>
                <label class="label">Year</label>
                <input type="number" min="2000" max="2100" wire:model.live="year" class="input w-28" />
            </div>
            <div>
                <label class="label">Status</label>
                <select wire:model.live="statusFilter" class="input">
                    <option value="">All</option>
                    @foreach (\App\Models\LeaveRequest::STATUSES as $value => $label)
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
            <h3 class="text-sm font-semibold text-gray-700">Apply for leave</h3>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
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
                    <label class="label">Leave type <span class="text-danger-500">*</span></label>
                    <select wire:model.live="a_type" class="input">
                        <option value="">— Select —</option>
                        @foreach ($types as $t)
                            {{-- A blocked type is DISABLED and labelled, not hidden:
                                 staff can still see the entitlement they hold. --}}
                            <option value="{{ $t->id }}" @disabled($t->blockedReason() !== null)>
                                {{ $t->name }} ({{ $t->code }}){{ $t->blockedReason() ? ' — paid with salary' : '' }}
                            </option>
                        @endforeach
                    </select>
                    @php $chosen = $types->firstWhere('id', (int) $a_type); @endphp
                    @if ($chosen && $chosen->blockedReason())
                        <p class="mt-1 text-[11px] text-warning-700">{{ $chosen->blockedReason() }}</p>
                    @elseif ($chosen && $chosen->description)
                        <p class="help">{{ $chosen->description }}</p>
                    @endif
                    <x-input-error :messages="$errors->get('a_type')" class="mt-1" />
                </div>
            </div>

            @if ($a_employee && $a_type && isset($balances[(int) $a_employee]))
                @php $row = $balances[(int) $a_employee]->firstWhere('type.id', (int) $a_type); @endphp
                @if ($row)
                    <p class="text-xs text-gray-700">
                        <strong>{{ rtrim(rtrim(number_format($row['remaining'], 1), '0'), '.') }}</strong>
                        day(s) left of {{ $row['type']->name }} for {{ $year }} —
                        {{ rtrim(rtrim(number_format($row['entitled'], 1), '0'), '.') }} entitled,
                        {{ rtrim(rtrim(number_format($row['taken'], 1), '0'), '.') }} taken
                        @if ($row['pending'] > 0)
                            , {{ rtrim(rtrim(number_format($row['pending'], 1), '0'), '.') }} pending
                        @endif
                        @if (($row['expired'] ?? 0) > 0)
                            {{-- Shown rather than netted off silently: a day lost to the claim
                                 window is the one that gets argued about. --}}
                            , <span class="text-warning-700">{{ rtrim(rtrim(number_format($row['expired'], 1), '0'), '.') }} expired</span>
                        @endif.
                    </p>
                @endif
            @endif

            {{-- A replacement draws on one named holiday rather than a yearly
                 balance, so the form asks WHICH — the question staff ask back
                 ("which holiday am I still owed for?") only has an answer if
                 the request records it. --}}
            @if ($isReplacement)
                <div>
                    <label class="label">Replacing which public holiday? <span class="text-danger-500">*</span></label>
                    @if ($a_employee === '')
                        <p class="help">Pick the employee first.</p>
                    @elseif ($credits->isEmpty())
                        <div class="alert-warning mt-1 text-xs">
                            No unused replacement days. Every one earned is either already booked or past its
                            claim window — check the
                            @canDo('settings.hr')<a href="{{ route('settings.public-holidays') }}" class="underline font-medium">holiday register</a>@else<span>holiday register</span>@endcanDo.
                        </div>
                    @else
                        <select wire:model.live="a_holiday" class="input">
                            <option value="">— choose a holiday —</option>
                            @foreach ($credits as $credit)
                                <option value="{{ $credit['holiday']->id }}">
                                    {{ $credit['holiday']->date->format('j M Y') }} — {{ $credit['holiday']->name }}{{ $credit['expires_on'] ? ' (take by ' . $credit['expires_on']->format('j M Y') . ')' : '' }}
                                </option>
                            @endforeach
                        </select>
                        <p class="help">{{ $credits->count() }} unused replacement day(s) available.</p>
                    @endif
                    <x-input-error :messages="$errors->get('a_holiday')" class="mt-1" />
                </div>
            @endif

            <div class="grid grid-cols-1 sm:grid-cols-4 gap-3">
                <div>
                    <label class="label">{{ $isReplacement ? 'Day off' : 'From' }} <span class="text-danger-500">*</span></label>
                    <input type="date" wire:model.live="a_start" class="input" />
                    @if ($isReplacement)
                        <p class="help">On or after the holiday, and inside its claim window.</p>
                    @endif
                    <x-input-error :messages="$errors->get('a_start')" class="mt-1" />
                </div>
                @unless ($isReplacement)
                    <div>
                        <label class="label">To <span class="text-danger-500">*</span></label>
                        <input type="date" wire:model.live="a_end" @disabled($a_half) class="input disabled:bg-gray-100" />
                        <x-input-error :messages="$errors->get('a_end')" class="mt-1" />
                    </div>
                @endunless
                <div>
                    <label class="label">Days <span class="text-danger-500">*</span></label>
                    <input type="number" step="0.5" min="0.5" wire:model="a_days" @disabled($isReplacement)
                           class="input disabled:bg-gray-100" />
                    @if ($isReplacement)
                        <p class="help">One holiday, one day back.</p>
                    @else
                        {{-- Suggested from the dates but editable: rest days, public
                             holidays and half days all change the real answer. --}}
                        <p class="help">Suggested from the dates — adjust for rest days.</p>
                    @endif
                    <x-input-error :messages="$errors->get('a_days')" class="mt-1" />
                </div>
                @unless ($isReplacement)
                    <div class="flex flex-col justify-end pb-1 gap-1">
                        <label class="inline-flex items-center gap-2">
                            <input type="checkbox" wire:model.live="a_half" class="rounded border-gray-300 text-brand-600 focus:ring-brand-500" />
                            <span class="text-sm text-gray-700">Half day</span>
                        </label>
                        @if ($a_half)
                            <select wire:model="a_half_period" class="input">
                                <option value="am">Morning</option>
                                <option value="pm">Afternoon</option>
                            </select>
                        @endif
                    </div>
                @endunless
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

    {{-- Grant entitlement --}}
    @if ($showGrant)
        <div class="card p-5 mb-4 space-y-3">
            <h3 class="text-sm font-semibold text-gray-700">Grant entitlement for {{ $year }}</h3>
            <p class="text-xs text-gray-600">
                Overrides the leave type's default for this employee and year. Carried days come from last
                year; an adjustment is anything granted outside the policy figure, so the entitlement itself
                still reads as the policy.
            </p>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="label">Employee <span class="text-danger-500">*</span></label>
                    <select wire:model="g_employee" class="input">
                        <option value="">— Select —</option>
                        @foreach ($employees as $e)
                            <option value="{{ $e->id }}">{{ $e->name }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('g_employee')" class="mt-1" />
                </div>
                <div>
                    <label class="label">Leave type <span class="text-danger-500">*</span></label>
                    <select wire:model="g_type" class="input">
                        <option value="">— Select —</option>
                        @foreach ($types as $t)
                            <option value="{{ $t->id }}">{{ $t->name }} ({{ $t->code }})</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('g_type')" class="mt-1" />
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div>
                    <label class="label">Entitled days <span class="text-danger-500">*</span></label>
                    <input type="number" step="0.5" min="0" wire:model="g_entitled" class="input" />
                    <x-input-error :messages="$errors->get('g_entitled')" class="mt-1" />
                </div>
                <div>
                    <label class="label">Carried from last year</label>
                    <input type="number" step="0.5" min="0" wire:model="g_carried" class="input" />
                    <x-input-error :messages="$errors->get('g_carried')" class="mt-1" />
                </div>
                <div>
                    <label class="label">Adjustment</label>
                    <input type="number" step="0.5" wire:model="g_adjustment" class="input" />
                    <p class="help">Extra days granted outside the policy.</p>
                    <x-input-error :messages="$errors->get('g_adjustment')" class="mt-1" />
                </div>
            </div>

            <div>
                <label class="label">Note</label>
                <input type="text" maxlength="255" wire:model="g_notes" class="input" placeholder="Why this differs from the default" />
            </div>

            <div class="flex justify-end gap-2">
                <button wire:click="$set('showGrant', false)" class="btn-ghost">Cancel</button>
                <button wire:click="grant" class="btn-primary">Save entitlement</button>
            </div>
        </div>
    @endif

    {{-- Requests --}}
    <div class="card overflow-hidden mb-4">
        <div class="overflow-x-auto">
            <table class="table-surface min-w-[960px]">
                <thead>
                    <tr>
                        <th class="px-3 py-2 text-left">Employee</th>
                        <th class="px-2 py-2 text-left">Type</th>
                        <th class="px-2 py-2 text-left">Dates</th>
                        <th class="px-2 py-2 text-right">Days</th>
                        <th class="px-2 py-2 text-left">Reason</th>
                        <th class="px-2 py-2 text-left">Status</th>
                        <th class="px-2 py-2"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($requests as $req)
                        <tr wire:key="lr-{{ $req->id }}" class="hover:bg-gray-50">
                            <td class="px-3 py-2 whitespace-nowrap">
                                <span class="font-medium text-gray-800">{{ $req->employee?->name ?? '—' }}</span>
                                @if ($req->employee?->staff_id)
                                    <span class="block text-[10px] text-gray-500 font-mono">{{ $req->employee->staff_id }}</span>
                                @endif
                            </td>
                            <td class="px-2 py-2">
                                <span class="badge-info">{{ $req->leaveType?->code ?? '—' }}</span>
                                <span class="block text-[10px] text-gray-500">{{ $req->leaveType?->name }}</span>
                                {{-- Which holiday it replaces, so a row that says
                                     "1 day RPH" can be reconciled against the register. --}}
                                @if ($req->publicHoliday)
                                    <span class="block text-[10px] text-gray-600 mt-0.5">
                                        for {{ $req->publicHoliday->name }}, {{ $req->publicHoliday->date->format('j M') }}
                                    </span>
                                @endif
                            </td>
                            <td class="px-2 py-2 text-xs text-gray-700 whitespace-nowrap">
                                {{ $req->start_date->format('d M Y') }}
                                @if (! $req->start_date->isSameDay($req->end_date))
                                    – {{ $req->end_date->format('d M Y') }}
                                @endif
                                @if ($req->is_half_day)
                                    <span class="block text-[10px] text-gray-500">half day ({{ $req->half_day_period }})</span>
                                @endif
                            </td>
                            <td class="px-2 py-2 text-right tabular-nums text-gray-700">
                                {{ rtrim(rtrim(number_format((float) $req->days, 1), '0'), '.') }}
                            </td>
                            <td class="px-2 py-2 text-xs text-gray-600 max-w-[220px] truncate" title="{{ $req->reason }}">
                                {{ $req->reason ?: '—' }}
                            </td>
                            <td class="px-2 py-2">
                                @php
                                    $tone = match ($req->status) {
                                        \App\Models\LeaveRequest::APPROVED  => 'badge-success',
                                        \App\Models\LeaveRequest::REJECTED  => 'badge-danger',
                                        \App\Models\LeaveRequest::CANCELLED => 'badge-info',
                                        default                             => 'badge-warning',
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
                                               class="input text-xs w-40 inline-block" />
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
                                            wire:confirm="Cancel this leave? The days go back on the balance."
                                            class="ml-3 text-xs font-medium text-gray-600 hover:text-gray-900">Cancel</button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-3 py-6 text-center text-sm text-gray-600">
                            No leave {{ $statusFilter ? \App\Models\LeaveRequest::STATUSES[$statusFilter] ?? '' : '' }} to show.
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-4 py-2 border-t border-gray-100">{{ $requests->links() }}</div>
    </div>

    {{-- Balances --}}
    <div class="card overflow-hidden">
        <div class="px-4 py-3 border-b border-gray-100">
            <h3 class="text-sm font-semibold text-gray-700">Balances — {{ $year }}</h3>
            <p class="text-xs text-gray-600 mt-0.5">
                Pending requests are already deducted, so what is shown is what can still be booked.
            </p>
        </div>
        <div class="overflow-x-auto">
            <table class="table-surface min-w-[820px]">
                <thead>
                    <tr>
                        <th class="px-3 py-2 text-left">Employee</th>
                        @foreach ($types as $t)
                            <th class="px-2 py-2 text-right" title="{{ $t->name }}">{{ $t->code }}</th>
                        @endforeach
                        @if ($canApprove)<th class="px-2 py-2"></th>@endif
                    </tr>
                </thead>
                <tbody>
                    @forelse ($balances as $employeeId => $rows)
                        @php $emp = $employees->firstWhere('id', $employeeId); @endphp
                        <tr wire:key="bal-{{ $employeeId }}" class="hover:bg-gray-50">
                            <td class="px-3 py-1.5 font-medium text-gray-800 whitespace-nowrap">{{ $emp?->name }}</td>
                            @foreach ($types as $t)
                                @php $row = $rows->firstWhere('type.id', $t->id); @endphp
                                <td class="px-2 py-1.5 text-right tabular-nums {{ $row && $row['remaining'] <= 0 ? 'text-gray-400' : 'text-gray-700' }}">
                                    @if ($row)
                                        {{ rtrim(rtrim(number_format($row['remaining'], 1), '0'), '.') }}
                                        <span class="block text-[10px] text-gray-500">
                                            of {{ rtrim(rtrim(number_format($row['entitled'], 1), '0'), '.') }}
                                            @unless ($row['granted'])<span title="Using the leave type's default — no entitlement set for this person">*</span>@endunless
                                        </span>
                                    @else — @endif
                                </td>
                            @endforeach
                            @if ($canApprove)
                                <td class="px-2 py-1.5 text-right">
                                    <button wire:click="openGrant({{ $employeeId }})"
                                            class="text-xs font-medium text-brand-600 hover:text-brand-800 whitespace-nowrap">Grant</button>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr><td colspan="{{ $types->count() + ($canApprove ? 2 : 1) }}" class="px-3 py-6 text-center text-sm text-gray-600">
                            No active employees in this view.
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <p class="px-4 py-2 text-[11px] text-gray-600 border-t border-gray-100">
            * using the leave type's default, because no entitlement has been set for that person and year.
            Showing the first 50 employees in this view.
        </p>
    </div>
</div>
