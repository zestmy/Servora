<div>
    <x-page-header title="EA Forms" eyebrow="HR / Payroll">
        <x-slot:actions>
            <a href="{{ route('hr.payroll') }}" wire:navigate class="btn-secondary">Payroll runs</a>
            @if ($forms->isNotEmpty())
                <a href="{{ route('hr.payroll.ea', ['year' => $year, 'outlet' => $outletFilter ?: null]) }}"
                   class="btn-primary">Download all ({{ $forms->count() }})</a>
            @endif
        </x-slot:actions>
    </x-page-header>

    <div class="panel p-4 mb-4">
        <p class="text-sm text-gray-700">
            Borang EA (C.P.8A) is the statement of remuneration you must give <strong>each employee by
            28 February</strong> for the previous year, so they can file their own return. It is not
            submitted to LHDN by the employer.
        </p>
        <p class="text-xs text-gray-600 mt-1">
            Built by summing that year's <strong>approved and paid</strong> payroll runs. Drafts are
            excluded — a form issued from one would disagree with the payslips already handed out.
        </p>
    </div>

    {{-- What the system cannot know. Said before the forms are issued, because
         these have to be added by hand and it is too late once they are out. --}}
    <div class="alert-warning mb-4">
        <p class="font-medium text-sm">Check these before issuing</p>
        <p class="text-sm mt-0.5">
            Benefits in kind, the value of living accommodation, tax-exempt allowances, CP38 instalments
            and TP1 relief do not pass through payroll here, so every form reports them as
            <strong>nil</strong> — which is not the same as knowing they are zero. Each form repeats this
            in its own footnote.
        </p>
    </div>

    <div class="toolbar mb-4">
        <div class="flex flex-wrap items-end gap-3">
            <div>
                <label class="label">Year</label>
                @if ($years)
                    <select wire:model.live="year" class="input">
                        @foreach ($years as $y)
                            <option value="{{ $y }}">{{ $y }}</option>
                        @endforeach
                    </select>
                @else
                    <p class="text-sm text-gray-600">No approved payroll yet.</p>
                @endif
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
        </div>
    </div>

    @if (! $employer['tax_number'])
        <div class="alert-danger mb-4">
            <p class="text-sm">
                No <strong>employer's number (E)</strong> is set, so every form will print "NOT SET" where
                LHDN expects it. Add it under
                <a href="{{ route('settings.statutory') }}" class="underline font-medium">Settings → Statutory Rates</a>.
            </p>
        </div>
    @endif

    @if ($forms->isEmpty())
        <div class="empty-state">
            <p class="text-sm text-gray-700">Nothing to issue for {{ $year }}.</p>
            <p class="text-xs text-gray-500 mt-1">
                EA forms are built from approved payroll runs. Approve a run for {{ $year }} first.
            </p>
        </div>
    @else
        <div class="card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="table-surface min-w-[1000px]">
                    <thead>
                        <tr>
                            <th class="px-3 py-2 text-left">Employee</th>
                            <th class="px-2 py-2 text-left">Period in {{ $year }}</th>
                            <th class="px-2 py-2 text-right">Months</th>
                            <th class="px-2 py-2 text-right">Gross</th>
                            <th class="px-2 py-2 text-right">Service charge</th>
                            <th class="px-2 py-2 text-right">PCB</th>
                            <th class="px-2 py-2 text-right">EPF</th>
                            <th class="px-2 py-2 text-right">SOCSO</th>
                            <th class="px-2 py-2"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($forms as $ea)
                            <tr wire:key="ea-{{ $ea['employee_id'] }}" class="hover:bg-gray-50">
                                <td class="px-3 py-1.5 whitespace-nowrap">
                                    <span class="font-medium text-gray-800">{{ $ea['name'] }}</span>
                                    <span class="block text-[10px] text-gray-500">
                                        @if ($ea['ic_number'])
                                            <span class="font-mono">{{ $ea['ic_number'] }}</span>
                                        @else
                                            <span class="text-danger-600">no IC on record</span>
                                        @endif
                                        @if (! $ea['tax_number'])
                                            · <span class="text-warning-700">no tax no.</span>
                                        @endif
                                    </span>
                                </td>
                                <td class="px-2 py-1.5 text-xs text-gray-600 whitespace-nowrap">
                                    {{ $ea['from']->format('d/m/Y') }} – {{ $ea['to']->format('d/m/Y') }}
                                </td>
                                <td class="px-2 py-1.5 text-right tabular-nums text-gray-600">{{ $ea['months'] }}</td>
                                <td class="px-2 py-1.5 text-right tabular-nums font-medium text-gray-800">{{ number_format($ea['gross_income'], 2) }}</td>
                                <td class="px-2 py-1.5 text-right tabular-nums text-gray-600">{{ $ea['service_charge'] > 0 ? number_format($ea['service_charge'], 2) : '—' }}</td>
                                <td class="px-2 py-1.5 text-right tabular-nums text-gray-600">{{ $ea['pcb'] > 0 ? number_format($ea['pcb'], 2) : '—' }}</td>
                                <td class="px-2 py-1.5 text-right tabular-nums text-gray-600">{{ $ea['epf_employee'] > 0 ? number_format($ea['epf_employee'], 2) : '—' }}</td>
                                <td class="px-2 py-1.5 text-right tabular-nums text-gray-600">{{ $ea['socso_employee'] > 0 ? number_format($ea['socso_employee'], 2) : '—' }}</td>
                                <td class="px-2 py-1.5 text-right">
                                    <a href="{{ route('hr.payroll.ea', ['year' => $year, 'employee' => $ea['employee_id']]) }}"
                                       class="text-xs font-medium text-brand-600 hover:text-brand-800 whitespace-nowrap">EA form</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="px-4 py-2 text-[11px] text-gray-600 border-t border-gray-100">
                Gross includes basic, allowances, overtime and service charge — service charge is
                employment income and has to be declared, so leaving it out would understate the form.
            </p>
        </div>
    @endif
</div>
