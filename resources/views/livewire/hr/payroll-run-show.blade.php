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
                <span class="text-xs text-gray-500 block">Scope</span>
                <span class="text-gray-800">{{ $run->outlet?->name ?? 'All outlets' }}</span>
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
                    @foreach ([
                        'bank'  => 'Salary payment',
                        'cp39'  => 'PCB (CP39)',
                        'epf'   => 'EPF (KWSP)',
                        'socso' => 'SOCSO &amp; EIS',
                    ] as $type => $label)
                        <a href="{{ route('hr.payroll.export', [$run, $type]) }}" class="btn-secondary text-xs">
                            {!! $label !!}
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    {{-- Totals --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 mb-4">
        <div class="stat"><span class="stat-label">Staff</span><span class="stat-value">{{ $run->employee_count }}</span></div>
        <div class="stat"><span class="stat-label">Gross</span><span class="stat-value">{{ number_format((float) $run->total_gross, 2) }}</span></div>
        <div class="stat"><span class="stat-label">Statutory (staff)</span><span class="stat-value">{{ number_format((float) $run->total_statutory_employee, 2) }}</span></div>
        <div class="stat"><span class="stat-label">Net pay</span><span class="stat-value text-brand-700">{{ number_format((float) $run->total_net, 2) }}</span></div>
        <div class="stat">
            <span class="stat-label">Cost to company</span>
            <span class="stat-value">{{ number_format((float) $run->total_employer_cost, 2) }}</span>
        </div>
    </div>

    {{-- Lines --}}
    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table-surface min-w-[1100px]">
                <thead>
                    <tr>
                        <th class="px-3 py-2 text-left">Employee</th>
                        <th class="px-2 py-2 text-right">Basic</th>
                        <th class="px-2 py-2 text-right">Allowances</th>
                        <th class="px-2 py-2 text-right">OT</th>
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
                            </td>
                            <td class="px-2 py-1.5 text-right tabular-nums text-gray-700">{{ number_format((float) $line->basic, 2) }}</td>
                            <td class="px-2 py-1.5 text-right tabular-nums text-gray-700">{{ (float) $line->allowances > 0 ? number_format((float) $line->allowances, 2) : '—' }}</td>
                            <td class="px-2 py-1.5 text-right tabular-nums text-gray-700">
                                {{ (float) $line->ot_amount > 0 ? number_format((float) $line->ot_amount, 2) : '—' }}
                                @if ((float) $line->ot_hours > 0)
                                    <span class="block text-[10px] text-gray-500">{{ number_format((float) $line->ot_hours, 2) }}h</span>
                                @endif
                            </td>
                            <td class="px-2 py-1.5 text-right tabular-nums {{ (float) $line->deductions > 0 ? 'text-danger-600' : 'text-gray-400' }}">
                                {{ (float) $line->deductions > 0 ? '-' . number_format((float) $line->deductions, 2) : '—' }}
                            </td>
                            <td class="px-2 py-1.5 text-right tabular-nums font-medium text-gray-800">{{ number_format((float) $line->gross, 2) }}</td>
                            <td class="px-2 py-1.5 text-right tabular-nums text-gray-600">{{ (float) $line->epf_employee > 0 ? number_format((float) $line->epf_employee, 2) : '—' }}</td>
                            <td class="px-2 py-1.5 text-right tabular-nums text-gray-600">{{ (float) $line->socso_employee > 0 ? number_format((float) $line->socso_employee, 2) : '—' }}</td>
                            <td class="px-2 py-1.5 text-right tabular-nums text-gray-600">{{ (float) $line->eis_employee > 0 ? number_format((float) $line->eis_employee, 2) : '—' }}</td>
                            <td class="px-2 py-1.5 text-right tabular-nums text-gray-600">{{ (float) $line->pcb > 0 ? number_format((float) $line->pcb, 2) : '—' }}</td>
                            <td class="px-2 py-1.5 text-right tabular-nums font-semibold text-brand-700">{{ number_format((float) $line->net, 2) }}</td>
                            <td class="px-2 py-1.5 text-right">
                                <a href="{{ route('hr.payroll.payslip', [$run, $line]) }}"
                                   class="text-xs font-medium text-brand-600 hover:text-brand-800 whitespace-nowrap">Payslip</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="12" class="px-3 py-6 text-center text-sm text-gray-600">
                            No employees in this run.
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Approve --}}
    @if ($showApprove)
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
    @endif

    {{-- Mark paid --}}
    @if ($showPaid)
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
    @endif
</div>
