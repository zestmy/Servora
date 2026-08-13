<div>
    @if (session()->has('success'))
        <div wire:key="flash-{{ microtime(true) }}" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)"
             class="mb-4 px-4 py-3 bg-success-50 border border-success-200 text-success-700 text-sm rounded-lg">
            {{ session('success') }}
        </div>
    @endif

    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        <div class="flex items-center gap-3">
            <a data-back href="{{ route('settings.index') }}" class="text-gray-600 hover:text-gray-900 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <div>
                <p class="page-eyebrow">Settings / Statutory Rates</p>
                <h1 class="page-title mt-1">Statutory Rates</h1>
            </div>
        </div>
        <button wire:click="save" class="btn-primary">Save rates</button>
    </div>

    {{-- The warning is the point of the screen, so it leads. --}}
    <div class="card p-5 mb-4 border-warning-200 bg-warning-50/60">
        <p class="text-sm text-warning-800 font-semibold">These figures are yours to verify.</p>
        <p class="text-sm text-gray-700 mt-1">
            The values below are the published Malaysian rates as at August 2026, seeded as a starting point.
            They are not an authority and they change by ministerial order. Check them against
            <strong>KWSP</strong> (EPF), <strong>PERKESO</strong> (SOCSO and EIS) and <strong>LHDN</strong> (PCB)
            before running payroll on them.
        </p>
        <p class="text-sm text-gray-700 mt-2">
            EPF, SOCSO and EIS are published as <strong>wage-band tables</strong>. This calculates a percentage of
            the capped wage instead, which lands within a few sen of the table but is not guaranteed to match it
            row for row. PCB is an <strong>annualised estimate</strong>, not the LHDN MTD formula — that formula
            carries year-to-date pay and tax forward, which needs a payroll run history this system does not keep.
            Reconcile both before submitting a return.
        </p>
        @if ($settings->rates_confirmed_at)
            <p class="text-sm text-success-700 mt-3">
                Confirmed {{ $settings->rates_confirmed_at->format('d M Y') }}
                @if ($settings->confirmedBy) by {{ $settings->confirmedBy->name }} @endif.
            </p>
        @else
            <p class="text-sm text-danger-700 mt-3 font-medium">
                Not yet confirmed — payroll figures are labelled as unverified everywhere they appear.
            </p>
        @endif
    </div>

    {{-- Employer reference numbers. Not rates — these identify the company on
         every submission, and a listing without them cannot be filed. --}}
    <div class="card p-5 mb-4">
        <h3 class="text-sm font-semibold text-gray-700">Employer reference numbers</h3>
        <p class="text-xs text-gray-600 mt-0.5 mb-3">
            Printed on payslips and on the EPF, SOCSO and PCB listings produced from a payroll run.
        </p>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div>
                <label class="label">EPF employer no.</label>
                <input type="text" maxlength="30" wire:model="employer_epf_number" class="input" />
            </div>
            <div>
                <label class="label">SOCSO employer code</label>
                <input type="text" maxlength="30" wire:model="employer_socso_number" class="input" />
            </div>
            <div>
                <label class="label">LHDN employer no. (E)</label>
                <input type="text" maxlength="30" wire:model="employer_tax_number" class="input" />
            </div>
            <div>
                <label class="label">HRD Corp employer no.</label>
                <input type="text" maxlength="30" wire:model="employer_hrdf_number" class="input" />
            </div>
        </div>
    </div>

    {{-- HRD Corp levy. Its own card, above the four contribution cards, because
         it is the one that is NOT deducted from anybody — putting it in the
         grid beside EPF invites exactly that misreading. --}}
    <div class="card p-5 mb-4">
        <label class="flex items-center gap-2 mb-1">
            <input type="checkbox" wire:model="hrdf_enabled" class="rounded border-gray-300 text-brand-600 focus:ring-brand-500" />
            <span class="text-sm font-semibold text-gray-700">HRD Corp levy (HRDF)</span>
        </label>
        <p class="text-xs text-gray-600 mb-3">
            <strong>Employer only</strong> — never deducted from an employee, and it does not appear on a
            payslip as a deduction. It is included in cost to company. Mandatory at 10 or more Malaysian
            employees in a covered industry (1%), optional at 5 to 9 (0.5%). Charged on Malaysian
            employees; switch it off per person on their Compensation screen where it does not apply.
        </p>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
                <label class="label">Levy rate (%)</label>
                <input type="number" step="0.01" min="0" max="100" wire:model="hrdf_employer_rate" class="input" />
                <x-input-error :messages="$errors->get('hrdf_employer_rate')" class="mt-1" />
            </div>
            <div>
                <label class="label">Wage ceiling (RM)</label>
                <input type="number" step="0.01" min="0" wire:model="hrdf_ceiling" class="input" placeholder="uncapped" />
                <p class="help">Leave blank for uncapped, which is the usual case.</p>
                <x-input-error :messages="$errors->get('hrdf_ceiling')" class="mt-1" />
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        {{-- EPF --}}
        <div class="card p-5">
            <label class="flex items-center gap-2 mb-3">
                <input type="checkbox" wire:model="epf_enabled" class="rounded border-gray-300 text-brand-600 focus:ring-brand-500" />
                <span class="text-sm font-semibold text-gray-700">EPF (KWSP)</span>
            </label>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="text-xs font-semibold text-gray-600">Employee %</label>
                    <input type="number" step="0.01" wire:model="epf_employee_rate" class="mt-1 w-full text-sm rounded-lg border-gray-300" />
                    <x-input-error :messages="$errors->get('epf_employee_rate')" class="mt-1" />
                </div>
                <div>
                    <label class="text-xs font-semibold text-gray-600">Wage threshold</label>
                    <input type="number" step="0.01" wire:model="epf_wage_threshold" class="mt-1 w-full text-sm rounded-lg border-gray-300" />
                    <p class="mt-1 text-[11px] text-gray-500">Employer rate steps down above this.</p>
                    <x-input-error :messages="$errors->get('epf_wage_threshold')" class="mt-1" />
                </div>
                <div>
                    <label class="text-xs font-semibold text-gray-600">Employer % (at or below)</label>
                    <input type="number" step="0.01" wire:model="epf_employer_rate_low" class="mt-1 w-full text-sm rounded-lg border-gray-300" />
                    <x-input-error :messages="$errors->get('epf_employer_rate_low')" class="mt-1" />
                </div>
                <div>
                    <label class="text-xs font-semibold text-gray-600">Employer % (above)</label>
                    <input type="number" step="0.01" wire:model="epf_employer_rate_high" class="mt-1 w-full text-sm rounded-lg border-gray-300" />
                    <x-input-error :messages="$errors->get('epf_employer_rate_high')" class="mt-1" />
                </div>
                <div>
                    <label class="text-xs font-semibold text-gray-600">Employee % (senior)</label>
                    <input type="number" step="0.01" wire:model="epf_employee_rate_senior" class="mt-1 w-full text-sm rounded-lg border-gray-300" />
                </div>
                <div>
                    <label class="text-xs font-semibold text-gray-600">Employer % (senior)</label>
                    <input type="number" step="0.01" wire:model="epf_employer_rate_senior" class="mt-1 w-full text-sm rounded-lg border-gray-300" />
                </div>
                <div>
                    <label class="text-xs font-semibold text-gray-600">Employee % (non-citizen)</label>
                    <input type="number" step="0.01" wire:model="epf_employee_rate_foreign" class="mt-1 w-full text-sm rounded-lg border-gray-300" />
                </div>
                <div>
                    <label class="text-xs font-semibold text-gray-600">Employer % (non-citizen)</label>
                    <input type="number" step="0.01" wire:model="epf_employer_rate_foreign" class="mt-1 w-full text-sm rounded-lg border-gray-300" />
                </div>
                <div>
                    <label class="text-xs font-semibold text-gray-600">Senior age</label>
                    <input type="number" wire:model="senior_age" class="mt-1 w-full text-sm rounded-lg border-gray-300" />
                    <p class="mt-1 text-[11px] text-gray-500">EPF and SOCSO rates both change here.</p>
                    <x-input-error :messages="$errors->get('senior_age')" class="mt-1" />
                </div>
            </div>
            <p class="mt-3 text-[11px] text-gray-500">Contributions are rounded up to the next whole ringgit.</p>
        </div>

        {{-- SOCSO + EIS --}}
        <div class="space-y-4">
            <div class="card p-5">
                <label class="flex items-center gap-2 mb-3">
                    <input type="checkbox" wire:model="socso_enabled" class="rounded border-gray-300 text-brand-600 focus:ring-brand-500" />
                    <span class="text-sm font-semibold text-gray-700">SOCSO (PERKESO)</span>
                </label>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-xs font-semibold text-gray-600">Employee %</label>
                        <input type="number" step="0.01" wire:model="socso_employee_rate" class="mt-1 w-full text-sm rounded-lg border-gray-300" />
                        <x-input-error :messages="$errors->get('socso_employee_rate')" class="mt-1" />
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-gray-600">Employer %</label>
                        <input type="number" step="0.01" wire:model="socso_employer_rate" class="mt-1 w-full text-sm rounded-lg border-gray-300" />
                        <x-input-error :messages="$errors->get('socso_employer_rate')" class="mt-1" />
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-gray-600">Employer % (senior)</label>
                        <input type="number" step="0.01" wire:model="socso_employer_rate_senior" class="mt-1 w-full text-sm rounded-lg border-gray-300" />
                        <p class="mt-1 text-[11px] text-gray-500">Employee side is nil.</p>
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-gray-600">Insured wage ceiling</label>
                        <input type="number" step="0.01" wire:model="socso_ceiling" class="mt-1 w-full text-sm rounded-lg border-gray-300" />
                        <x-input-error :messages="$errors->get('socso_ceiling')" class="mt-1" />
                    </div>
                </div>
            </div>

            {{-- SKBBK. Its own card and no employer column, because unlike
                 every scheme beside it the employee pays the whole thing. --}}
            <div class="card p-5">
                <label class="flex items-center gap-2 mb-1">
                    <input type="checkbox" wire:model="skbbk_enabled" class="rounded border-gray-300 text-brand-600 focus:ring-brand-500" />
                    <span class="text-sm font-semibold text-gray-700">SKBBK — LINDUNG 24 Jam</span>
                </label>
                <p class="text-xs text-gray-600 mb-3">
                    PERKESO cover for accidents outside work, from 1 June 2026.
                    <strong>Paid entirely by the employee</strong> — there is no employer share.
                    Mandatory for foreign workers; voluntary for locals since 14 July 2026, so each
                    local who stayed in is ticked on their own <strong>Statutory</strong> tab.
                </p>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-xs font-semibold text-gray-600">Employee %</label>
                        <input type="number" step="0.01" wire:model="skbbk_employee_rate" class="mt-1 w-full text-sm rounded-lg border-gray-300" />
                        {{-- The rate is phased, so it WILL need changing. Said
                             here rather than hard-coded to a calendar, which
                             would silently deduct the wrong percentage the
                             month a phase turned over. --}}
                        <p class="mt-1 text-[11px] text-gray-500">
                            0.75% in the first two years, then 1.0%, then 1.25% from year six.
                            Check the current phase against PERKESO.
                        </p>
                        <x-input-error :messages="$errors->get('skbbk_employee_rate')" class="mt-1" />
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-gray-600">Insured wage ceiling</label>
                        <input type="number" step="0.01" wire:model="skbbk_ceiling" class="mt-1 w-full text-sm rounded-lg border-gray-300" />
                        <p class="mt-1 text-[11px] text-gray-500">
                            Computed as a percentage of capped wages, like SOCSO and EIS above —
                            within sen of PERKESO's band table, not identical to it.
                        </p>
                        <x-input-error :messages="$errors->get('skbbk_ceiling')" class="mt-1" />
                    </div>
                </div>
            </div>

            <div class="card p-5">
                <label class="flex items-center gap-2 mb-3">
                    <input type="checkbox" wire:model="eis_enabled" class="rounded border-gray-300 text-brand-600 focus:ring-brand-500" />
                    <span class="text-sm font-semibold text-gray-700">EIS (SIP)</span>
                </label>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-xs font-semibold text-gray-600">Employee %</label>
                        <input type="number" step="0.01" wire:model="eis_employee_rate" class="mt-1 w-full text-sm rounded-lg border-gray-300" />
                        <x-input-error :messages="$errors->get('eis_employee_rate')" class="mt-1" />
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-gray-600">Employer %</label>
                        <input type="number" step="0.01" wire:model="eis_employer_rate" class="mt-1 w-full text-sm rounded-lg border-gray-300" />
                        <x-input-error :messages="$errors->get('eis_employer_rate')" class="mt-1" />
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-gray-600">Insured wage ceiling</label>
                        <input type="number" step="0.01" wire:model="eis_ceiling" class="mt-1 w-full text-sm rounded-lg border-gray-300" />
                        <x-input-error :messages="$errors->get('eis_ceiling')" class="mt-1" />
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-gray-600">Stops at age</label>
                        <input type="number" wire:model="eis_max_age" class="mt-1 w-full text-sm rounded-lg border-gray-300" />
                        <x-input-error :messages="$errors->get('eis_max_age')" class="mt-1" />
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- PCB --}}
    <div class="card p-5 mt-4">
        <label class="flex items-center gap-2 mb-1">
            <input type="checkbox" wire:model="pcb_enabled" class="rounded border-gray-300 text-brand-600 focus:ring-brand-500" />
            <span class="text-sm font-semibold text-gray-700">PCB (monthly tax deduction)</span>
        </label>
        <p class="text-xs text-gray-500 mb-4">
            Off by default. An annualised estimate — see the note at the top of this page. Each employee's
            category, children and zakat are set on their compensation page.
        </p>

        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            <div>
                <label class="text-xs font-semibold text-gray-600">Individual relief</label>
                <input type="number" step="0.01" wire:model="pcb_relief_individual" class="mt-1 w-full text-sm rounded-lg border-gray-300" />
                <x-input-error :messages="$errors->get('pcb_relief_individual')" class="mt-1" />
            </div>
            <div>
                <label class="text-xs font-semibold text-gray-600">EPF relief cap</label>
                <input type="number" step="0.01" wire:model="pcb_relief_epf_cap" class="mt-1 w-full text-sm rounded-lg border-gray-300" />
                <x-input-error :messages="$errors->get('pcb_relief_epf_cap')" class="mt-1" />
            </div>
            <div>
                <label class="text-xs font-semibold text-gray-600">Spouse relief</label>
                <input type="number" step="0.01" wire:model="pcb_relief_spouse" class="mt-1 w-full text-sm rounded-lg border-gray-300" />
                <x-input-error :messages="$errors->get('pcb_relief_spouse')" class="mt-1" />
            </div>
            <div>
                <label class="text-xs font-semibold text-gray-600">Relief per child</label>
                <input type="number" step="0.01" wire:model="pcb_relief_child" class="mt-1 w-full text-sm rounded-lg border-gray-300" />
                <x-input-error :messages="$errors->get('pcb_relief_child')" class="mt-1" />
            </div>
            <div>
                <label class="text-xs font-semibold text-gray-600">Individual rebate</label>
                <input type="number" step="0.01" wire:model="pcb_rebate_amount" class="mt-1 w-full text-sm rounded-lg border-gray-300" />
                <p class="mt-1 text-[11px] text-gray-500">Doubled where the spouse has no income.</p>
                <x-input-error :messages="$errors->get('pcb_rebate_amount')" class="mt-1" />
            </div>
            <div>
                <label class="text-xs font-semibold text-gray-600">Rebate applies up to</label>
                <input type="number" step="0.01" wire:model="pcb_rebate_threshold" class="mt-1 w-full text-sm rounded-lg border-gray-300" />
                <p class="mt-1 text-[11px] text-gray-500">Chargeable income ceiling for the rebate.</p>
                <x-input-error :messages="$errors->get('pcb_rebate_threshold')" class="mt-1" />
            </div>
        </div>

        <div class="mt-5">
            <div class="flex flex-wrap items-center justify-between gap-3 mb-2">
                <div class="min-w-0">
                    <p class="text-xs font-semibold text-gray-600">Resident tax bands</p>
                    <p class="text-[11px] text-gray-500">Read in order. The last band must be open-ended.</p>
                </div>
                {{-- Wraps: the caption plus both buttons need more than a
                     narrow phone gives, and neither row could break. --}}
                <div class="flex flex-wrap items-center justify-end gap-2">
                    <button type="button" wire:click="resetBands" class="btn-secondary">Reset to defaults</button>
                    <button type="button" wire:click="addBand" class="btn-secondary">+ Band</button>
                </div>
            </div>
            <div class="space-y-2">
                @foreach ($bands as $i => $band)
                    <div wire:key="band-{{ $i }}" class="flex items-center gap-3">
                        <div class="flex-1">
                            <input type="number" step="0.01" wire:model="bands.{{ $i }}.up_to"
                                   placeholder="{{ $i === count($bands) - 1 ? 'and above (leave blank)' : 'up to' }}"
                                   class="w-full text-sm rounded-lg border-gray-300" />
                            <x-input-error :messages="$errors->get('bands.' . $i . '.up_to')" class="mt-1" />
                        </div>
                        <div class="w-28">
                            <input type="number" step="0.01" wire:model="bands.{{ $i }}.rate"
                                   class="w-full text-sm rounded-lg border-gray-300" />
                            <x-input-error :messages="$errors->get('bands.' . $i . '.rate')" class="mt-1" />
                        </div>
                        <span class="text-xs text-gray-500 w-4">%</span>
                        <button type="button" wire:click="removeBand({{ $i }})" class="text-danger-400 hover:text-danger-600 p-1" title="Remove">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M1 7h22M9 7V4a2 2 0 012-2h2a2 2 0 012 2v3"/></svg>
                        </button>
                    </div>
                @endforeach
            </div>
            <x-input-error :messages="$errors->get('bands')" class="mt-2" />
        </div>
    </div>

    <div class="card p-5 mt-4">
        <label class="flex items-start gap-2">
            <input type="checkbox" wire:model="confirmed" class="mt-0.5 rounded border-gray-300 text-brand-600 focus:ring-brand-500" />
            <span class="text-sm text-gray-700">
                I have checked these rates against KWSP, PERKESO and LHDN.
                <span class="block text-[11px] text-gray-500">
                    Re-stamped on every save, so "checked" always means checked against what is stored now.
                </span>
            </span>
        </label>
        <div class="flex justify-end mt-4">
            <button wire:click="save" class="btn-primary">Save rates</button>
        </div>
    </div>
</div>
