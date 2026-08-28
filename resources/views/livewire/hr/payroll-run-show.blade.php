<div>
    <x-page-header :title="'Payroll — ' . $run->periodLabel()" eyebrow="HR / Payroll">
        <x-slot:actions>
            <a href="{{ route('hr.payroll') }}" wire:navigate class="btn-secondary">All runs</a>
            <a href="{{ route('hr.payroll.payslips', $run) }}" class="btn-secondary">Payslips (PDF)</a>
            @if ($run->isEditable())
                <button wire:click="regenerate" wire:loading.attr="disabled" class="btn-secondary">
                    <span wire:loading.remove wire:target="regenerate">Regenerate</span>
                    <span wire:loading wire:target="regenerate">Regenerating…</span>
                </button>
            @endif
            @if ($canApprove && ! $run->isApproved())
                <button wire:click="$set('showApprove', true)" class="btn-primary">Approve</button>
            @endif
            @if ($run->isApproved())
                <button wire:click="$set('showEmail', true)" class="btn-secondary">Email payslips</button>
            @endif
            @if ($canApprove && $run->status === \App\Models\PayrollRun::APPROVED)
                <button wire:click="$set('showPaid', true)" class="btn-primary">Mark paid</button>
            @endif
        </x-slot:actions>
    </x-page-header>

    @if (session('success'))
        <div class="alert-success mb-4">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="alert-danger mb-4">{{ session('error') }}</div>
    @endif

    {{-- Where the run stands --}}
    <div class="card p-4 mb-4">
        <div class="flex flex-wrap items-center gap-x-6 gap-y-2 text-sm">
            <div>
                <span class="text-xs text-gray-500 block">Reference</span>
                <span class="font-mono text-gray-800">{{ $run->reference }}</span>
            </div>
            <div>
                <span class="text-xs text-gray-500 block">Period covered</span>
                <span class="text-gray-800">{{ $run->rangeLabel() }}</span>
            </div>
            <div>
                <span class="text-xs text-gray-500 block">Scope</span>
                <span class="text-gray-800">{{ $run->scopeLabel() }}</span>
                {{-- A segmented run pays part of the company, so the totals on
                     this page are part of the month's payroll and not all of
                     it. Said once, here, rather than left to be inferred from a
                     staff count that looks low. --}}
                @if ($run->isSegmented())
                    <span class="block text-[11px] text-gray-500 mt-0.5">
                        Covers this segment only — other staff are paid on their own run.
                    </span>
                @endif
            </div>
            <div>
                <span class="text-xs text-gray-500 block">Status</span>
                @php
                    $tone = match ($run->status) {
                        \App\Models\PayrollRun::PAID     => 'badge-success',
                        \App\Models\PayrollRun::APPROVED => 'badge-info',
                        default                          => 'badge-warning',
                    };
                @endphp
                <span class="{{ $tone }}">{{ $run->statusLabel() }}</span>
            </div>
            @if ($run->generatedBy)
                <div>
                    <span class="text-xs text-gray-500 block">Generated</span>
                    <span class="text-gray-700">{{ $run->generated_at?->format('d M Y, H:i') }} by {{ $run->generatedBy->name }}</span>
                </div>
            @endif
            @if ($run->approvedBy)
                <div>
                    <span class="text-xs text-gray-500 block">Approved</span>
                    <span class="text-gray-700">{{ $run->approved_at?->format('d M Y, H:i') }} by {{ $run->approvedBy->name }}</span>
                </div>
            @endif
            @if ($run->payment_date)
                <div>
                    <span class="text-xs text-gray-500 block">Paid</span>
                    <span class="text-gray-700">{{ $run->payment_date->format('d M Y') }}</span>
                </div>
            @endif
        </div>
        @if ($run->notes)
            <p class="mt-3 pt-3 border-t border-gray-100 text-sm text-gray-700">{{ $run->notes }}</p>
        @endif
    </div>

    {{-- What is not ready. Said BEFORE approval, because that is while it is
         still fixable; kept visible afterwards as a record of what was known. --}}
    @if ($warnings)
        <div class="alert-warning mb-4">
            <p class="font-medium text-sm">Check before approving</p>
            <ul class="mt-1 list-disc list-inside text-sm space-y-0.5">
                @foreach ($warnings as $warning)
                    <li>{{ $warning }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- ── One-off adjustments ──────────────────────────────────────────────
         Above the figures and above Approve, deliberately: these are the
         corrections somebody has to see before signing the run off, and a
         panel below a hundred-row table is a panel nobody reads.

         Listed even once the run is locked, because a payslip carrying a
         RM500 recovery has to remain explainable after the fact. --}}
    @if ($canAdjust || $adjustments->isNotEmpty())
        <div class="card p-4 mb-4">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h3 class="text-sm font-semibold text-gray-700">Adjustments</h3>
                    <p class="text-xs text-gray-600 mt-0.5">
                        One-off corrections on this run — recovering an overpayment, settling a
                        shortfall from a previous month. They are re-applied every time the run is
                        regenerated, and are itemised on the payslip.
                    </p>
                </div>
                @if ($canAdjust)
                    <div class="flex items-center gap-2">
                        <button wire:click="openAdjust" class="btn-secondary">Add adjustment</button>
                        <button wire:click="openBulk" class="btn-secondary">Add by days (all staff)</button>
                    </div>
                @endif
            </div>

            @if ($adjustments->isNotEmpty())
                <div class="mt-3 overflow-x-auto">
                    <table class="table-surface min-w-[720px]">
                        <thead>
                            <tr>
                                <th class="px-3 py-2 text-left">Employee</th>
                                <th class="px-3 py-2 text-left">Description</th>
                                <th class="px-2 py-2 text-left">Statutory</th>
                                <th class="px-2 py-2 text-right">Amount</th>
                                @if ($canAdjust)<th class="px-2 py-2"></th>@endif
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($adjustments as $a)
                                <tr wire:key="adj-{{ $a->id }}">
                                    <td class="px-3 py-2 text-sm text-gray-800">{{ $a->employee?->name ?? '—' }}</td>
                                    <td class="px-3 py-2 text-sm text-gray-700">
                                        {{ $a->label }}
                                        @if ($a->notes)
                                            <span class="block text-[11px] text-gray-500">{{ $a->notes }}</span>
                                        @endif
                                        @if ($a->createdBy)
                                            <span class="block text-[10px] text-gray-500">added by {{ $a->createdBy->name }}</span>
                                        @endif
                                    </td>
                                    <td class="px-2 py-2">
                                        <span class="{{ $a->affects_statutory ? 'badge-info' : 'badge-warning' }}">
                                            {{ $a->affects_statutory ? 'Counts as wages' : 'After statutory' }}
                                        </span>
                                    </td>
                                    <td class="px-2 py-2 text-right tabular-nums font-medium {{ $a->isAddition() ? 'text-success-700' : 'text-danger-700' }}">
                                        {{ $a->isAddition() ? '+' : '−' }}{{ number_format((float) $a->amount, 2) }}
                                    </td>
                                    @if ($canAdjust)
                                        <td class="px-2 py-2 text-right whitespace-nowrap">
                                            <button wire:click="editAdjustment({{ $a->id }})"
                                                    class="text-xs font-medium text-brand-600 hover:text-brand-800">Edit</button>
                                            <button wire:click="removeAdjustment({{ $a->id }})"
                                                    data-confirm-delete="Remove this adjustment and recalculate the run?"
                                                    class="ml-3 text-xs font-medium text-danger-600 hover:text-danger-800">Remove</button>
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @elseif ($canAdjust)
                <p class="mt-3 text-xs text-gray-500">None on this run.</p>
            @endif
        </div>
    @endif

    {{-- The same correction across the whole run, priced per head.

         Separate from the single-employee form above rather than a mode of
         it: this one asks for DAYS and produces a different amount for every
         person, and folding that into a form whose central field is an amount
         in ringgit would make both harder to read. --}}
    @if ($showBulk)
        @php
            $isDeduction = $bulk_direction === \App\Models\PayrollRunAdjustment::DEDUCTION;
        @endphp
        <div class="card p-4 mb-4">
            <h3 class="text-sm font-semibold text-gray-700">Add or deduct days across this run</h3>
            <p class="text-xs text-gray-600 mt-0.5 mb-3">
                One decision, priced per person — a day of someone's salary is their own salary.
                This writes an ordinary adjustment for each employee you tick, so any one of them
                can be edited or removed afterwards.
            </p>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                <div>
                    <label class="label">Direction</label>
                    <select wire:model.live="bulk_direction" class="input">
                        @foreach (\App\Models\PayrollRunAdjustment::DIRECTIONS as $dv => $dl)
                            <option value="{{ $dv }}">{{ $dl }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('bulk_direction')" class="mt-1" />
                </div>

                <div>
                    <label class="label">Number of days</label>
                    <input type="number" step="0.5" min="0.5" max="31" wire:model.live="bulk_days" class="input" />
                    <p class="help">Half days allowed.</p>
                    <x-input-error :messages="$errors->get('bulk_days')" class="mt-1" />
                </div>

                {{-- The divisor is a policy choice, not a constant: 26 is the
                     ordinary rate of pay the Employment Act uses, while the
                     calendar length of the period is what pro-rating a joiner
                     divides by. They differ by about a fifth, so the one used
                     is chosen here and recorded on every row it writes. --}}
                <div>
                    <label class="label">A day means</label>
                    <select wire:model.live="bulk_basis" class="input">
                        @foreach ($bulkBases as $bv => $bl)
                            <option value="{{ $bv }}">{{ $bl }}</option>
                        @endforeach
                    </select>
                    <p class="help">Monthly salary ÷ {{ $bulkDivisor[0] }} ({{ $bulkDivisor[1] }}).</p>
                    <x-input-error :messages="$errors->get('bulk_basis')" class="mt-1" />
                </div>

                <div>
                    <label class="label">Description</label>
                    <input type="text" maxlength="120" wire:model="bulk_label" class="input"
                           placeholder="e.g. Company shutdown 12–13 Aug" />
                    <p class="help">Shown on every payslip it touches.</p>
                    <x-input-error :messages="$errors->get('bulk_label')" class="mt-1" />
                </div>
            </div>

            <div class="mt-3 grid grid-cols-1 lg:grid-cols-2 gap-3">
                <div class="p-3 bg-gray-50 rounded-lg border border-gray-100">
                    <label class="flex items-start gap-2">
                        <input type="checkbox" wire:model.live="bulk_include_allowances"
                               class="mt-0.5 rounded border-gray-300 text-brand-600 focus:ring-brand-500">
                        <span class="text-sm text-gray-800">Include fixed allowances</span>
                    </label>
                    <p class="mt-1 text-[11px] text-gray-600">
                        @if ($bulk_include_allowances)
                            A day is <strong>basic plus this run's allowances</strong>, both cut by the
                            same divisor. The allowance figure comes off each line, so a part month that
                            already reduced it stays reduced.
                        @else
                            A day is <strong>basic salary only</strong>. Allowances are untouched.
                        @endif
                    </p>
                </div>

                <div class="p-3 bg-gray-50 rounded-lg border border-gray-100">
                    <label class="flex items-start gap-2">
                        <input type="checkbox" wire:model.live="bulk_affects_statutory"
                               class="mt-0.5 rounded border-gray-300 text-brand-600 focus:ring-brand-500">
                        <span class="text-sm text-gray-800">Counts as wages this month</span>
                    </label>
                    <p class="mt-1 text-[11px] text-gray-600">
                        @if ($bulk_affects_statutory)
                            EPF, SOCSO, EIS and PCB are recomputed on the adjusted figure.
                        @else
                            Applied after the statutory deductions, so it changes take-home only.
                        @endif
                    </p>
                </div>
            </div>

            <div class="mt-3">
                <label class="label">Internal note (optional)</label>
                <input type="text" maxlength="180" wire:model="bulk_notes" class="input"
                       placeholder="Not shown on the payslip" />
                <p class="help">The working — days, rate and basis — is recorded on every row regardless.</p>
                <x-input-error :messages="$errors->get('bulk_notes')" class="mt-1" />
            </div>

            {{-- Who it lands on. Everybody is ticked when the panel opens,
                 because "all of them" is the case this exists for. --}}
            <div class="mt-4">
                <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
                    <div class="text-sm font-semibold text-gray-700">
                        Employees
                        <span class="ml-1 text-xs font-normal text-gray-600">
                            {{ count($bulk_selected) }} of {{ $bulkCandidates->count() }} selected
                        </span>
                    </div>
                    <div class="flex items-center gap-3">
                        <button wire:click="selectAllBulk" class="text-xs font-medium text-brand-600 hover:text-brand-800">Select all</button>
                        <button wire:click="selectNoneBulk" class="text-xs font-medium text-gray-600 hover:text-gray-800">Clear</button>
                    </div>
                </div>

                {{-- Daily and hourly staff are absent from a DEDUCTION on
                     purpose, and it is said rather than left as a shorter
                     list — a name missing without explanation reads as a bug
                     in the run, not as a rule. --}}
                @if ($isDeduction && $lines->count() > $bulkCandidates->count())
                    <p class="mb-2 text-[11px] text-warning-700">
                        {{ $lines->count() - $bulkCandidates->count() }} daily or hourly employee(s) are not
                        listed: their pay already follows the days and hours on the grid, so deducting a day
                        here would take the same absence off twice. They can still receive an addition.
                    </p>
                @endif

                <x-input-error :messages="$errors->get('bulk_selected')" class="mb-2" />

                @if ($bulkCandidates->isEmpty())
                    <p class="text-xs text-gray-500">Nobody on this run can take this adjustment.</p>
                @else
                    @php $preview = $bulkPreview['rows']->keyBy('employee_id'); @endphp
                    <div class="border border-gray-200 rounded-lg max-h-72 overflow-y-auto">
                        <table class="table-surface w-full">
                            <thead class="sticky top-0">
                                <tr>
                                    <th class="px-3 py-2 w-8"></th>
                                    <th class="px-3 py-2 text-left">Employee</th>
                                    <th class="px-2 py-2 text-right">Per day</th>
                                    <th class="px-2 py-2 text-right">{{ $bulk_days ?: 0 }} day(s)</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($bulkCandidates as $l)
                                    @php $row = $preview[$l->employee_id] ?? null; @endphp
                                    <tr wire:key="bulk-{{ $l->employee_id }}">
                                        <td class="px-3 py-1.5">
                                            <input type="checkbox" value="{{ $l->employee_id }}"
                                                   wire:model.live="bulk_selected"
                                                   class="rounded border-gray-300 text-brand-600 focus:ring-brand-500">
                                        </td>
                                        <td class="px-3 py-1.5 text-sm text-gray-800">
                                            {{ $l->employee_name }}
                                            @if ($l->isHourly() || $l->isDaily())
                                                <span class="ml-1 badge-warning">{{ $l->isHourly() ? 'hourly' : 'daily' }}</span>
                                            @endif
                                        </td>
                                        <td class="px-2 py-1.5 text-right text-xs tabular-nums text-gray-600">
                                            @if ($row)
                                                {{ number_format($row['day_rate'] + $row['allowance'], 2) }}
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td class="px-2 py-1.5 text-right text-sm tabular-nums font-medium {{ $isDeduction ? 'text-danger-700' : 'text-success-700' }}">
                                            @if ($row)
                                                {{ $isDeduction ? '−' : '+' }}{{ number_format($row['amount'], 2) }}
                                            @else
                                                <span class="text-gray-400">—</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            {{-- The confirmation. Forty adjustments from one button press is
                 not something to discover afterwards, so the total and anyone
                 who cannot be priced are stated before it is pressed. --}}
            <div class="mt-3 p-3 rounded-lg border {{ $isDeduction ? 'bg-danger-50 border-danger-100' : 'bg-success-50 border-success-100' }}">
                <p class="text-sm text-gray-800">
                    <strong>{{ $bulkPreview['rows']->count() }}</strong> employee(s),
                    <strong>{{ $isDeduction ? '−' : '+' }}RM{{ number_format($bulkPreview['total'], 2) }}</strong> in total.
                </p>
                @if ($bulkPreview['skipped']->isNotEmpty())
                    <p class="mt-1 text-[11px] text-warning-800">
                        Skipped {{ $bulkPreview['skipped']->count() }}:
                        {{ $bulkPreview['skipped']->map(fn ($x) => $x['name'] . ' (' . $x['reason'] . ')')->implode('; ') }}.
                    </p>
                @endif
            </div>

            <div class="mt-3 flex items-center gap-2">
                <button wire:click="saveBulkAdjustment" wire:loading.attr="disabled" class="btn-primary">
                    <span wire:loading.remove wire:target="saveBulkAdjustment">Apply and recalculate</span>
                    <span wire:loading wire:target="saveBulkAdjustment">Recalculating…</span>
                </button>
                <button wire:click="$set('showBulk', false)" class="btn-ghost">Cancel</button>
            </div>
        </div>
    @endif

    {{-- Add / edit an adjustment --}}
    @if ($showAdjust)
        <div class="card p-4 mb-4">
            <h3 class="text-sm font-semibold text-gray-700 mb-3">
                {{ $adjustmentId ? 'Edit adjustment' : 'Add adjustment' }}
            </h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                {{-- Narrows the picker, not the adjustment. A company-wide run
                     is ninety names in a dropdown, and the corrections that
                     come up in practice are aimed at one group — the agency
                     heads, or the leavers. Same options as the Generate panel
                     so the words mean the same thing in both places. --}}
                <div>
                    <label class="label">Employment</label>
                    <select wire:model.live="adj_employment" class="input">
                        <option value="">All employment statuses</option>
                        @foreach ($employmentSegments as $sv => $sl)
                            <option value="{{ $sv }}">{{ $sl }}</option>
                        @endforeach
                    </select>
                    <p class="help">Filters the list below — it does not change the adjustment.</p>
                </div>
                <div>
                    <label class="label">Employee</label>
                    <select wire:model="adj_employee_id" class="input">
                        <option value="">— Select —</option>
                        @foreach ($adjustCandidates as $l)
                            <option value="{{ $l->employee_id }}">{{ $l->employee_name }}</option>
                        @endforeach
                    </select>
                    @if ($adjustCandidates->isEmpty())
                        <p class="help text-warning-700">Nobody on this run matches that employment filter.</p>
                    @else
                        <p class="help">{{ $adjustCandidates->count() }} of {{ $lines->count() }} on this run.</p>
                    @endif
                    <x-input-error :messages="$errors->get('adj_employee_id')" class="mt-1" />
                </div>
                <div>
                    <label class="label">Description</label>
                    <input type="text" maxlength="120" wire:model="adj_label" class="input"
                           placeholder="e.g. June overpayment recovery" />
                    {{-- It is printed on the payslip, so it has to read as an
                         explanation to the person being paid. --}}
                    <p class="help">Shown on the payslip — write it for the employee.</p>
                    <x-input-error :messages="$errors->get('adj_label')" class="mt-1" />
                </div>
                <div>
                    <label class="label">Direction</label>
                    <select wire:model.live="adj_direction" class="input">
                        @foreach (\App\Models\PayrollRunAdjustment::DIRECTIONS as $dv => $dl)
                            <option value="{{ $dv }}">{{ $dl }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('adj_direction')" class="mt-1" />
                </div>
                <div>
                    <label class="label">Amount (RM)</label>
                    <input type="number" step="0.01" min="0.01" wire:model="adj_amount" class="input" />
                    <p class="help">Always positive — the direction above decides the sign.</p>
                    <x-input-error :messages="$errors->get('adj_amount')" class="mt-1" />
                </div>
            </div>

            {{-- The decision that changes what is remitted, so it is spelled
                 out rather than left as a checkbox label. --}}
            <div class="mt-3 p-3 bg-gray-50 rounded-lg border border-gray-100">
                <label class="flex items-start gap-2">
                    <input type="checkbox" wire:model.live="adj_affects_statutory"
                           class="mt-0.5 rounded border-gray-300 text-brand-600 focus:ring-brand-500">
                    <span class="text-sm text-gray-800">Counts as wages this month</span>
                </label>
                <p class="mt-1 text-[11px] text-gray-600">
                    @if ($adj_affects_statutory)
                        EPF, SOCSO, EIS and PCB will be recomputed on the adjusted figure. Right for
                        <strong>arrears</strong> — pay underpaid now is wages now, and contributions are due on it.
                    @else
                        Applied after the statutory deductions, so it changes take-home only. Right for
                        <strong>recovering an overpayment</strong> — the contributions were already remitted on
                        last month's inflated figure, and reducing this month's as well would understate a
                        month in which nothing was overpaid.
                    @endif
                </p>
            </div>

            <div class="mt-3">
                <label class="label">Internal note (optional)</label>
                <input type="text" maxlength="255" wire:model="adj_notes" class="input"
                       placeholder="Not shown on the payslip" />
                <x-input-error :messages="$errors->get('adj_notes')" class="mt-1" />
            </div>

            <div class="mt-3 flex items-center gap-2">
                <button wire:click="saveAdjustment" wire:loading.attr="disabled" class="btn-primary">
                    <span wire:loading.remove wire:target="saveAdjustment">Save and recalculate</span>
                    <span wire:loading wire:target="saveAdjustment">Recalculating…</span>
                </button>
                <button wire:click="$set('showAdjust', false)" class="btn-ghost">Cancel</button>
            </div>
        </div>
    @endif

    {{-- Submission and payment files. Only from an approved run: a draft can
         still be regenerated, and a submission built from figures that then
         change is the one mistake here that cannot be undone by editing. --}}
    <div class="card p-4 mb-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h3 class="text-sm font-semibold text-gray-700">Submissions &amp; payment</h3>
                <p class="text-xs text-gray-600 mt-0.5">
                    @if ($run->isApproved())
                        Field listings built from this run's locked figures — check them against KWSP,
                        PERKESO and LHDN before submitting. Each file repeats that caveat in its first row.
                    @else
                        Available once the run is approved.
                    @endif
                </p>
            </div>
            @if ($run->isApproved())
                <div class="flex flex-wrap gap-2">
                    {{-- Each listing omits the people it has nothing to say
                         about, so a run where nobody paid PCB produces an empty
                         CP39 — common in this industry, where most staff earn
                         under the tax threshold. The count is shown and the
                         empty ones are not clickable, because offering a button
                         that then fails is what made this look broken. --}}
                    @foreach (\App\Services\Payroll\PayrollExports::TYPES as $type => $label)
                        @php $rows = $exportCounts[$type] ?? null; @endphp

                        @if ($rows === 0)
                            <span class="btn-secondary text-xs opacity-50 cursor-not-allowed"
                                  title="No employee on this run has a figure for {{ $label }}.">
                                {{ $label }} — none
                            </span>
                        @else
                            <a href="{{ route('hr.payroll.export', [$run, $type]) }}" class="btn-secondary text-xs">
                                {{ $label }}@if ($rows) <span class="text-gray-500">({{ $rows }})</span>@endif
                            </a>
                        @endif
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    {{-- Totals.

         Every one of these is a different question and three of them are close
         enough in size to be mistaken for each other — gross, net and cost to
         company sit within a few thousand ringgit and mean entirely different
         things. So each says what it is underneath the figure, in the terms
         somebody checking a payroll would use.

         Laid out three across rather than five: at five, the sixth tile
         wrapped onto a row of its own and read as an afterthought, and there
         was no room for a line of explanation under any of them. --}}
    <div class="card p-4 mb-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-x-6 gap-y-5">
            <div class="stat">
                <span class="stat-label">Staff</span>
                <span class="stat-value">{{ $run->employee_count }}</span>
                <span class="stat-meta">People paid on this run.</span>
            </div>

            <div class="stat">
                <span class="stat-label">Gross</span>
                <span class="stat-value">{{ number_format((float) $run->total_gross, 2) }}</span>
                <span class="stat-meta">Basic, allowances and overtime, less company deductions. Before statutory.</span>
            </div>

            @if ((float) $run->total_service_charge > 0)
                <div class="stat">
                    <span class="stat-label">Service charge</span>
                    <span class="stat-value">{{ number_format((float) $run->total_service_charge, 2) }}</span>
                    {{-- RM per point is the figure staff actually check — "I
                         have two points, so I should have had twice what a
                         one-point colleague did" — and without it the pool
                         total answers nobody's question.

                         Absent on a run spanning several outlets, because each
                         pool has its own rate and showing one of them as the
                         run's would be wrong for everybody else. --}}
                    @if ($perPoint !== null)
                        <span class="text-sm font-semibold text-gray-700 -mt-0.5">
                            RM {{ number_format($perPoint, 2) }} per point
                        </span>
                    @endif
                    {{-- Said plainly because it is the one figure here that is
                         not the company's money — it is collected from
                         customers and passes through, which is exactly why it
                         is absent from cost to company below. --}}
                    <span class="stat-meta">
                        Each person's share of the pool, after attendance deductions. Collected from
                        customers, so it is paid on top and is not a company cost.
                        @if ($perPoint === null)
                            This run spans outlets with different pools, so there is no single rate per point.
                        @endif
                    </span>
                </div>
            @endif

            <div class="stat">
                <span class="stat-label">Statutory (staff)</span>
                <span class="stat-value">{{ number_format((float) $run->total_statutory_employee, 2) }}</span>
                <span class="stat-meta">The employee's own EPF, SOCSO, EIS and PCB — deducted from their pay, not added to it.</span>
            </div>

            <div class="stat">
                <span class="stat-label">Net pay</span>
                <span class="stat-value text-brand-700">{{ number_format((float) $run->total_net, 2) }}</span>
                <span class="stat-meta">What actually reaches staff: gross less their statutory, plus any service charge. This is the amount the payment file pays.</span>
            </div>

            <div class="stat">
                <span class="stat-label">Cost to company</span>
                <span class="stat-value">{{ number_format((float) $run->total_employer_cost, 2) }}</span>
                <span class="stat-meta">Gross plus the <strong>employer's</strong> contributions ({{ number_format((float) $run->total_statutory_employer, 2) }}). Excludes service charge.</span>
            </div>
        </div>
    </div>

    {{-- Lines --}}
    <div class="flex items-center justify-between mb-2">
        <h3 class="text-sm font-semibold text-gray-700">Employees on this run</h3>
        {{-- Offered on a DRAFT too, unlike the statutory and bank files: this
             is the sheet somebody checks the figures on before approving, and
             holding it back until after approval would withhold it at exactly
             the moment it is useful. --}}
        <div class="flex items-center gap-2">
            <a href="{{ route('hr.payroll.list-pdf', $run) }}" target="_blank" rel="noopener"
               class="btn-secondary text-xs">
                Download PDF
            </a>
            {{-- Excel for the same reason and on the same terms, but for the
                 part of checking a run that paper cannot do: sorting it,
                 filtering it and totalling it against something else. --}}
            <a href="{{ route('hr.payroll.run-excel', $run) }}"
               class="btn-secondary text-xs">
                Download Excel
            </a>
        </div>
    </div>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table-surface min-w-[1100px]">
                <thead>
                    <tr>
                        <th class="px-3 py-2 text-left">Employee</th>
                        <th class="px-2 py-2 text-right">Basic</th>
                        <th class="px-2 py-2 text-right">Allowances</th>
                        <th class="px-2 py-2 text-right">OT</th>
                        @if ((float) $run->total_service_charge > 0)
                            <th class="px-2 py-2 text-right">Svc charge</th>
                        @endif
                        <th class="px-2 py-2 text-right">Deductions</th>
                        <th class="px-2 py-2 text-right">Gross</th>
                        <th class="px-2 py-2 text-right">EPF</th>
                        <th class="px-2 py-2 text-right">SOCSO</th>
                        <th class="px-2 py-2 text-right">EIS</th>
                        <th class="px-2 py-2 text-right">PCB</th>
                        <th class="px-2 py-2 text-right">Net</th>
                        <th class="px-2 py-2"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($lines as $line)
                        <tr wire:key="line-{{ $line->id }}" class="hover:bg-gray-50">
                            <td class="px-3 py-1.5 whitespace-nowrap">
                                <span class="font-medium text-gray-800">{{ $line->employee_name }}</span>
                                <span class="block text-[10px] text-gray-500">
                                    @if ($line->staff_id)<span class="font-mono">{{ $line->staff_id }}</span> · @endif
                                    {{ $line->outlet_name ?? '—' }}
                                </span>
                                @if ($line->missingForPayment())
                                    <span class="badge-warning mt-0.5">no {{ implode(', ', $line->missingForPayment()) }}</span>
                                @endif
                                {{-- The warning above the table names these people;
                                     this is where somebody scrolling to find them
                                     stops. Danger, not warning: a missing bank
                                     account delays a payment, an unfilled grid pays
                                     the wrong amount. --}}
                                @if ($line->zeroHourReason())
                                    <span class="badge-danger mt-0.5">{{ $line->zeroHourReason() }}</span>
                                @endif
                            </td>
                            <td class="px-2 py-1.5 text-right tabular-nums text-gray-700">
                                {{ number_format((float) $line->basic, 2) }}
                                {{-- The working, so a figure can be checked against
                                     the attendance grid without opening it. --}}
                                @if ($line->paid_hours !== null)
                                    <span class="block text-[10px] text-gray-500 whitespace-nowrap">
                                        {{ rtrim(rtrim(number_format((float) $line->paid_hours, 2, '.', ''), '0'), '.') }}h
                                        @if ((float) $line->pay_rate > 0)
                                            &times; {{ number_format((float) $line->pay_rate, 2) }}
                                        @endif
                                    </span>
                                @endif
                            </td>
                            <td class="px-2 py-1.5 text-right tabular-nums text-gray-700">{{ (float) $line->allowances > 0 ? number_format((float) $line->allowances, 2) : '—' }}</td>
                            <td class="px-2 py-1.5 text-right tabular-nums text-gray-700">
                                {{ (float) $line->ot_amount > 0 ? number_format((float) $line->ot_amount, 2) : '—' }}
                                @if ((float) $line->ot_hours > 0)
                                    <span class="block text-[10px] text-gray-500">{{ number_format((float) $line->ot_hours, 2) }}h</span>
                                @endif
                            </td>
                            @if ((float) $run->total_service_charge > 0)
                                <td class="px-2 py-1.5 text-right tabular-nums text-gray-700">
                                    {{ (float) $line->service_charge > 0 ? number_format((float) $line->service_charge, 2) : '—' }}
                                </td>
                            @endif
                            <td class="px-2 py-1.5 text-right tabular-nums {{ (float) $line->deductions > 0 ? 'text-danger-600' : 'text-gray-400' }}">
                                {{ (float) $line->deductions > 0 ? '-' . number_format((float) $line->deductions, 2) : '—' }}
                            </td>
                            <td class="px-2 py-1.5 text-right tabular-nums font-medium text-gray-800">{{ number_format((float) $line->gross, 2) }}</td>
                            <td class="px-2 py-1.5 text-right tabular-nums text-gray-600">{{ (float) $line->epf_employee > 0 ? number_format((float) $line->epf_employee, 2) : '—' }}</td>
                            <td class="px-2 py-1.5 text-right tabular-nums text-gray-600">{{ (float) $line->socso_employee > 0 ? number_format((float) $line->socso_employee, 2) : '—' }}</td>
                            <td class="px-2 py-1.5 text-right tabular-nums text-gray-600">{{ (float) $line->eis_employee > 0 ? number_format((float) $line->eis_employee, 2) : '—' }}</td>
                            <td class="px-2 py-1.5 text-right tabular-nums text-gray-600">{{ (float) $line->pcb > 0 ? number_format((float) $line->pcb, 2) : '—' }}</td>
                            <td class="px-2 py-1.5 text-right tabular-nums font-semibold text-brand-700">{{ number_format((float) $line->net, 2) }}</td>
                            <td class="px-2 py-1.5 text-right whitespace-nowrap">
                                <a href="{{ route('hr.payroll.payslip', [$run, $line]) }}"
                                   class="text-xs font-medium text-brand-600 hover:text-brand-800">Payslip</a>
                                {{-- What was sent, where and when — the question
                                     asked after the fact, answered on the row. --}}
                                @php $sent = ($deliveries[$line->id] ?? collect())->first(); @endphp
                                @if ($sent)
                                    <span class="block text-[10px] mt-0.5
                                        {{ $sent->status === \App\Models\PayslipDelivery::SENT ? 'text-success-700'
                                            : ($sent->status === \App\Models\PayslipDelivery::FAILED ? 'text-danger-600' : 'text-gray-500') }}"
                                          title="{{ $sent->email }}{{ $sent->error ? ' — ' . $sent->error : '' }}">
                                        @if ($sent->status === \App\Models\PayslipDelivery::SENT)
                                            emailed {{ $sent->sent_at?->format('d M H:i') }}
                                        @elseif ($sent->status === \App\Models\PayslipDelivery::FAILED)
                                            email failed
                                        @else
                                            queued
                                        @endif
                                    </span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="13" class="px-3 py-6 text-center text-sm text-gray-600">
                            No employees in this run.
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Approve --}}
    @if ($showApprove)
        {{-- Teleported, like the two below it. `position: fixed` is relative to
             the viewport UNLESS an ancestor carries a transform, and
             layouts.app wraps every page in `.page-enter`, whose animation
             keeps its final translateY because the fill mode is `both`. Even
             translateY(0) establishes a containing block, so a fixed panel is
             quietly clipped to the content column instead of covering the
             window. The layout solves this the same way for the user menu. --}}
        @teleport('body')
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 p-4">
            <div class="card p-5 w-full max-w-lg">
                <h3 class="text-base font-semibold text-gray-800">Approve {{ $run->periodLabel() }}</h3>
                <p class="text-sm text-gray-700 mt-2">
                    This locks every figure in the run. It cannot be regenerated afterwards, and the
                    payslips, payment file and statutory submissions will all be produced from it.
                </p>
                @if ($warnings)
                    <div class="alert-warning mt-3">
                        <ul class="list-disc list-inside text-sm space-y-0.5">
                            @foreach ($warnings as $warning)<li>{{ $warning }}</li>@endforeach
                        </ul>
                    </div>
                @endif
                <div class="mt-3">
                    <label class="label">Note (optional)</label>
                    <textarea wire:model="notes" rows="2" class="input" placeholder="Anything worth recording about this run"></textarea>
                </div>
                <div class="mt-4 flex justify-end gap-2">
                    <button wire:click="$set('showApprove', false)" class="btn-ghost">Cancel</button>
                    <button wire:click="approve" class="btn-primary">Approve and lock</button>
                </div>
            </div>
        </div>
        @endteleport
    @endif

    {{-- Email payslips. A confirmation, not a one-click send: this puts
         someone's pay details in their inbox and cannot be recalled. --}}
    @if ($showEmail)
        @teleport('body')
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 p-4">
            <div class="card p-5 w-full max-w-lg max-h-[85vh] overflow-y-auto">
                <h3 class="text-base font-semibold text-gray-800">Email payslips — {{ $run->periodLabel() }}</h3>

                <p class="text-sm text-gray-700 mt-2">
                    Each employee gets their own payslip as a PDF attachment. The email itself carries
                    no figures — those are in the attachment.
                </p>

                <div class="mt-3 space-y-2 text-sm">
                    <p class="text-gray-800">
                        <strong>{{ $audience['sendable']->count() }}</strong> will be sent.
                    </p>

                    @if ($audience['alreadySent']->isNotEmpty())
                        <label class="flex items-start gap-2">
                            <input type="checkbox" wire:model.live="resendSent"
                                   class="mt-0.5 rounded border-gray-300 text-brand-600 focus:ring-brand-500" />
                            <span class="text-gray-700">
                                Also re-send to the {{ $audience['alreadySent']->count() }} already emailed.
                                <span class="block text-xs text-gray-500">
                                    Leave unticked to send only to those who have not had it yet.
                                </span>
                            </span>
                        </label>
                    @endif

                    @if ($audience['blocked']->isNotEmpty())
                        <div class="alert-warning">
                            <p class="font-medium">{{ $audience['blocked']->count() }} cannot be emailed</p>
                            <p class="text-xs mt-0.5">No valid email address on record. They will need their payslip printed.</p>
                            <ul class="mt-1 text-xs list-disc list-inside">
                                @foreach ($audience['blocked']->take(10) as $row)
                                    <li>{{ $row['line']->employee_name }}{{ $row['raw'] ? ' — "' . $row['raw'] . '"' : '' }}</li>
                                @endforeach
                                @if ($audience['blocked']->count() > 10)
                                    <li>and {{ $audience['blocked']->count() - 10 }} more</li>
                                @endif
                            </ul>
                        </div>
                    @endif

                    {{-- The addresses are shown before sending, not after: this
                         is the only moment a typo can still be caught. --}}
                    @if ($audience['sendable']->isNotEmpty())
                        <details class="rounded-control border border-gray-200 p-2">
                            <summary class="text-xs font-medium text-gray-700 cursor-pointer">
                                Check the {{ $audience['sendable']->count() }} address(es)
                            </summary>
                            <ul class="mt-2 text-xs text-gray-600 space-y-0.5 max-h-48 overflow-y-auto">
                                @foreach ($audience['sendable'] as $row)
                                    <li>{{ $row['line']->employee_name }} — <span class="font-mono">{{ $row['email'] }}</span></li>
                                @endforeach
                            </ul>
                        </details>
                    @endif
                </div>

                <div class="mt-4 flex justify-end gap-2">
                    <button wire:click="$set('showEmail', false)" class="btn-ghost">Cancel</button>
                    <button wire:click="sendPayslips" wire:loading.attr="disabled" class="btn-primary">
                        <span wire:loading.remove wire:target="sendPayslips">Send {{ $audience['sendable']->count() }} payslip(s)</span>
                        <span wire:loading wire:target="sendPayslips">Queueing…</span>
                    </button>
                </div>
            </div>
        </div>
        @endteleport
    @endif

    {{-- Mark paid --}}
    @if ($showPaid)
        @teleport('body')
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 p-4">
            <div class="card p-5 w-full max-w-md">
                <h3 class="text-base font-semibold text-gray-800">Record payment</h3>
                <p class="text-sm text-gray-700 mt-2">The date the salaries actually left the account.</p>
                <div class="mt-3">
                    <label class="label">Payment date</label>
                    <input type="date" wire:model="paymentDate" class="input" />
                    <x-input-error :messages="$errors->get('paymentDate')" class="mt-1" />
                </div>
                <div class="mt-4 flex justify-end gap-2">
                    <button wire:click="$set('showPaid', false)" class="btn-ghost">Cancel</button>
                    <button wire:click="markPaid" class="btn-primary">Mark paid</button>
                </div>
            </div>
        </div>
        @endteleport
    @endif
</div>
