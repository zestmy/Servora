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
                    @if ($newRange && ! $customPeriod)
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
                    <select wire:model.live="newOutlet" class="input">
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

            {{-- ── Segment ──────────────────────────────────────────────────
                 Both default to everybody, because most companies pay everybody
                 in one batch and this row should cost them nothing to ignore.

                 Where it earns its place is outsourced staff. They are the
                 agent's employees on the agent's permit — the company buys
                 their labour against a contract rate and settles an invoice, so
                 they carry no EPF, SOCSO, EIS or PCB, are usually paid on a
                 different day, and the payment goes to the agent rather than to
                 any of the accounts on their records. Mixing them into the main
                 run produced one bank file that had to be split by hand every
                 month. Two runs is the fix, and this is how you ask for them. --}}
            <div class="mt-3 pt-3 border-t border-gray-100 grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div>
                    <label class="label">Section</label>
                    <select wire:model.live="newSection" class="input">
                        <option value="">All sections</option>
                        @foreach ($sections as $section)
                            <option value="{{ $section->id }}">{{ $section->name }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('newSection')" class="mt-1" />
                </div>
                <div>
                    <label class="label">Employment</label>
                    <select wire:model.live="newEmploymentStatus" class="input">
                        <option value="">All employment statuses</option>
                        @foreach ($segments as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('newEmploymentStatus')" class="mt-1" />
                </div>
                <div class="flex items-end">
                    {{-- The count is here rather than in a confirmation, because
                         an empty segment generates a perfectly valid empty run
                         and this is the last moment it is cheap to notice. --}}
                    <p class="help pb-1">
                        @if ($headcount === 0)
                            <span class="text-warning-700 font-medium">No employees match this segment.</span>
                            Check the outlet, section and employment together.
                        @else
                            Covers <strong>{{ $headcount }}</strong>
                            {{ \Illuminate\Support\Str::plural('employee', $headcount ?? 0) }}.
                            Leave both on "all" to pay everybody in one run.
                        @endif
                    </p>
                </div>
            </div>

            @if ($newEmploymentStatus === 'outsourcing')
                {{-- Said on the screen that builds the run, because it changes
                     what the resulting bank file is FOR: not a set of salary
                     transfers to staff, but the basis of one payment to an
                     agent. --}}
                <p class="mt-2 text-xs text-info-700">
                    Outsourced staff carry <strong>no statutory contributions</strong> — EPF, SOCSO, EIS,
                    PCB and the HRD Corp levy are the agent's, not this company's. Their salary figure is
                    the <strong>contract rate paid to the agent</strong>, and the money goes to the agent
                    on the agent's invoice rather than to the bank accounts on their records.
                </p>
            @endif

            {{-- The cycle-change escape hatch.

                 Closed by default and one checkbox wide, because the ordinary
                 month is a month and putting two date pickers in front of that
                 invites somebody to change them. The month it earns its place
                 is the one where a company moves its cycle: going to 26th–25th
                 makes the last calendar month short (1–25 June) and the first
                 new one start mid-month, and neither is something the cycle
                 SETTING can say — a setting is the steady state, and this is
                 the seam between two of them. The alternative was editing the
                 setting, generating, and editing it back, which rewrites what
                 every other screen thinks June meant. --}}
            <div class="mt-3 pt-3 border-t border-gray-100">
                <label class="flex items-center gap-2 text-sm text-gray-700">
                    <input type="checkbox" wire:model.live="customPeriod"
                           class="rounded border-gray-300 text-brand-600 focus:ring-brand-500">
                    Use a different period for this run
                </label>

                @if ($customPeriod)
                    <div class="mt-3 grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div>
                            <label class="label">Period from</label>
                            <input type="date" wire:model="newFrom" class="input" />
                            <x-input-error :messages="$errors->get('newFrom')" class="mt-1" />
                        </div>
                        <div>
                            <label class="label">Period to</label>
                            <input type="date" wire:model="newTo" class="input" />
                            <x-input-error :messages="$errors->get('newTo')" class="mt-1" />
                        </div>
                        <div class="sm:col-span-1">
                            <p class="help">
                                Attendance, approved overtime and dated allowances are counted over
                                these dates instead of the pay cycle. The run is still filed under the
                                month above, and carries the range on its payslips.
                            </p>
                        </div>
                    </div>

                @endif
            </div>

            {{-- ── A period per input ──────────────────────────────────────
                 A DIFFERENT QUESTION from the override above, which moves the
                 whole run. These move ONE thing and leave the run where it is,
                 because a company answers them on different calendars: a
                 service charge pool is saved for the dates the attendance grid
                 was showing, overtime is often approved a cycle behind, and a
                 timesheet may close on a different day from the payroll.

                 All three default to following the run, so the ordinary month
                 is still one field and a button. --}}
            <div class="mt-3 pt-3 border-t border-gray-100">
                <p class="text-xs font-medium text-gray-700">What each input is counted over</p>
                <p class="help mt-0.5">
                    Leave these alone and everything is counted over the run's own dates, as it always has been.
                </p>

                <div class="mt-3 space-y-3">
                    @foreach (\App\Services\Payroll\RunPeriods::COMPONENTS as $component)
                        @php
                            $mode  = $periodMode[$component] ?? \App\Livewire\Hr\Payroll::MODE_FOLLOWS;
                            $range = $componentRanges[$component] ?? null;
                            $isServiceCharge = $component === \App\Services\Payroll\RunPeriods::SERVICE_CHARGE;
                        @endphp

                        <div wire:key="period-{{ $component }}" class="rounded-lg border border-gray-200 p-3">
                            <div class="flex flex-wrap items-end gap-3">
                                <div class="min-w-[9rem]">
                                    <label class="label">{{ \App\Services\Payroll\RunPeriods::LABELS[$component] }}</label>
                                    <select wire:model.live="periodMode.{{ $component }}" class="input">
                                        @foreach (\App\Livewire\Hr\Payroll::MODES as $value => $label)
                                            <option value="{{ $value }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                @if ($mode === \App\Livewire\Hr\Payroll::MODE_CUSTOM)
                                    <div>
                                        <label class="label">From</label>
                                        <input type="date" wire:model.live="periodDates.{{ $component }}.from" class="input" />
                                    </div>
                                    <div>
                                        <label class="label">To</label>
                                        <input type="date" wire:model.live="periodDates.{{ $component }}.to" class="input" />
                                    </div>
                                @endif

                                {{-- The dates that will actually be used, whichever
                                     mode is chosen. A range you cannot see before
                                     generating is one you find out about on a
                                     payslip. --}}
                                <p class="text-xs text-gray-600 py-2">
                                    @if ($range)
                                        {{ $range[0]->format('j M') }} – {{ $range[1]->format('j M Y') }}
                                    @else
                                        <span class="text-warning-700">dates needed</span>
                                    @endif
                                </p>
                            </div>

                            <p class="help mt-1">
                                @if ($component === \App\Services\Payroll\RunPeriods::ATTENDANCE)
                                    Prices hourly and daily staff off the attendance grid. A monthly salary is
                                    never affected by this.
                                @elseif ($component === \App\Services\Payroll\RunPeriods::OVERTIME)
                                    Which approved claims this run pays. Hours an earlier run already paid are
                                    never paid twice, whatever the dates.
                                @else
                                    Which saved pool this run is paid from. Matched on both exact dates.
                                @endif
                            </p>

                            @error('periodDates.' . $component . '.from') <p class="text-xs text-danger-600 mt-1">{{ $message }}</p> @enderror
                            @error('periodDates.' . $component . '.to') <p class="text-xs text-danger-600 mt-1">{{ $message }}</p> @enderror

                            {{-- THE POOL PICKER, and the reason it is a picker
                                 rather than two date boxes: a pool is matched on
                                 both its exact dates, so a range one day out finds
                                 nothing and the run pays no service charge at all —
                                 silently. Typing dates cannot fix that; seeing
                                 whether they hit anything can. --}}
                            @if ($isServiceCharge && $servicePools)
                                @if ($servicePools['matched']->isNotEmpty())
                                    <p class="mt-2 text-xs text-success-700">
                                        Matches
                                        @foreach ($servicePools['matched'] as $pool)
                                            {{ $pool->outlet?->name ?? 'All outlets' }}
                                            RM {{ number_format((float) $pool->amount, 2) }}@if (! $loop->last), @endif
                                        @endforeach
                                        — this run will carry the service charge.
                                    </p>
                                @elseif ($servicePools['pools']->isNotEmpty())
                                    <div class="mt-2">
                                        <p class="text-xs text-warning-700">
                                            No pool is saved for these exact dates, so this run would carry no service
                                            charge. Pick the pool you mean:
                                        </p>
                                        <div class="mt-1.5 flex flex-wrap gap-1.5">
                                            @foreach ($servicePools['pools']->take(8) as $pool)
                                                <button type="button" wire:click="useServiceChargePool({{ $pool->id }})"
                                                        class="px-2 py-1 text-[11px] rounded-md border border-gray-300 text-gray-700 hover:bg-gray-50">
                                                    {{ $pool->outlet?->name ?? 'All outlets' }}
                                                    {{ $pool->period_from->format('j M') }} – {{ $pool->period_to->format('j M Y') }}
                                                </button>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            @endif
                        </div>
                    @endforeach
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
                                <td class="px-3 py-2 text-sm text-gray-600">
                                    {{ $run->outlet?->name ?? 'All outlets' }}
                                    {{-- Two runs of the same month are only
                                         tellable apart by their segment, so it
                                         is on the row rather than one click in. --}}
                                    @if ($run->isSegmented())
                                        <span class="block text-[10px] text-gray-500">
                                            {{ collect([$run->section?->name, $run->employmentSegmentLabel()])->filter()->implode(' · ') }}
                                        </span>
                                    @endif
                                </td>
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
                                                data-confirm-delete="Delete this draft payroll run?"
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
