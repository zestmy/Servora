<div>
    <x-page-header title="Payroll" eyebrow="HR">
        <x-slot:actions>
            <a href="{{ route('hr.compensation') }}" class="btn-secondary">Compensation</a>
            <button wire:click="openNew" class="btn-primary">Generate payroll</button>
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
            A run <strong>snapshots</strong> the month. Compensation recalculates every time you open it, which is
            what you want while claims and allowances are still moving; a run freezes the figures so a payslip,
            a payment file and a statutory submission all describe the same payroll — and still will next year.
        </p>
        <p class="text-xs text-gray-600 mt-1">
            Drafts can be regenerated as often as you like. Approving locks them.
        </p>
    </div>

    {{-- Generate --}}
    @if ($showNew)
        <div class="card p-4 mb-4">
            <h3 class="text-sm font-semibold text-gray-700 mb-3">Generate payroll</h3>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div>
                    <label class="label">Month</label>
                    <input type="month" wire:model.live="newMonth" class="input" />
                    {{-- The dates this month actually covers under the company's
                         pay cycle, shown before generating rather than after. --}}
                    @if ($newRange)
                        <p class="help">
                            Covers <strong>{{ $newRange[0]->format('j M Y') }} – {{ $newRange[1]->format('j M Y') }}</strong>
                            @if ($settings->hasCustomCycle())
                                <a href="{{ route('settings.pay-components') }}" class="text-brand-600 hover:underline">(pay cycle)</a>
                            @endif
                        </p>
                    @endif
                    <x-input-error :messages="$errors->get('newMonth')" class="mt-1" />
                </div>
                <div>
                    <label class="label">Outlet</label>
                    <select wire:model="newOutlet" class="input">
                        <option value="">All outlets (one run)</option>
                        @foreach ($outlets as $outlet)
                            <option value="{{ $outlet->id }}">{{ $outlet->name }}</option>
                        @endforeach
                    </select>
                    <p class="help">Choose an outlet only if that branch is paid separately.</p>
                    <x-input-error :messages="$errors->get('newOutlet')" class="mt-1" />
                </div>
                <div class="flex items-end gap-2">
                    <button wire:click="generate" wire:loading.attr="disabled" class="btn-primary">
                        <span wire:loading.remove wire:target="generate">Generate</span>
                        <span wire:loading wire:target="generate">Generating…</span>
                    </button>
                    <button wire:click="$set('showNew', false)" class="btn-ghost">Cancel</button>
                </div>
            </div>
        </div>
    @endif

    @if ($runs->isEmpty())
        <div class="empty-state">
            <p class="text-sm text-gray-700">No payroll has been run yet.</p>
            <p class="text-xs text-gray-500 mt-1">Generate one for a month to produce payslips and payment files.</p>
        </div>
    @else
        <div class="card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="table-surface min-w-[860px]">
                    <thead>
                        <tr>
                            <th class="px-3 py-2 text-left">Period</th>
                            <th class="px-3 py-2 text-left">Reference</th>
                            <th class="px-3 py-2 text-left">Scope</th>
                            <th class="px-2 py-2 text-right">Staff</th>
                            <th class="px-2 py-2 text-right">Gross</th>
                            <th class="px-2 py-2 text-right">Net</th>
                            <th class="px-2 py-2 text-left">Status</th>
                            <th class="px-2 py-2"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($runs as $run)
                            <tr wire:key="run-{{ $run->id }}" class="hover:bg-gray-50">
                                <td class="px-3 py-2 font-medium text-gray-800 whitespace-nowrap">
                                    <a href="{{ route('hr.payroll.show', $run) }}" wire:navigate
                                       class="hover:text-brand-600 hover:underline">{{ $run->periodLabel() }}</a>
                                    {{-- With a mid-month cycle, "August" is not the
                                         same thing as August — so say the dates. --}}
                                    @if ($run->hasCustomRange())
                                        <span class="block text-[10px] text-gray-500 font-normal">{{ $run->rangeLabel() }}</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-xs font-mono text-gray-600">{{ $run->reference }}</td>
                                <td class="px-3 py-2 text-sm text-gray-600">{{ $run->outlet?->name ?? 'All outlets' }}</td>
                                <td class="px-2 py-2 text-right tabular-nums text-gray-700">{{ $run->employee_count }}</td>
                                <td class="px-2 py-2 text-right tabular-nums text-gray-700">{{ number_format((float) $run->total_gross, 2) }}</td>
                                <td class="px-2 py-2 text-right tabular-nums font-semibold text-brand-700">{{ number_format((float) $run->total_net, 2) }}</td>
                                <td class="px-2 py-2">
                                    @php
                                        $tone = match ($run->status) {
                                            \App\Models\PayrollRun::PAID     => 'badge-success',
                                            \App\Models\PayrollRun::APPROVED => 'badge-info',
                                            default                          => 'badge-warning',
                                        };
                                    @endphp
                                    <span class="{{ $tone }}">{{ $run->statusLabel() }}</span>
                                    @if ($run->approvedBy)
                                        <span class="block text-[10px] text-gray-500 mt-0.5">by {{ $run->approvedBy->name }}</span>
                                    @endif
                                </td>
                                <td class="px-2 py-2 text-right whitespace-nowrap">
                                    <a href="{{ route('hr.payroll.show', $run) }}" wire:navigate
                                       class="text-xs font-medium text-brand-600 hover:text-brand-800">Open</a>
                                    @if ($run->isEditable())
                                        <button wire:click="deleteRun({{ $run->id }})"
                                                wire:confirm="Delete this draft payroll run?"
                                                class="ml-3 text-xs font-medium text-danger-600 hover:text-danger-800">Delete</button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-4">{{ $runs->links() }}</div>
    @endif
</div>
