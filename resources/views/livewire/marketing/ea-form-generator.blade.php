<div class="mx-auto max-w-5xl px-4 py-12 sm:px-6 lg:px-8">
    <div class="text-center">
        <p class="page-eyebrow">Free tool</p>
        <h1 class="display-2 mt-2">Borang EA Generator</h1>
        <p class="mx-auto mt-3 max-w-2xl text-gray-600">
            The statement of remuneration every employer has to give each employee by the
            end of February. Enter the year's pay — as totals, or month by month off the
            payslips — and take away an EA form ready to hand over.
        </p>
    </div>

    {{-- Said before anything is entered, not after the download. Somebody who
         believes this page has filed something for them is worse off than
         somebody who never found it. --}}
    <div class="alert-info mt-8">
        <p class="text-sm">
            <strong>This produces a working copy in the EA's structure, not LHDN's own C.P.8A.</strong>
            Figures are exactly what you enter — nothing here is recalculated, because a
            year's PCB is the twelve deductions actually made and remitted, not a number
            worked back from a total. Check it against your payslips before it goes out.
        </p>
    </div>

    {{-- ── How the year is entered ─────────────────────────────────────────
         Two ways because people hold the data two ways: an accountant hands
         over totals, and everyone else has a drawer of payslips where the
         adding up is the tedious part and where the errors get made.
    --}}
    <div class="mt-8 flex flex-wrap items-center justify-between gap-4">
        <div class="seg">
            <button type="button" wire:click="$set('mode', 'annual')"
                    class="seg-item {{ $mode === 'annual' ? 'seg-item-on' : '' }}">
                Annual totals
            </button>
            <button type="button" wire:click="$set('mode', 'monthly')"
                    class="seg-item {{ $mode === 'monthly' ? 'seg-item-on' : '' }}">
                Month by month
            </button>
        </div>

        <div class="flex items-center gap-2">
            <label for="year" class="label mb-0">Year of remuneration</label>
            <input id="year" type="number" min="2000" max="{{ now()->year }}" step="1"
                   wire:model.live="year" class="input w-28 tabular-nums" />
        </div>
    </div>

    {{-- ── Employer ────────────────────────────────────────────────────── --}}
    <div class="card mt-6 p-6">
        <h2 class="text-sm font-semibold text-gray-900">Employer</h2>

        <div class="mt-4 grid gap-4 sm:grid-cols-2">
            <div>
                <label for="employer-name" class="label">Company name</label>
                <input id="employer-name" type="text" wire:model.blur="employerName"
                       class="input mt-1" placeholder="e.g. Dotty's Sdn Bhd" />
            </div>
            <div>
                <label for="employer-tax" class="label">Employer's no. (E)</label>
                <input id="employer-tax" type="text" wire:model.blur="employerTaxNo"
                       class="input mt-1" placeholder="E 1234567890" />
            </div>
            <div>
                <label for="employer-reg" class="label">Company registration no.</label>
                <input id="employer-reg" type="text" wire:model.blur="employerRegNo" class="input mt-1" />
            </div>
            <div>
                <label for="employer-address" class="label">Address</label>
                <input id="employer-address" type="text" wire:model.blur="employerAddress" class="input mt-1" />
            </div>
        </div>
    </div>

    {{-- ── Employee (Part A) ───────────────────────────────────────────── --}}
    <div class="card mt-6 p-6">
        <h2 class="text-sm font-semibold text-gray-900">A &nbsp;Particulars of employee</h2>

        <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div>
                <label for="emp-name" class="label">Full name</label>
                <input id="emp-name" type="text" wire:model.blur="employeeName" class="input mt-1" />
            </div>
            <div>
                <label for="emp-ic" class="label">IC / passport no.</label>
                <input id="emp-ic" type="text" wire:model.blur="icNumber" class="input mt-1" />
            </div>
            <div>
                <label for="emp-tax" class="label">Income tax no.</label>
                <input id="emp-tax" type="text" wire:model.blur="taxNumber" class="input mt-1" placeholder="SG / OG" />
            </div>
            <div>
                <label for="emp-epf" class="label">EPF no.</label>
                <input id="emp-epf" type="text" wire:model.blur="epfNumber" class="input mt-1" />
            </div>
            <div>
                <label for="emp-socso" class="label">SOCSO no.</label>
                <input id="emp-socso" type="text" wire:model.blur="socsoNumber" class="input mt-1" />
            </div>
            <div>
                <label for="emp-staff" class="label">Staff no.</label>
                <input id="emp-staff" type="text" wire:model.blur="staffId" class="input mt-1" />
            </div>
            <div>
                <label for="emp-designation" class="label">Designation</label>
                <input id="emp-designation" type="text" wire:model.blur="designation" class="input mt-1" />
            </div>
            <div>
                <label for="emp-from" class="label">Employed from</label>
                <input id="emp-from" type="date" wire:model.blur="employedFrom" class="input mt-1" />
            </div>
            <div>
                <label for="emp-to" class="label">Employed to</label>
                <input id="emp-to" type="date" wire:model.blur="employedTo" class="input mt-1" />
            </div>
        </div>
    </div>

    {{-- ── The year's pay ──────────────────────────────────────────────── --}}
    @if ($mode === 'monthly')
        <div class="card mt-6 p-6">
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <h2 class="text-sm font-semibold text-gray-900">The year, month by month</h2>
                <p class="help">Straight off each payslip. Blank months are simply not counted.</p>
            </div>

            {{-- Nine columns of figures do not fit a phone, and shrinking them
                 to fit is how a 0 gets typed in the wrong column. It scrolls. --}}
            <div class="mt-4 overflow-x-auto">
                <table class="w-full min-w-[52rem] text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 text-left">
                            <th class="py-2 pr-2 font-medium text-gray-600">Month</th>
                            @foreach ($monthColumns as $key => $col)
                                <th class="px-1 py-2 text-right font-medium text-gray-600">{{ $col['label'] }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($months as $i => $row)
                            <tr wire:key="month-{{ $i }}" class="border-b border-gray-100">
                                <td class="py-1.5 pr-2 text-gray-700">
                                    {{ \Carbon\Carbon::create($year, $i + 1, 1)->format('M') }}
                                </td>
                                @foreach ($monthColumns as $key => $col)
                                    <td class="px-1 py-1.5">
                                        <input type="number" min="0" step="0.01" aria-label="{{ $col['label'] }} {{ \Carbon\Carbon::create($year, $i + 1, 1)->format('F') }}"
                                               wire:model.live.debounce.500ms="months.{{ $i }}.{{ $key }}"
                                               class="input w-full min-w-[5.5rem] px-2 py-1.5 text-right tabular-nums" />
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <p class="help mt-3">
                Bonus, benefits in kind and anything paid once a year go in the fields below —
                asking for them twelve times to be entered once is how a form gets abandoned.
            </p>
        </div>
    @endif

    <div class="card mt-6 p-6">
        <h2 class="text-sm font-semibold text-gray-900">B &nbsp;Employment income, benefits and living accommodation</h2>

        @php
            $gridLocked = $mode === 'monthly';
            $incomeFields = [
                'salary'         => ['1 (a) Gross salary, wages or leave pay', $gridLocked],
                'allowances'     => ['Allowances', $gridLocked],
                'overtime'       => ['Overtime payments', $gridLocked],
                'service_charge' => ['Service charge', $gridLocked],
                'bonus'          => ['Bonus, gratuity, commission', false],
                'director_fee'   => ["Director's fee", false],
                'other'          => ['Other gross remuneration', false],
                'bik'            => ['1 (b) Benefits in kind', false],
                'accommodation'  => ['1 (c) Value of living accommodation', false],
                'exempt'         => ['3 Tax exempt allowances, perquisites and gifts', false],
            ];
        @endphp

        <div class="mt-4 grid gap-4 sm:grid-cols-2">
            @foreach ($incomeFields as $key => [$label, $locked])
                <div>
                    <label for="income-{{ $key }}" class="label">{{ $label }}</label>
                    <div class="mt-1 flex items-center gap-2">
                        <span class="text-sm text-gray-600">RM</span>
                        @if ($locked)
                            {{-- Read-only rather than hidden: the total the grid
                                 produced is the number going on the form, and it
                                 should be visible where the form shows it. --}}
                            <input id="income-{{ $key }}" type="text" readonly tabindex="-1"
                                   value="{{ number_format($figures['income'][$key], 2) }}"
                                   class="input cursor-not-allowed bg-gray-50 text-right tabular-nums text-gray-600" />
                        @else
                            <input id="income-{{ $key }}" type="number" min="0" step="0.01"
                                   wire:model.live.debounce.500ms="income.{{ $key }}"
                                   class="input text-right tabular-nums" />
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        @if ($gridLocked)
            <p class="help mt-3">The first four come from the monthly grid above.</p>
        @endif

        <p class="help mt-3">
            Tax exempt allowances are reported on the form because the EA asks for them,
            and are <strong>not</strong> added into the gross — that is what exempt means.
        </p>
    </div>

    <div class="grid gap-6 sm:grid-cols-2">
        <div class="card mt-6 p-6">
            <h2 class="text-sm font-semibold text-gray-900">D &nbsp;Total deduction</h2>

            <div class="mt-4 space-y-4">
                @foreach ([
                    'pcb'   => ['1 Monthly tax deduction (MTD / PCB)', $mode === 'monthly'],
                    'cp38'  => ['2 CP38 deduction', false],
                    'zakat' => ['3 Zakat paid through salary deduction', false],
                    'tp1'   => ['4 Relief claimed through TP1', false],
                ] as $key => [$label, $locked])
                    <div>
                        <label for="ded-{{ $key }}" class="label">{{ $label }}</label>
                        <div class="mt-1 flex items-center gap-2">
                            <span class="text-sm text-gray-600">RM</span>
                            @if ($locked)
                                <input id="ded-{{ $key }}" type="text" readonly tabindex="-1"
                                       value="{{ number_format($figures['deductions'][$key], 2) }}"
                                       class="input cursor-not-allowed bg-gray-50 text-right tabular-nums text-gray-600" />
                            @else
                                <input id="ded-{{ $key }}" type="number" min="0" step="0.01"
                                       wire:model.live.debounce.500ms="deductions.{{ $key }}"
                                       class="input text-right tabular-nums" />
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="card mt-6 p-6">
            <h2 class="text-sm font-semibold text-gray-900">E &nbsp;Contributions</h2>

            <div class="mt-4 space-y-4">
                @foreach ([
                    'epf_employee'   => "1 Employee's EPF contribution",
                    'epf_employer'   => "Employer's EPF contribution",
                    'socso_employee' => '2 SOCSO paid by the employee',
                    'eis_employee'   => '3 EIS (SIP) paid by the employee',
                ] as $key => $label)
                    <div>
                        <label for="con-{{ $key }}" class="label">{{ $label }}</label>
                        <div class="mt-1 flex items-center gap-2">
                            <span class="text-sm text-gray-600">RM</span>
                            @if ($mode === 'monthly')
                                <input id="con-{{ $key }}" type="text" readonly tabindex="-1"
                                       value="{{ number_format($figures['contributions'][$key], 2) }}"
                                       class="input cursor-not-allowed bg-gray-50 text-right tabular-nums text-gray-600" />
                            @else
                                <input id="con-{{ $key }}" type="number" min="0" step="0.01"
                                       wire:model.live.debounce.500ms="contributions.{{ $key }}"
                                       class="input text-right tabular-nums" />
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- ── What it comes to ────────────────────────────────────────────── --}}
    <div class="panel mt-8 p-6">
        @if ($figures['ready'])
            <div class="grid gap-6 sm:grid-cols-3">
                <div class="stat">
                    <div class="stat-label">Total gross remuneration</div>
                    <div class="stat-value tabular-nums">RM {{ number_format($figures['gross'], 2) }}</div>
                </div>
                <div class="stat">
                    <div class="stat-label">Total deducted (Part D)</div>
                    <div class="stat-value tabular-nums">RM {{ number_format($figures['total_deducted'], 2) }}</div>
                </div>
                <div class="stat">
                    <div class="stat-label">Employee's EPF (Part E)</div>
                    <div class="stat-value tabular-nums">RM {{ number_format($figures['contributions']['epf_employee'], 2) }}</div>
                </div>
            </div>

            <p class="help mt-4">
                Roughly RM {{ number_format($figures['net'], 2) }} reached the employee across the year
                after PCB, zakat, EPF, SOCSO and EIS.
                @if ($mode === 'monthly')
                    Built from {{ $figures['months_paid'] }} month(s) with pay entered.
                @endif
                That figure is a sanity check on the columns, not an EA box.
            </p>

            <div class="mt-5 flex flex-wrap items-center gap-3">
                <button type="button" wire:click="downloadPdf" class="btn-primary">
                    Download the EA form (PDF)
                </button>
                <span class="help">3 downloads a day per network.</span>
            </div>

            @if ($downloadError)
                <p class="error-text mt-3">{{ $downloadError }}</p>
            @endif
        @else
            <div class="empty-state">
                <p class="text-sm text-gray-600">
                    Enter the year's pay above and the form totals appear here.
                </p>
            </div>
        @endif
    </div>

    <div class="mt-10 text-center">
        <p class="text-sm text-gray-600">
            Servora issues EA forms for every employee at once, from payroll runs already approved —
            no re-typing a year off payslips.
        </p>
        <a href="{{ route('saas.register') }}" class="btn-secondary mt-4">Start a free trial</a>
    </div>
</div>
