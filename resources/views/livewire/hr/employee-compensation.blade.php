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

    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        <div class="flex items-start gap-3">
            <a data-back href="{{ route('hr.compensation') }}" title="Back to Compensation"
               class="mt-0.5 p-1.5 rounded-control text-gray-600 hover:text-gray-900 hover:bg-gray-100 transition">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <div>
                <p class="text-xs text-gray-600">HR / Compensation</p>
                <h2 class="text-lg font-semibold text-gray-700 mt-1">{{ $employee->name }}</h2>
            </div>
        </div>
        <a href="{{ route('hr.employees.edit', $employee->id) }}" class="btn-secondary">Employee record</a>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        {{-- Basic salary --}}
        <div class="card p-5">
            <p class="text-xs font-medium uppercase tracking-wider text-gray-500">Basic salary</p>
            <p class="mt-2 text-2xl font-semibold tabular-nums text-gray-900">
                {{ $employee->basic_salary !== null ? number_format((float) $employee->basic_salary, 2) : '—' }}
                <span class="text-xs font-normal text-gray-500">{{ \App\Models\Employee::PAY_TYPE_SUFFIXES[$employee->pay_type] ?? '' }}</span>
            </p>
            @php $rate = $settings->hourlyRate($employee->basic_salary !== null ? (float) $employee->basic_salary : null, $employee->pay_type); @endphp
            <p class="mt-1 text-xs text-gray-600">
                @if ($rate !== null)
                    Hourly rate {{ number_format($rate, 2) }} — used to price approved OT.
                @else
                    No salary on file, so overtime cannot be priced.
                @endif
            </p>
            {{-- Changed through a dated, approved revision, never inline: that
                 is the point of keeping a history. --}}
            <button wire:click="openRevision" class="btn-primary mt-4">
                {{ $canApprove ? 'Change salary' : 'Request a change' }}
            </button>
        </div>

        {{-- Allowances and deductions --}}
        <div class="card p-5 lg:col-span-2">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h3 class="text-sm font-semibold text-gray-700">Allowances &amp; deductions</h3>
                    <p class="text-xs text-gray-500">Dated, so a past month reports what that month was owed.</p>
                </div>
                <button wire:click="openAssign" @disabled($components->isEmpty()) class="btn-secondary disabled:opacity-50 disabled:cursor-not-allowed">+ Add</button>
            </div>

            @if ($components->isEmpty())
                <p class="mt-4 text-xs text-gray-500">
                    No pay components yet — add them under
                    <a href="{{ route('settings.pay-components') }}" class="text-brand-600 hover:underline">Settings → Pay Components</a>.
                </p>
            @elseif ($assignments->isEmpty())
                <p class="mt-4 text-sm text-gray-600">Nothing assigned. Basic salary only.</p>
            @else
                <div class="mt-4 divide-y divide-gray-50 border border-gray-100 rounded-lg">
                    @foreach ($assignments as $a)
                        <div wire:key="assign-{{ $a->id }}" class="px-3 py-2 flex flex-wrap items-center gap-x-3 gap-y-1 {{ $a->isCurrent() ? '' : 'opacity-60' }}">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $a->component?->isDeduction() ? 'bg-danger-100 text-danger-700' : 'bg-success-100 text-success-700' }}">
                                {{ $a->component?->isDeduction() ? 'Deduction' : 'Allowance' }}
                            </span>
                            <span class="text-sm font-medium text-gray-800">{{ $a->component?->name ?? 'Removed component' }}</span>
                            <span class="text-sm tabular-nums text-gray-700">
                                {{ number_format((float) $a->amount, 2) }}{{ $a->component?->calculation === 'percent_basic' ? '% of basic' : '' }}
                            </span>
                            <span class="text-[11px] text-gray-500">
                                {{ $a->effective_from->format('d M Y') }} →
                                {{ $a->effective_to?->format('d M Y') ?? 'ongoing' }}
                            </span>
                            @unless ($a->isCurrent())
                                <span class="text-[11px] text-gray-500 italic">not in force today</span>
                            @endunless
                            <div class="ml-auto flex items-center gap-1">
                                <button wire:click="editAssignment({{ $a->id }})" class="text-brand-500 hover:text-brand-700 p-1" title="Edit">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                </button>
                                <button wire:click="removeAssignment({{ $a->id }})" wire:confirm="Remove this from the employee?" class="text-danger-400 hover:text-danger-600 p-1" title="Remove">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M1 7h22M9 7V4a2 2 0 012-2h2a2 2 0 012 2v3"/></svg>
                                </button>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    {{-- This month, end to end --}}
    @if ($thisMonth)
        <div class="card overflow-hidden mt-4">
            <div class="px-4 py-3 border-b border-gray-100 flex flex-wrap items-center justify-between gap-2">
                <h3 class="text-sm font-semibold text-gray-700">{{ $monthLabel }} — estimated pay</h3>
                <div class="flex items-center gap-2">
                    @if ($statutory->ratesUnconfirmed())
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-warning-100 text-warning-700">
                            statutory rates unconfirmed
                        </span>
                    @endif
                    {{-- Edited on the employee record, which is where these
                         facts belong; this screen is for what is owed. --}}
                    <a href="{{ route('hr.employees.edit', $employee->id) }}" class="btn-secondary">Statutory details</a>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 divide-y sm:divide-y-0 sm:divide-x divide-gray-100">
                <div class="p-4">
                    <p class="text-xs font-medium uppercase tracking-wider text-gray-500 mb-2">Earnings</p>
                    <dl class="space-y-1 text-sm">
                        <div class="flex justify-between"><dt class="text-gray-600">Basic</dt><dd class="tabular-nums text-gray-800">{{ number_format($thisMonth['basic'], 2) }}</dd></div>
                        @foreach ($thisMonth['components']->where('kind', 'allowance') as $c)
                            <div class="flex justify-between"><dt class="text-gray-600 pl-3">{{ $c['name'] }}</dt><dd class="tabular-nums text-gray-800">{{ number_format($c['amount'], 2) }}</dd></div>
                        @endforeach
                        <div class="flex justify-between"><dt class="text-gray-600">Overtime ({{ number_format($thisMonth['ot_hours'], 2) }} hrs)</dt><dd class="tabular-nums text-gray-800">{{ number_format($thisMonth['ot_amount'], 2) }}</dd></div>
                        @foreach ($thisMonth['components']->where('kind', 'deduction') as $c)
                            <div class="flex justify-between"><dt class="text-danger-600 pl-3">{{ $c['name'] }}</dt><dd class="tabular-nums text-danger-600">{{ number_format($c['amount'], 2) }}</dd></div>
                        @endforeach
                        <div class="flex justify-between pt-2 mt-2 border-t border-gray-100 font-semibold">
                            <dt class="text-gray-700">Gross</dt><dd class="tabular-nums text-gray-900">{{ number_format($thisMonth['gross'], 2) }}</dd>
                        </div>
                    </dl>
                </div>

                <div class="p-4">
                    <p class="text-xs font-medium uppercase tracking-wider text-gray-500 mb-2">Statutory (employee)</p>
                    @php $st = $thisMonth['statutory']; @endphp
                    <dl class="space-y-1 text-sm">
                        <div class="flex justify-between"><dt class="text-gray-600">EPF</dt><dd class="tabular-nums text-gray-800">{{ number_format($st['epf_employee'], 2) }}</dd></div>
                        <div class="flex justify-between"><dt class="text-gray-600">SOCSO</dt><dd class="tabular-nums text-gray-800">{{ number_format($st['socso_employee'], 2) }}</dd></div>
                        <div class="flex justify-between"><dt class="text-gray-600">EIS</dt><dd class="tabular-nums text-gray-800">{{ number_format($st['eis_employee'], 2) }}</dd></div>
                        <div class="flex justify-between"><dt class="text-gray-600">PCB</dt><dd class="tabular-nums text-gray-800">{{ number_format($st['pcb'], 2) }}</dd></div>
                        <div class="flex justify-between pt-2 mt-2 border-t border-gray-100 font-semibold">
                            <dt class="text-gray-700">Net pay</dt><dd class="tabular-nums text-gray-900">{{ number_format($thisMonth['net'], 2) }}</dd>
                        </div>
                    </dl>

                    <p class="text-xs font-medium uppercase tracking-wider text-gray-500 mt-4 mb-2">Employer contributions</p>
                    <dl class="space-y-1 text-sm">
                        <div class="flex justify-between"><dt class="text-gray-600">EPF</dt><dd class="tabular-nums text-gray-800">{{ number_format($st['epf_employer'], 2) }}</dd></div>
                        <div class="flex justify-between"><dt class="text-gray-600">SOCSO</dt><dd class="tabular-nums text-gray-800">{{ number_format($st['socso_employer'], 2) }}</dd></div>
                        <div class="flex justify-between"><dt class="text-gray-600">EIS</dt><dd class="tabular-nums text-gray-800">{{ number_format($st['eis_employer'], 2) }}</dd></div>
                        <div class="flex justify-between pt-2 mt-2 border-t border-gray-100 font-semibold">
                            <dt class="text-gray-700">Total cost to company</dt><dd class="tabular-nums text-gray-900">{{ number_format($thisMonth['employer_cost'], 2) }}</dd>
                        </div>
                    </dl>
                </div>
            </div>

            @if (! empty($st['notes']))
                {{-- Said on the figure itself, not only in the settings screen. --}}
                <div class="px-4 py-3 bg-warning-50 border-t border-warning-200">
                    <ul class="text-xs text-warning-800 space-y-0.5 list-disc list-inside">
                        @foreach ($st['notes'] as $note)
                            <li>{{ $note }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="px-4 py-3 border-t border-gray-100 text-xs text-gray-600">
                EPF wages {{ number_format($thisMonth['epf_wages'], 2) }} ·
                SOCSO/EIS wages {{ number_format($thisMonth['socso_wages'], 2) }} ·
                taxable {{ number_format($thisMonth['taxable_pay'], 2) }}.
                Each allowance decides what it counts towards, on
                <a href="{{ route('settings.pay-components') }}" class="text-brand-600 hover:underline">Pay Components</a>.
            </div>
        </div>
    @endif

    {{-- Salary history --}}
    <div class="card overflow-hidden mt-4">
        <div class="px-4 py-3 border-b border-gray-100">
            <h3 class="text-sm font-semibold text-gray-700">Salary history</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="table-surface min-w-[760px]">
                <thead>
                    <tr>
                        <th class="px-4 py-3 text-left">Effective</th>
                        <th class="px-4 py-3 text-right">From</th>
                        <th class="px-4 py-3 text-right">To</th>
                        <th class="px-4 py-3 text-left">Reason</th>
                        <th class="px-4 py-3 text-center">Status</th>
                        <th class="px-4 py-3 text-left">Trail</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($revisions as $r)
                        <tr wire:key="rev-{{ $r->id }}" class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-sm text-gray-700 whitespace-nowrap">{{ $r->effective_on->format('d M Y') }}</td>
                            <td class="px-4 py-3 text-right tabular-nums text-gray-600">
                                {{ $r->from_amount !== null ? number_format((float) $r->from_amount, 2) : '—' }}
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums font-medium text-gray-800">
                                {{ number_format((float) $r->to_amount, 2) }}
                                <span class="text-[10px] text-gray-500">{{ \App\Models\Employee::PAY_TYPE_SUFFIXES[$r->to_pay_type] ?? '' }}</span>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-600">{{ $r->reason ?? '—' }}</td>
                            <td class="px-4 py-3 text-center">
                                @php
                                    $tone = match ($r->status) {
                                        \App\Models\SalaryRevision::APPROVED => 'bg-success-100 text-success-700',
                                        \App\Models\SalaryRevision::REJECTED => 'bg-danger-100 text-danger-700',
                                        default => 'bg-warning-100 text-warning-700',
                                    };
                                @endphp
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $tone }}">
                                    {{ ucfirst($r->status) }}
                                </span>
                                @if ($r->status === \App\Models\SalaryRevision::APPROVED && ! $r->applied_at)
                                    <div class="text-[10px] text-gray-500 mt-0.5">takes effect {{ $r->effective_on->format('d M') }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-[11px] text-gray-500">
                                requested by {{ $r->requester?->name ?? '—' }}
                                @if ($r->reviewer)
                                    <div>{{ $r->status === \App\Models\SalaryRevision::REJECTED ? 'rejected' : 'approved' }} by {{ $r->reviewer->name }}</div>
                                @endif
                                @if ($r->review_note)
                                    <div class="italic">{{ $r->review_note }}</div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-8 text-center text-gray-600">No salary changes recorded.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Assign modal --}}
    @if ($showAssign)
        @teleport('body')
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 overflow-y-auto" wire:click.self="$set('showAssign', false)">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-md mx-4 p-6 my-8">
                <h3 class="text-base font-semibold text-gray-800 mb-4">{{ $assignmentId ? 'Edit' : 'Add' }} allowance or deduction</h3>
                <form wire:submit.prevent="saveAssignment" class="space-y-3">
                    <div>
                        <label class="text-xs font-semibold text-gray-600">Component <span class="text-danger-500">*</span></label>
                        <select wire:model.live="a_component_id" class="mt-1 w-full text-sm rounded-lg border-gray-300">
                            <option value="">— Select —</option>
                            @foreach ($components as $c)
                                <option value="{{ $c->id }}">{{ $c->name }} ({{ \App\Models\PayComponent::KINDS[$c->kind] }})</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('a_component_id')" class="mt-1" />
                    </div>
                    @php $picked = $components->firstWhere('id', (int) $a_component_id); @endphp
                    <div>
                        <label class="text-xs font-semibold text-gray-600">
                            {{ $picked?->calculation === 'percent_basic' ? 'Percent of basic salary' : 'Amount' }}
                            <span class="text-danger-500">*</span>
                        </label>
                        <input type="number" step="0.01" min="0" wire:model="a_amount" class="mt-1 w-full text-sm rounded-lg border-gray-300"
                               placeholder="{{ $picked?->default_amount !== null ? (float) $picked->default_amount : '0.00' }}" />
                        <x-input-error :messages="$errors->get('a_amount')" class="mt-1" />
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="text-xs font-semibold text-gray-600">From <span class="text-danger-500">*</span></label>
                            <input type="date" wire:model="a_from" class="mt-1 w-full text-sm rounded-lg border-gray-300" />
                            <x-input-error :messages="$errors->get('a_from')" class="mt-1" />
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-gray-600">To</label>
                            <input type="date" wire:model="a_to" class="mt-1 w-full text-sm rounded-lg border-gray-300" />
                            <p class="mt-1 text-[11px] text-gray-500">Blank = ongoing.</p>
                            <x-input-error :messages="$errors->get('a_to')" class="mt-1" />
                        </div>
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-gray-600">Note</label>
                        <input type="text" wire:model="a_notes" class="mt-1 w-full text-sm rounded-lg border-gray-300" />
                        <x-input-error :messages="$errors->get('a_notes')" class="mt-1" />
                    </div>
                    <div class="flex justify-end gap-2 pt-3 border-t border-gray-100">
                        <button type="button" wire:click="$set('showAssign', false)" class="btn-secondary">Cancel</button>
                        <button type="submit" class="btn-primary">Save</button>
                    </div>
                </form>
            </div>
        </div>
        @endteleport
    @endif

    {{-- Statutory details moved onto the employee record's own Statutory tab.
         They are facts about the person, not a transaction on this screen, and
         a tall form in an overlay was getting clipped at the top with no way to
         scroll to its heading. One door onto the data, on the record it
         describes. --}}

    {{-- Salary revision modal --}}
    @if ($showRevision)
        @teleport('body')
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 overflow-y-auto" wire:click.self="$set('showRevision', false)">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-md mx-4 p-6 my-8">
                <h3 class="text-base font-semibold text-gray-800 mb-1">{{ $canApprove ? 'Change salary' : 'Request a salary change' }}</h3>
                <p class="text-xs text-gray-600 mb-4">
                    @if ($canApprove)
                        Recorded with its effective date and applied on that date.
                    @else
                        Goes to someone with approval rights before it takes effect.
                    @endif
                </p>
                <form wire:submit.prevent="submitRevision" class="space-y-3">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="text-xs font-semibold text-gray-600">New basic salary <span class="text-danger-500">*</span></label>
                            <input type="number" step="0.01" min="0" wire:model="r_amount" class="mt-1 w-full text-sm rounded-lg border-gray-300" />
                            <x-input-error :messages="$errors->get('r_amount')" class="mt-1" />
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-gray-600">Pay type <span class="text-danger-500">*</span></label>
                            <select wire:model="r_pay_type" class="mt-1 w-full text-sm rounded-lg border-gray-300">
                                @foreach (\App\Models\Employee::PAY_TYPES as $v => $l)
                                    <option value="{{ $v }}">{{ $l }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('r_pay_type')" class="mt-1" />
                        </div>
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-gray-600">Effective on <span class="text-danger-500">*</span></label>
                        <input type="date" wire:model="r_effective_on" class="mt-1 w-full text-sm rounded-lg border-gray-300" />
                        <x-input-error :messages="$errors->get('r_effective_on')" class="mt-1" />
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-gray-600">Reason</label>
                        <input type="text" wire:model="r_reason" class="mt-1 w-full text-sm rounded-lg border-gray-300" placeholder="e.g. Annual review, promotion" />
                        <x-input-error :messages="$errors->get('r_reason')" class="mt-1" />
                    </div>
                    <div class="flex justify-end gap-2 pt-3 border-t border-gray-100">
                        <button type="button" wire:click="$set('showRevision', false)" class="btn-secondary">Cancel</button>
                        <button type="submit" class="btn-primary">{{ $canApprove ? 'Save' : 'Submit' }}</button>
                    </div>
                </form>
            </div>
        </div>
        @endteleport
    @endif
</div>
