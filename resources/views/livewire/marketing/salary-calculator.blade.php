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
                <label for="salary" class="label">Monthly gross salary</label>
                <div class="mt-1 flex items-center gap-2">
                    <span class="text-sm text-gray-600">RM</span>
                    <input id="salary" type="number" min="0" step="50" wire:model.live.debounce.400ms="salary"
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
