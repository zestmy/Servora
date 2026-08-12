@extends('pdf.layout')

@section('title', 'Payroll — ' . $run->reference)

@section('content')
    {{-- The run's employee table, printed as it appears on screen: same
         columns, same order, same totals. The sheet somebody checks a run
         against before approving it, as opposed to the payslips, which are one
         document per person and go to the staff. --}}
    <style>
        .pr-title   { font-size: 15pt; font-weight: bold; color: #104d4f; }
        .pr-sub     { font-size: 9pt; color: #6b7280; margin-top: 2px; }
        .pr-meta    { margin: 8px 0 10px; padding: 6px 9px; background: #f8fafc;
                      border: 1px solid #e2e8f0; border-radius: 4px; font-size: 8.5pt; color: #475569; }
        .pr-meta strong { color: #1f2937; }

        table.pr { width: 100%; border-collapse: collapse; font-size: 7.5pt; }
        table.pr thead th {
            background: #104d4f; color: #fff; font-weight: bold; padding: 5px 4px;
            border: 1px solid #0d3f41; font-size: 7pt; text-transform: uppercase; letter-spacing: 0.2px;
        }
        table.pr tbody td { padding: 4px; border: 1px solid #e2e8f0; }
        table.pr tbody tr:nth-child(even) td { background: #f8fafc; }
        table.pr tfoot td {
            padding: 5px 4px; border: 1px solid #cbd5e1; background: #f1f5f9;
            font-weight: bold; font-size: 8pt;
        }
        .r    { text-align: right; }
        .c    { text-align: center; }
        .sub  { font-size: 6.5pt; color: #6b7280; display: block; }
        .neg  { color: #b91c1c; }
    </style>

    <div class="pr-title">Payroll — {{ $run->periodLabel() }}</div>
    <div class="pr-sub">
        {{ $company->brand_name ?? $company->name }}
        &nbsp;·&nbsp; {{ $run->reference }}
        &nbsp;·&nbsp; Generated {{ now()->format('d M Y, g:ia') }} by {{ $generatedBy }}
    </div>

    <div class="pr-meta">
        <strong>Scope:</strong> {{ $run->scopeLabel() }}
        &nbsp;·&nbsp; <strong>Period:</strong> {{ $run->rangeLabel() }}
        &nbsp;·&nbsp; <strong>Status:</strong> {{ $run->statusLabel() }}
        @if ($run->approvedBy)
            (approved by {{ $run->approvedBy->name }})
        @endif
        &nbsp;·&nbsp; <strong>{{ $lines->count() }}</strong> employee(s)
        {{-- Said on the paper, because a draft that has been printed and
             circulated looks exactly as authoritative as an approved one. --}}
        @unless ($run->isApproved())
            <br><strong style="color: #a16207;">DRAFT — figures may still change. Not for payment or submission.</strong>
        @endunless
    </div>

    <table class="pr">
        <thead>
            <tr>
                <th style="width: 17%; text-align: left;">Employee</th>
                <th class="r" style="width: 8%;">Basic</th>
                <th class="r" style="width: 7%;">Allowances</th>
                <th class="r" style="width: 6%;">OT</th>
                @if ($hasService)
                    <th class="r" style="width: 7%;">Svc Charge</th>
                @endif
                <th class="r" style="width: 7%;">Deductions</th>
                <th class="r" style="width: 8%;">Gross</th>
                <th class="r" style="width: 6%;">EPF</th>
                <th class="r" style="width: 6%;">SOCSO</th>
                <th class="r" style="width: 5%;">EIS</th>
                <th class="r" style="width: 6%;">PCB</th>
                <th class="r" style="width: 9%;">Net</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($lines as $line)
                <tr>
                    <td>
                        {{ $line->employee_name }}
                        <span class="sub">
                            {{ $line->staff_id ? $line->staff_id . ' · ' : '' }}{{ $line->section_name ?: '—' }}
                            {{-- The working behind basic, the same notes the
                                 payslip carries: a part month or an hourly
                                 total is the first thing questioned. --}}
                            @if ($line->isProrated())
                                · {{ $line->prorationLabel() }}
                            @elseif ($line->paid_hours !== null)
                                · {{ rtrim(rtrim(number_format((float) $line->paid_hours, 2, '.', ''), '0'), '.') }}h
                            @elseif ($line->paid_days !== null)
                                · {{ (int) $line->paid_days }}d
                            @endif
                        </span>
                    </td>
                    <td class="r">{{ number_format((float) $line->basic, 2) }}</td>
                    <td class="r">{{ number_format((float) $line->allowances, 2) }}</td>
                    <td class="r">{{ number_format((float) $line->ot_amount, 2) }}</td>
                    @if ($hasService)
                        <td class="r">{{ number_format((float) $line->service_charge, 2) }}</td>
                    @endif
                    <td class="r">{{ number_format((float) $line->deductions, 2) }}</td>
                    <td class="r">{{ number_format((float) $line->gross, 2) }}</td>
                    <td class="r">{{ number_format((float) $line->epf_employee, 2) }}</td>
                    <td class="r">{{ number_format((float) $line->socso_employee, 2) }}</td>
                    <td class="r">{{ number_format((float) $line->eis_employee, 2) }}</td>
                    <td class="r">{{ number_format((float) $line->pcb, 2) }}</td>
                    <td class="r {{ (float) $line->net < 0 ? 'neg' : '' }}" style="font-weight: bold;">
                        {{ number_format((float) $line->net, 2) }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ $hasService ? 12 : 11 }}" class="c" style="padding: 24px; color: #9ca3af;">
                        This run has no employees.
                    </td>
                </tr>
            @endforelse
        </tbody>
        @if ($lines->isNotEmpty())
            <tfoot>
                <tr>
                    <td>Total</td>
                    <td class="r">{{ number_format((float) $lines->sum('basic'), 2) }}</td>
                    <td class="r">{{ number_format((float) $lines->sum('allowances'), 2) }}</td>
                    <td class="r">{{ number_format((float) $lines->sum('ot_amount'), 2) }}</td>
                    @if ($hasService)
                        <td class="r">{{ number_format((float) $run->total_service_charge, 2) }}</td>
                    @endif
                    <td class="r">{{ number_format((float) $lines->sum('deductions'), 2) }}</td>
                    <td class="r">{{ number_format((float) $run->total_gross, 2) }}</td>
                    <td class="r">{{ number_format((float) $lines->sum('epf_employee'), 2) }}</td>
                    <td class="r">{{ number_format((float) $lines->sum('socso_employee'), 2) }}</td>
                    <td class="r">{{ number_format((float) $lines->sum('eis_employee'), 2) }}</td>
                    <td class="r">{{ number_format((float) $lines->sum('pcb'), 2) }}</td>
                    <td class="r">{{ number_format((float) $run->total_net, 2) }}</td>
                </tr>
            </tfoot>
        @endif
    </table>

    {{-- The two figures the table cannot show, because neither is a column:
         what the employer pays on top, and what the run costs in total. --}}
    <table style="width: 100%; margin-top: 10px; font-size: 8pt; border-collapse: collapse;">
        <tr>
            <td style="width: 60%;"></td>
            <td style="padding: 3px 6px; color: #475569;">Employer contributions</td>
            <td class="r" style="padding: 3px 6px; width: 14%;">{{ number_format((float) $run->total_statutory_employer, 2) }}</td>
        </tr>
        <tr>
            <td></td>
            <td style="padding: 3px 6px; font-weight: bold; color: #1f2937; border-top: 1px solid #cbd5e1;">
                Cost to company
                <span class="sub">Gross plus employer contributions. Excludes service charge.</span>
            </td>
            <td class="r" style="padding: 3px 6px; font-weight: bold; border-top: 1px solid #cbd5e1;">
                {{ number_format((float) $run->total_employer_cost, 2) }}
            </td>
        </tr>
    </table>
@endsection
