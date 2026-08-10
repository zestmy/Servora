<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    /* dompdf: table layout only — no flex, no grid. DejaVu because the core
       fonts are Latin-1 and print anything outside it as a question mark. */
    @page { margin: 12mm 12mm; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 8.5pt; color: #0f172a; }

    .head { border-bottom: 2px solid #0f172a; padding-bottom: 6px; margin-bottom: 10px; }
    .title { font-size: 13pt; font-weight: bold; }
    .subtitle { font-size: 8pt; color: #475569; margin-top: 2px; }
    .yr { float: right; text-align: right; }
    .yr-big { font-size: 15pt; font-weight: bold; }
    .clear { clear: both; }

    table { width: 100%; border-collapse: collapse; }
    .kv td { padding: 2px 0; vertical-align: top; }
    .kv .lbl { color: #64748b; width: 32%; }

    .part { margin-top: 10px; }
    .part-h { background: #e2e8f0; font-weight: bold; padding: 3px 5px;
              text-transform: uppercase; letter-spacing: 0.5px; font-size: 8pt; }
    .rows td { padding: 3px 5px; border-bottom: 1px solid #f1f5f9; }
    .rows .amt { text-align: right; width: 24%; }
    .rows .sub { color: #64748b; font-size: 7pt; }
    .rows .tot td { border-top: 1.5px solid #0f172a; border-bottom: none;
                    font-weight: bold; padding-top: 5px; }

    .grid-h th { background: #f1f5f9; font-size: 7pt; padding: 3px 4px;
                 border-bottom: 1px solid #cbd5e1; text-align: right; }
    .grid-h th.m { text-align: left; }
    .grid-b td { font-size: 7pt; padding: 2.5px 4px; text-align: right;
                 border-bottom: 1px solid #f1f5f9; }
    .grid-b td.m { text-align: left; color: #475569; }
    .grid-b tr.tot td { font-weight: bold; border-top: 1px solid #0f172a; border-bottom: none; }

    .note { margin-top: 10px; padding: 6px 7px; border: 1px solid #fbbf24;
            background: #fffbeb; font-size: 7pt; color: #92400e; line-height: 1.5; }
    .foot { margin-top: 10px; font-size: 7pt; color: #64748b; line-height: 1.5; }
    .sign { margin-top: 18px; font-size: 8pt; }
    .sign-line { border-top: 1px solid #475569; width: 45%; margin-top: 26px; padding-top: 3px; }
</style>
</head>
<body>

@php
    $rm = fn ($n) => number_format((float) $n, 2);
    $dt = function (?string $d) {
        try { return $d ? \Carbon\Carbon::parse($d)->format('d/m/Y') : '—'; }
        catch (\Throwable) { return '—'; }
    };
@endphp

<div class="head">
    <div class="yr">
        <div class="subtitle">Year of remuneration</div>
        <div class="yr-big">{{ $year }}</div>
    </div>
    <div class="title">Borang EA</div>
    <div class="subtitle">Statement of remuneration from employment &middot; in the form of C.P.8A</div>
    <div class="clear"></div>
</div>

<div class="part">
    <div class="part-h">Employer</div>
    <table class="kv">
        <tr><td class="lbl">Name</td><td><strong>{{ $employer['name'] ?: '—' }}</strong></td></tr>
        @if ($employer['reg_no'])
            <tr><td class="lbl">Company no.</td><td>{{ $employer['reg_no'] }}</td></tr>
        @endif
        <tr><td class="lbl">Employer's no. (E)</td><td>{{ $employer['tax_no'] ?: '—' }}</td></tr>
        @if ($employer['address'])
            <tr>
                <td class="lbl">Address</td>
                <td>{{ preg_replace('/\s*\R\s*/', ', ', trim($employer['address'])) }}</td>
            </tr>
        @endif
    </table>
</div>

<div class="part">
    <div class="part-h">A &nbsp; Particulars of employee</div>
    <table class="kv">
        <tr><td class="lbl">Name</td><td><strong>{{ $employee['name'] }}</strong></td></tr>
        <tr><td class="lbl">IC / passport no.</td><td>{{ $employee['ic_number'] ?: '—' }}</td></tr>
        <tr><td class="lbl">Income tax no.</td><td>{{ $employee['tax_number'] ?: '—' }}</td></tr>
        <tr><td class="lbl">EPF no.</td><td>{{ $employee['epf_number'] ?: '—' }}</td></tr>
        <tr><td class="lbl">SOCSO no.</td><td>{{ $employee['socso_number'] ?: '—' }}</td></tr>
        <tr><td class="lbl">Staff no.</td><td>{{ $employee['staff_id'] ?: '—' }}</td></tr>
        <tr><td class="lbl">Designation</td><td>{{ $employee['designation'] ?: '—' }}</td></tr>
        <tr>
            <td class="lbl">Period of employment</td>
            <td>{{ $dt($employee['from']) }} &ndash; {{ $dt($employee['to']) }}</td>
        </tr>
    </table>
</div>

<div class="part">
    <div class="part-h">B &nbsp; Employment income, benefits and living accommodation</div>
    <table class="rows">
        <tr><td>1 (a) &nbsp; Gross salary, wages or leave pay</td>
            <td class="amt">{{ $rm($figures['income']['salary']) }}</td></tr>
        <tr><td>&nbsp; &nbsp; &nbsp; &nbsp; Overtime payments</td>
            <td class="amt">{{ $rm($figures['income']['overtime']) }}</td></tr>
        <tr><td>&nbsp; &nbsp; &nbsp; &nbsp; Allowances</td>
            <td class="amt">{{ $rm($figures['income']['allowances']) }}</td></tr>
        <tr><td>&nbsp; &nbsp; &nbsp; &nbsp; Service charge
                <span class="sub">distributed from the pool collected on the employer's behalf</span></td>
            <td class="amt">{{ $rm($figures['income']['service_charge']) }}</td></tr>
        <tr><td>&nbsp; &nbsp; &nbsp; &nbsp; Bonus, gratuity, commission</td>
            <td class="amt">{{ $rm($figures['income']['bonus']) }}</td></tr>
        <tr><td>&nbsp; &nbsp; &nbsp; &nbsp; Director's fee</td>
            <td class="amt">{{ $rm($figures['income']['director_fee']) }}</td></tr>
        <tr><td>&nbsp; &nbsp; &nbsp; &nbsp; Other gross remuneration</td>
            <td class="amt">{{ $rm($figures['income']['other']) }}</td></tr>
        <tr><td>1 (b) &nbsp; Benefits in kind</td>
            <td class="amt">{{ $rm($figures['income']['bik']) }}</td></tr>
        <tr><td>1 (c) &nbsp; Value of living accommodation</td>
            <td class="amt">{{ $rm($figures['income']['accommodation']) }}</td></tr>
        <tr class="tot">
            <td>Total gross remuneration</td>
            <td class="amt">{{ $rm($figures['gross']) }}</td>
        </tr>
        <tr>
            <td>3 &nbsp; &nbsp; &nbsp; Tax exempt allowances, perquisites and gifts
                <span class="sub">reported, and correctly excluded from the total above</span></td>
            <td class="amt">{{ $rm($figures['income']['exempt']) }}</td>
        </tr>
    </table>
</div>

<div class="part">
    <div class="part-h">D &nbsp; Total deduction</div>
    <table class="rows">
        <tr><td>1 &nbsp; Monthly tax deduction (MTD / PCB)</td>
            <td class="amt">{{ $rm($figures['deductions']['pcb']) }}</td></tr>
        <tr><td>2 &nbsp; CP38 deduction</td>
            <td class="amt">{{ $rm($figures['deductions']['cp38']) }}</td></tr>
        <tr><td>3 &nbsp; Zakat paid through salary deduction</td>
            <td class="amt">{{ $rm($figures['deductions']['zakat']) }}</td></tr>
        <tr><td>4 &nbsp; Relief claimed through TP1</td>
            <td class="amt">{{ $rm($figures['deductions']['tp1']) }}</td></tr>
        <tr class="tot">
            <td>Total</td>
            <td class="amt">{{ $rm($figures['total_deducted']) }}</td>
        </tr>
    </table>
</div>

<div class="part">
    <div class="part-h">E &nbsp; Contributions</div>
    <table class="rows">
        <tr><td>1 &nbsp; Employee's contribution to approved provident fund (EPF)</td>
            <td class="amt">{{ $rm($figures['contributions']['epf_employee']) }}</td></tr>
        <tr><td>&nbsp; &nbsp; Employer's contribution to approved provident fund (EPF)</td>
            <td class="amt">{{ $rm($figures['contributions']['epf_employer']) }}</td></tr>
        <tr><td>2 &nbsp; SOCSO contribution paid by the employee</td>
            <td class="amt">{{ $rm($figures['contributions']['socso_employee']) }}</td></tr>
        <tr><td>3 &nbsp; EIS (SIP) contribution paid by the employee</td>
            <td class="amt">{{ $rm($figures['contributions']['eis_employee']) }}</td></tr>
    </table>
</div>

@if (! empty($months))
    {{-- The working, printed with the answer. Somebody checking this form
         against a drawer of payslips should not have to open the tool again to
         see which month a figure came from. --}}
    <div class="part">
        <div class="part-h">Working &ndash; month by month, as entered</div>
        <table>
            <thead>
                <tr class="grid-h">
                    <th class="m">Month</th>
                    @foreach ($monthLabels as $col)
                        <th>{{ $col['label'] }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody class="grid-b">
                @php $totals = array_fill_keys(array_keys($monthLabels), 0.0); @endphp
                @foreach ($months as $i => $row)
                    @php $rowSum = array_sum(array_map(fn ($v) => (float) $v, $row)); @endphp
                    @continue($rowSum <= 0)
                    <tr>
                        <td class="m">{{ \Carbon\Carbon::create($year, $i + 1, 1)->format('M') }}</td>
                        @foreach ($monthLabels as $key => $col)
                            @php $totals[$key] += (float) ($row[$key] ?? 0); @endphp
                            <td>{{ $rm($row[$key] ?? 0) }}</td>
                        @endforeach
                    </tr>
                @endforeach
                <tr class="tot">
                    <td class="m">Total</td>
                    @foreach ($monthLabels as $key => $col)
                        <td>{{ $rm($totals[$key]) }}</td>
                    @endforeach
                </tr>
            </tbody>
        </table>
    </div>
@endif

<div class="note">
    <strong>This is a working copy in the structure of Borang EA, not LHDN's own C.P.8A.</strong>
    Every figure above is exactly as entered — nothing was recalculated. Check it against
    the payslips it came from before issuing it, and file using the form LHDN publishes.
</div>

<div class="sign">
    <div class="sign-line">Signature and company stamp</div>
    <div style="margin-top: 3px; color: #64748b;">Date: ____________________</div>
</div>

<div class="foot">
    Prepared with the free Borang EA generator at servora.com.my &middot;
    generated {{ now()->format('d/m/Y') }}.
</div>

</body>
</html>
