<div class="mx-auto max-w-4xl px-4 py-12 sm:px-6 lg:px-8">
    <div class="text-center">
        <p class="page-eyebrow">Free tool</p>
        <h1 class="display-2 mt-2">Salary Calculator</h1>
        <p class="mx-auto mt-3 max-w-2xl text-gray-600">
            Monthly take-home after EPF, SOCSO, EIS and PCB — and what the same person
            actually costs to employ.
        </p>
    </div>

    <div class="card mt-8 p-6">
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="basic" class="label">Basic salary</label>
                <div class="mt-1 flex items-center gap-2">
                    <span class="text-sm text-gray-600">RM</span>
                    <input id="basic" type="number" min="0" step="50" wire:model.live.debounce.400ms="basic"
                           class="input tabular-nums" />
                </div>
            </div>

            <div>
                <label for="category" class="label">Tax category</label>
                <select id="category" wire:model.live="category" class="input mt-1">
                    @foreach ($categories as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="children" class="label">Children claimed</label>
                <input id="children" type="number" min="0" max="20" step="1" wire:model.live.debounce.400ms="children"
                       class="input mt-1 w-32 tabular-nums" />
            </div>

            <div>
                <span class="label">Applies to</span>
                <div class="mt-2 space-y-1.5">
                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" wire:model.live="isMalaysian" class="rounded border-gray-300">
                        Malaysian citizen / permanent resident
                    </label>
                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" wire:model.live="isSenior" class="rounded border-gray-300">
                        Aged 60 or above
                    </label>
                </div>
            </div>
        </div>

        {{-- Allowances. Three named cases rather than three checkboxes: an
             operator can answer "fixed or variable?" and cannot answer "is this
             SOCSO-applicable?" — which is the same question in the language of
             the Act rather than the language of a payslip. --}}
        <div class="mt-6">
            <div class="flex items-center justify-between gap-3">
                <span class="label">Allowances</span>
                <button type="button" wire:click="addAllowance" class="btn-secondary btn-sm">+ Add allowance</button>
            </div>

            @if ($allowances)
                <div class="mt-2 space-y-2">
                    @foreach ($allowances as $i => $line)
                        <div wire:key="allow-{{ $i }}" class="grid gap-2 sm:grid-cols-12">
                            <input type="text" wire:model.live.debounce.500ms="allowances.{{ $i }}.label"
                                   placeholder="e.g. Transport" class="input sm:col-span-4" />
                            <select wire:model.live="allowances.{{ $i }}.type" class="input sm:col-span-4">
                                @foreach ($allowanceTypes as $key => $type)
                                    <option value="{{ $key }}">{{ $type['label'] }}</option>
                                @endforeach
                            </select>
                            <input type="number" min="0" step="10" wire:model.live.debounce.400ms="allowances.{{ $i }}.amount"
                                   class="input tabular-nums sm:col-span-3" />
                            <button type="button" wire:click="removeAllowance({{ $i }})"
                                    aria-label="Remove allowance {{ $i + 1 }}"
                                    class="icon-btn text-danger-400 hover:text-danger-600 sm:col-span-1">&times;</button>
                        </div>
                        <p class="help">{{ $allowanceTypes[$line['type'] ?? 'fixed']['note'] }}</p>
                    @endforeach
                </div>
            @else
                <p class="help mt-1">Housing, transport, position — anything paid on top of basic.</p>
            @endif
        </div>

        {{-- Overtime by hours and kind. The Employment Act prices hours beyond
             normal hours at 1.5x, 2x and 3x; entering ringgit hides which of
             those was actually used, which is where disputes start. --}}
        <div class="mt-6">
            <span class="label">Overtime hours</span>

            <div class="mt-2 grid gap-3 sm:grid-cols-3">
                @foreach ($otTypes as $key => $type)
                    <div>
                        <label for="ot-{{ $key }}" class="block text-xs font-medium text-gray-600">
                            {{ $type['label'] }}
                            <span class="text-gray-500">&times;{{ rtrim(rtrim(number_format($type['rate'], 1), '0'), '.') }}</span>
                        </label>
                        <input id="ot-{{ $key }}" type="number" min="0" step="0.5"
                               wire:model.live.debounce.400ms="otHours.{{ $key }}"
                               class="input mt-1 tabular-nums" />
                        <p class="help mt-1">{{ $type['note'] }}</p>
                    </div>
                @endforeach
            </div>

            <div class="mt-3 grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="hours-day" class="label">Normal hours a day</label>
                    <input id="hours-day" type="number" min="1" max="12" step="0.5"
                           wire:model.live.debounce.400ms="normalHoursPerDay"
                           class="input mt-1 w-32 tabular-nums" />
                    <p class="help mt-1">Used with 26 days a month to get the hourly rate.</p>
                </div>
                <div>
                    <label for="unpaid" class="label">Unpaid leave (days)</label>
                    <input id="unpaid" type="number" min="0" step="0.5"
                           wire:model.live.debounce.400ms="unpaidLeaveDays"
                           class="input mt-1 w-32 tabular-nums" />
                    <p class="help mt-1">Deducted from basic at the same 26-day daily rate.</p>
                </div>
                <div>
                    <label for="overtime" class="label">Other overtime paid</label>
                    <div class="mt-1 flex items-center gap-2">
                        <span class="text-sm text-gray-600">RM</span>
                        <input id="overtime" type="number" min="0" step="10" wire:model.live.debounce.400ms="overtime"
                               class="input tabular-nums" />
                    </div>
                    <p class="help mt-1">A fixed OT allowance, or a figure straight off a payslip. Added to the hours above.</p>
                </div>
            </div>
        </div>

        <div class="mt-6 grid gap-4 sm:grid-cols-2">
            <div>
                <label for="service" class="label">Service charge</label>
                <div class="mt-1 flex items-center gap-2">
                    <span class="text-sm text-gray-600">RM</span>
                    <input id="service" type="number" min="0" step="10" wire:model.live.debounce.400ms="serviceCharge"
                           class="input tabular-nums" />
                </div>
                <p class="help mt-1">Taxable, but not wages for EPF, SOCSO or EIS.</p>
            </div>
        </div>

        @if ($figures['ready'])
            <div class="mt-8 grid gap-4 sm:grid-cols-3">
                <div class="stat">
                    <p class="stat-label">Gross</p>
                    <p class="stat-value tabular-nums">RM {{ number_format($figures['gross'], 2) }}</p>
                </div>
                <div class="stat">
                    <p class="stat-label">Take-home</p>
                    <p class="stat-value tabular-nums text-brand-600">RM {{ number_format($figures['net'], 2) }}</p>
                </div>
                <div class="stat">
                    <p class="stat-label">Cost to employer</p>
                    <p class="stat-value tabular-nums">RM {{ number_format($figures['employer_cost'], 2) }}</p>
                    <p class="text-xs text-gray-600 mt-1">gross plus the employer's share</p>
                </div>
            </div>

            {{-- Both sides in one table. An employee reads the left column and
                 an owner reads the right, and they are arguing about the same
                 salary from opposite ends of it. --}}
            <div class="mt-6 overflow-x-auto">
                <table class="table-surface min-w-full">
                    <thead>
                        <tr>
                            <th class="px-4 py-3 text-left">Contribution</th>
                            <th class="px-4 py-3 text-right">Employee</th>
                            <th class="px-4 py-3 text-right">Employer</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="px-4 py-3 font-medium text-gray-800">
                                EPF <span class="text-xs text-gray-600">({{ rtrim(rtrim(number_format($figures['epf_rate'], 2), '0'), '.') }}% employee)</span>
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums">RM {{ number_format($figures['epf_employee'], 2) }}</td>
                            <td class="px-4 py-3 text-right tabular-nums text-gray-600">RM {{ number_format($figures['epf_employer'], 2) }}</td>
                        </tr>
                        <tr>
                            <td class="px-4 py-3 font-medium text-gray-800">SOCSO</td>
                            <td class="px-4 py-3 text-right tabular-nums">RM {{ number_format($figures['socso_employee'], 2) }}</td>
                            <td class="px-4 py-3 text-right tabular-nums text-gray-600">RM {{ number_format($figures['socso_employer'], 2) }}</td>
                        </tr>
                        <tr>
                            <td class="px-4 py-3 font-medium text-gray-800">EIS</td>
                            <td class="px-4 py-3 text-right tabular-nums">RM {{ number_format($figures['eis_employee'], 2) }}</td>
                            <td class="px-4 py-3 text-right tabular-nums text-gray-600">RM {{ number_format($figures['eis_employer'], 2) }}</td>
                        </tr>
                        <tr>
                            <td class="px-4 py-3 font-medium text-gray-800">
                                PCB <span class="text-xs text-gray-600">(estimate)</span>
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums">RM {{ number_format($figures['pcb'], 2) }}</td>
                            <td class="px-4 py-3 text-right text-gray-500">&mdash;</td>
                        </tr>
                        <tr class="bg-gray-50">
                            <td class="px-4 py-3 font-semibold text-gray-900">Total</td>
                            <td class="px-4 py-3 text-right font-semibold tabular-nums">RM {{ number_format($figures['deductions'], 2) }}</td>
                            <td class="px-4 py-3 text-right font-semibold tabular-nums text-gray-700">
                                RM {{ number_format($figures['employer_cost'] - $figures['gross'], 2) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            @if ($figures['unpaid_leave'] > 0)
                <div class="alert-warning mt-6">
                    <p class="font-medium">
                        {{ rtrim(rtrim(number_format($figures['unpaid_days'], 1), '0'), '.') }}
                        day{{ $figures['unpaid_days'] == 1 ? '' : 's' }} unpaid —
                        RM {{ number_format($figures['unpaid_leave'], 2) }} off basic
                    </p>
                    <p class="mt-1 text-sm">
                        At RM {{ number_format($figures['daily_rate'], 2) }} a day, leaving basic of
                        RM {{ number_format($figures['paid_basic'], 2) }}. EPF, SOCSO, EIS and PCB all
                        follow the wages actually payable, so every figure below is lower for it.
                    </p>
                </div>
            @endif

            @if ($figures['ot_lines'])
                <div class="mt-6 rounded-panel border border-gray-200 bg-gray-50 p-5">
                    <h2 class="text-sm font-semibold text-gray-800">Overtime working</h2>
                    <p class="help mt-1">
                        Hourly rate RM {{ number_format($figures['hourly_rate'], 2) }} — basic salary
                        RM {{ number_format($figures['basic'], 2) }} over 26 days over
                        {{ rtrim(rtrim(number_format($normalHoursPerDay, 1), '0'), '.') }} hours.
                        Allowances are not part of the overtime rate.
                    </p>
                    <ul class="mt-3 space-y-1.5">
                        @foreach ($figures['ot_lines'] as $line)
                            <li class="flex items-baseline justify-between gap-3 text-sm">
                                <span class="text-gray-700">
                                    {{ $line['label'] }} —
                                    {{ rtrim(rtrim(number_format($line['hours'], 1), '0'), '.') }} h
                                    &times; {{ rtrim(rtrim(number_format($line['rate'], 1), '0'), '.') }}
                                </span>
                                <span class="tabular-nums font-medium text-gray-800">RM {{ number_format($line['pay'], 2) }}</span>
                            </li>
                        @endforeach
                    </ul>
                    <p class="help mt-3">
                        These are the statutory minimums for hours beyond normal hours. A rest day or public
                        holiday also carries its own day's pay for the normal hours themselves — a separate
                        entitlement, not overtime, and not included here.
                    </p>
                </div>
            @endif

            {{-- The three bases, shown rather than assumed.

                 Once a service charge or overtime is on the payslip the reader
                 will ask why EPF did not move, and a calculator that will not
                 show its own workings gets closed. --}}
            @if ($figures['epf_wage'] !== $figures['gross'] || $figures['socso_wage'] !== $figures['gross'])
                <div class="mt-6 rounded-panel border border-gray-200 bg-gray-50 p-5">
                    <h2 class="text-sm font-semibold text-gray-800">What each contribution is calculated on</h2>
                    <p class="help mt-1">
                        Malaysian law does not have one idea of "pay" — each scheme excludes something different.
                    </p>
                    <dl class="mt-3 grid gap-3 sm:grid-cols-3">
                        <div>
                            <dt class="text-xs uppercase tracking-wide text-gray-500">EPF wages</dt>
                            <dd class="text-sm font-semibold tabular-nums text-gray-800">RM {{ number_format($figures['epf_wage'], 2) }}</dd>
                            <dd class="text-xs text-gray-600">basic + fixed allowances</dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase tracking-wide text-gray-500">SOCSO &amp; EIS wages</dt>
                            <dd class="text-sm font-semibold tabular-nums text-gray-800">RM {{ number_format($figures['socso_wage'], 2) }}</dd>
                            <dd class="text-xs text-gray-600">plus overtime, capped</dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase tracking-wide text-gray-500">Taxable pay</dt>
                            <dd class="text-sm font-semibold tabular-nums text-gray-800">RM {{ number_format($figures['taxable_wage'], 2) }}</dd>
                            <dd class="text-xs text-gray-600">plus service charge</dd>
                        </div>
                    </dl>
                </div>
            @endif

            {{-- Says what this is and is not. A salary figure people plan around
                 deserves to be honest about its own precision. --}}
            <div class="alert-info mt-6">
                <p class="font-medium">PCB here is an estimate, not a payslip figure.</p>
                <p class="mt-1 text-sm">
                    It applies the standard reliefs — individual, EPF up to the cap, spouse and children —
                    to an annualised salary and divides by twelve. Chargeable income used:
                    <strong class="tabular-nums">RM {{ number_format($figures['chargeable'], 2) }}</strong> a year.
                    The real MTD schedule also accounts for your year to date, zakat, other reliefs claimed and
                    prior deductions. Close enough to plan with; check with LHDN before you file.
                </p>
            </div>
        @else
            <div class="empty-state mt-8">
                <p class="font-medium text-gray-800">Enter a monthly salary</p>
                <p class="help mt-1">Everything else follows from it.</p>
            </div>
        @endif
    </div>

    <p class="mt-6 text-center text-sm text-gray-600">
        Running this for a whole team every month, with payslips and EA forms at the end of it,
        is what <a href="{{ route('saas.register') }}" class="text-brand-600 underline">Servora</a> does.
    </p>
</div>
