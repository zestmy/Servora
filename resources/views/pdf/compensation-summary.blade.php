@extends('pdf.layout')

@section('title', 'Compensation — ' . $periodLabel)

@section('content')
<style>
    .rpt-title-block { margin-bottom: 12px; border-bottom: 2px solid #0f172a; padding-bottom: 8px; }
    .rpt-company     { font-size: 13pt; font-weight: bold; color: #0f172a; }
    .rpt-doc-title   { font-size: 9.5pt; text-transform: uppercase; letter-spacing: 2px; color: #475569; font-weight: bold; margin-top: 2px; }
    .rpt-period      { font-size: 8.5pt; color: #64748b; margin-top: 2px; }

    /* The unconfirmed-rates warning. Loud on paper too: a printout outlives
       the screen it came from, and this is what someone files a return off. */
    .rpt-warn {
        border: 1px solid #f59e0b; background: #fffbeb; color: #92400e;
        padding: 6px 8px; font-size: 8pt; margin-bottom: 10px;
    }
    .rpt-warn strong { color: #78350f; }

    table.cmp { width: 100%; border-collapse: collapse; font-size: 8.5pt; }
    table.cmp thead th {
        background: #1e293b; color: #f8fafc; padding: 6px 6px; text-align: left;
        font-size: 7pt; text-transform: uppercase; letter-spacing: 0.6px; font-weight: bold;
    }
    table.cmp thead th.right { text-align: right; }
    table.cmp tbody td {
        padding: 5px 6px; border-bottom: 1px solid #e5e7eb; color: #1f2937; vertical-align: top;
    }
    table.cmp tbody td.right { text-align: right; }
    table.cmp tbody tr.alt td { background: #fafafa; }
    table.cmp tbody td .sub { display: block; font-size: 7pt; color: #64748b; }
    table.cmp tfoot td {
        background: #1e293b; color: #f8fafc; font-weight: bold; font-size: 9pt;
        padding: 7px 6px; border-top: 2px solid #0f172a;
    }
    table.cmp tfoot td.right { text-align: right; }

    .rpt-foot { margin-top: 12px; font-size: 7.5pt; color: #64748b; line-height: 1.5; }
</style>

<div class="rpt-title-block">
    <div class="rpt-company">{{ $company->brand_name ?? $company->name }}</div>
    <div class="rpt-doc-title">Monthly Compensation</div>
    <div class="rpt-period">
        Period: {{ $periodLabel }} &nbsp;·&nbsp; {{ $scope['outlet'] }} &nbsp;·&nbsp; {{ $scope['section'] }}
    </div>
</div>

@if ($showStatutory && $statutory->ratesUnconfirmed())
    <div class="rpt-warn">
        <strong>Statutory rates have not been confirmed.</strong>
        EPF, SOCSO, EIS and PCB below are calculated from seeded defaults that nobody has checked against
        KWSP, PERKESO or LHDN. Do not file a return from this document until they are reviewed.
    </div>
@endif

<table class="cmp">
    <thead>
        <tr>
            <th>Employee</th>
            <th>Section</th>
            <th class="right">Basic</th>
            <th class="right">Allowances</th>
            <th class="right">Deductions</th>
            <th class="right">OT hrs</th>
            <th class="right">OT pay</th>
            <th class="right">Gross</th>
            @if ($showStatutory)
                <th class="right">EPF</th>
                <th class="right">SOCSO</th>
                <th class="right">EIS</th>
                <th class="right">PCB</th>
                {{-- SKBBK is inside net, so a sheet that leaves it out prints
                     a net its own columns cannot reach. Only where the scheme
                     is on, so the column stays stable down the page. --}}
                @if ($statutory->skbbk_enabled)
                    <th class="right">SKBBK</th>
                @endif
                <th class="right">Net</th>
            @endif
        </tr>
    </thead>
    <tbody>
        @forelse ($summary['rows'] as $i => $row)
            @php $st = $row['statutory']; @endphp
            <tr class="{{ $i % 2 ? 'alt' : '' }}">
                <td>
                    {{ $row['name'] }}
                    @if ($row['staff_id'])
                        <span class="sub">{{ $row['staff_id'] }}</span>
                    @endif
                    @if ($row['outlet'])
                        <span class="sub">{{ $row['outlet'] }}</span>
                    @endif
                </td>
                <td>{{ $row['section'] ?? '—' }}</td>
                <td class="right">{{ number_format($row['basic'], 2) }}</td>
                <td class="right">{{ number_format($row['allowances'], 2) }}</td>
                <td class="right">{{ number_format($row['deductions'], 2) }}</td>
                <td class="right">{{ number_format($row['ot_hours'], 2) }}</td>
                <td class="right">
                    {{-- No salary on file means the OT figure would be invented,
                         so it is named rather than printed as 0.00. --}}
                    {{ $row['ot_unrated'] ? 'no salary' : number_format($row['ot_amount'], 2) }}
                </td>
                <td class="right">{{ number_format($row['gross'], 2) }}</td>
                @if ($showStatutory)
                    <td class="right">{{ number_format($st['epf_employee'], 2) }}</td>
                    <td class="right">{{ number_format($st['socso_employee'], 2) }}</td>
                    <td class="right">{{ number_format($st['eis_employee'], 2) }}</td>
                    <td class="right">{{ number_format($st['pcb'], 2) }}</td>
                    @if ($statutory->skbbk_enabled)
                        <td class="right">{{ number_format($st['skbbk'] ?? 0, 2) }}</td>
                    @endif
                    <td class="right">{{ number_format($row['net'], 2) }}</td>
                @endif
            </tr>
        @empty
            <tr><td colspan="{{ ($showStatutory ? 13 : 8) + ($statutory->skbbk_enabled ? 1 : 0) }}" style="text-align:center; padding:18px; color:#64748b;">
                No employees for this period and scope.
            </td></tr>
        @endforelse
    </tbody>
    @if ($summary['rows']->isNotEmpty())
        <tfoot>
            <tr>
                <td colspan="2">Total ({{ $summary['rows']->count() }})</td>
                <td class="right">{{ number_format($summary['totals']['basic'], 2) }}</td>
                <td class="right">{{ number_format($summary['totals']['allowances'], 2) }}</td>
                <td class="right">{{ number_format($summary['totals']['deductions'], 2) }}</td>
                <td class="right">{{ number_format($summary['totals']['ot_hours'], 2) }}</td>
                <td class="right">{{ number_format($summary['totals']['ot_amount'], 2) }}</td>
                <td class="right">{{ number_format($summary['totals']['gross'], 2) }}</td>
                @if ($showStatutory)
                    <td class="right">{{ number_format($summary['totals']['epf_employee'], 2) }}</td>
                    <td class="right">{{ number_format($summary['totals']['socso_employee'], 2) }}</td>
                    <td class="right">{{ number_format($summary['totals']['eis_employee'], 2) }}</td>
                    <td class="right">{{ number_format($summary['totals']['pcb'], 2) }}</td>
                    @if ($statutory->skbbk_enabled)
                        <td class="right">{{ number_format($summary['totals']['skbbk'], 2) }}</td>
                    @endif
                    <td class="right">{{ number_format($summary['totals']['net'], 2) }}</td>
                @endif
            </tr>
        </tfoot>
    @endif
</table>

@if ($showStatutory && $summary['rows']->isNotEmpty())
    {{-- The employer side never appears on a payslip but is what the company
         actually remits, so the printout carries it as its own summary. --}}
    <table class="cmp" style="margin-top: 14px; width: 60%;">
        <thead>
            <tr>
                <th>Employer contributions</th>
                <th class="right">EPF</th>
                <th class="right">SOCSO</th>
                <th class="right">EIS</th>
                {{-- The levy is inside Total and inside Cost to company, so
                     without its own column the two exceed what is listed. --}}
                @if ($statutory->hrdf_enabled)
                    <th class="right">HRD Corp</th>
                @endif
                <th class="right">Total</th>
                <th class="right">Cost to company</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>{{ $periodLabel }}</td>
                <td class="right">{{ number_format($summary['totals']['epf_employer'], 2) }}</td>
                <td class="right">{{ number_format($summary['totals']['socso_employer'], 2) }}</td>
                <td class="right">{{ number_format($summary['totals']['eis_employer'], 2) }}</td>
                @if ($statutory->hrdf_enabled)
                    <td class="right">{{ number_format($summary['totals']['hrdf_employer'], 2) }}</td>
                @endif
                <td class="right">{{ number_format($summary['totals']['statutory_employer'], 2) }}</td>
                <td class="right">{{ number_format($summary['totals']['employer_cost'], 2) }}</td>
            </tr>
        </tbody>
    </table>
@endif

<div class="rpt-foot">
    Overtime priced at {{ (float) $summary['settings']->ot_normal_multiplier }}× normal,
    {{ (float) $summary['settings']->ot_rest_day_multiplier }}× rest day,
    {{ (float) $summary['settings']->ot_public_holiday_multiplier }}× public holiday;
    hourly rate = salary ÷ {{ $summary['settings']->monthly_working_days }} ÷ {{ (float) $summary['settings']->daily_working_hours }}.
    Approved overtime claims only.
    @if ($showStatutory)
        <br>
        EPF, SOCSO and EIS are calculated as a percentage of the capped wage; the published wage-band tables may
        differ by a few sen. PCB is an annualised estimate, not the LHDN MTD formula.
    @endif
    <br>
    Service charge is not included — it is distributed from the attendance grid and read on Attendance Record.
    <br>
    Generated {{ now()->format('d M Y, H:i') }} by {{ $exportedBy }}.
</div>
@endsection
