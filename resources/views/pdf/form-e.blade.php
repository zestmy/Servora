<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    @page { margin: 12mm 10mm; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 8pt; color: #0f172a; }

    .head { border-bottom: 2px solid #0f172a; padding-bottom: 5px; margin-bottom: 9px; }
    .title { font-size: 13pt; font-weight: bold; }
    .subtitle { font-size: 7.5pt; color: #475569; margin-top: 1px; }
    .yr { float: right; text-align: right; }
    .yr-big { font-size: 15pt; font-weight: bold; }
    .clear { clear: both; }

    table { width: 100%; border-collapse: collapse; }
    .kv td { padding: 1.5px 0; font-size: 8pt; vertical-align: top; }
    .kv .lbl { color: #64748b; width: 32%; }

    .part { margin-top: 10px; }
    .part-h { background: #e2e8f0; font-weight: bold; font-size: 8pt;
              padding: 3px 5px; text-transform: uppercase; letter-spacing: 0.5px; }
    .rows td { padding: 3px 5px; font-size: 8pt; border-bottom: 1px solid #f1f5f9; }
    .rows .amt { text-align: right; width: 24%; }
    .rows .sub { color: #64748b; font-size: 6.5pt; }
    .rows .tot td { border-top: 1.5px solid #0f172a; border-bottom: none;
                    font-weight: bold; padding-top: 4px; }
    .unknown { color: #b45309; font-weight: bold; }

    .note { margin-top: 9px; padding: 5px 6px; border: 1px solid #fbbf24;
            background: #fffbeb; font-size: 6.5pt; color: #92400e; line-height: 1.5; }
    .foot { margin-top: 10px; font-size: 6.5pt; color: #64748b; line-height: 1.5; }
    .sign-line { border-top: 1px solid #475569; width: 45%; margin-top: 26px; padding-top: 3px; font-size: 7.5pt; }
</style>
</head>
<body>
    <div class="head">
        <div class="yr">
            <div class="subtitle">Year of remuneration</div>
            <div class="yr-big">{{ $year }}</div>
        </div>
        <div class="title">Borang E</div>
        <div class="subtitle">Return of remuneration by an employer &middot; C.P.8 &mdash; with CP8D listing</div>
        <div class="clear"></div>
    </div>

    {{-- Part A --}}
    <div class="part">
        <div class="part-h">A &nbsp; Particulars of employer</div>
        <table class="kv">
            <tr><td class="lbl">Name of employer</td><td><strong>{{ $employer['name'] ?: '—' }}</strong></td></tr>
            @if ($employer['brand'] && $employer['brand'] !== $employer['name'])
                <tr><td class="lbl">Trading as</td><td>{{ $employer['brand'] }}</td></tr>
            @endif
            @if ($employer['reg_no'])
                <tr><td class="lbl">Company registration no.</td><td>{{ $employer['reg_no'] }}</td></tr>
            @endif
            <tr>
                <td class="lbl">Employer's no. (E)</td>
                <td>@if ($employer['tax_number']){{ $employer['tax_number'] }}@else<span class="unknown">NOT SET</span>@endif</td>
            </tr>
            @if ($employer['epf_number'])
                <tr><td class="lbl">EPF employer no.</td><td>{{ $employer['epf_number'] }}</td></tr>
            @endif
            @if ($employer['address'])
                <tr>
                    <td class="lbl">Address</td>
                    <td>{{ preg_replace('/\s*\R\s*/', ', ', trim($employer['address'])) }}</td>
                </tr>
            @endif
        </table>
    </div>

    {{-- Part B --}}
    <div class="part">
        <div class="part-h">B &nbsp; Employees</div>
        <table class="rows">
            <tr>
                <td>1 &nbsp; Number of employees as at 31 December {{ $year }}</td>
                <td class="amt">{{ $headcount['at_year_end'] }}</td>
            </tr>
            <tr>
                <td>2 &nbsp; Number of new employees during {{ $year }}
                    <span class="sub">joining date falls inside the year</span></td>
                <td class="amt">{{ $headcount['new'] }}</td>
            </tr>
            <tr>
                <td>3 &nbsp; Number of employees who ceased employment during {{ $year }}</td>
                <td class="amt">{{ $headcount['ceased'] }}</td>
            </tr>
            <tr>
                <td>4 &nbsp; Of those, number who left Malaysia
                    <span class="sub">departure from the country is not recorded in this system</span></td>
                <td class="amt unknown">
                    {{ $headcount['ceased_left_malaysia'] === null ? 'NOT KNOWN' : $headcount['ceased_left_malaysia'] }}
                </td>
            </tr>
            <tr>
                <td>5 &nbsp; Number of employees subject to MTD (PCB)</td>
                <td class="amt">{{ $headcount['subject_to_mtd'] }}</td>
            </tr>
            <tr class="tot">
                <td>Employees with remuneration during {{ $year }}</td>
                <td class="amt">{{ $headcount['total_paid'] }}</td>
            </tr>
        </table>
    </div>

    {{-- Totals --}}
    <div class="part">
        <div class="part-h">C &nbsp; Total remuneration and deductions</div>
        <table class="rows">
            <tr><td>Gross salary, wages and leave pay</td><td class="amt">{{ number_format($totals['basic'], 2) }}</td></tr>
            <tr><td>Overtime</td><td class="amt">{{ number_format($totals['overtime'], 2) }}</td></tr>
            <tr><td>Allowances</td><td class="amt">{{ number_format($totals['allowances'], 2) }}</td></tr>
            <tr><td>Service charge</td><td class="amt">{{ number_format($totals['service_charge'], 2) }}</td></tr>
            <tr class="tot"><td>Total gross remuneration</td><td class="amt">{{ number_format($totals['gross_income'], 2) }}</td></tr>
        </table>
        <table class="rows" style="margin-top: 6px;">
            <tr><td>Total MTD (PCB) remitted</td><td class="amt">{{ number_format($totals['pcb'], 2) }}</td></tr>
            <tr><td>Total zakat deducted through salary</td><td class="amt">{{ number_format($totals['zakat'], 2) }}</td></tr>
            <tr><td>Total employee EPF contributions</td><td class="amt">{{ number_format($totals['epf_employee'], 2) }}</td></tr>
            <tr><td>Total employee SOCSO contributions</td><td class="amt">{{ number_format($totals['socso_employee'], 2) }}</td></tr>
            <tr><td>Total employee EIS contributions</td><td class="amt">{{ number_format($totals['eis_employee'], 2) }}</td></tr>
        </table>
    </div>

    {{-- Said on the return itself, because this is the document that gets filed. --}}
    <div class="note">
        <strong>This return is generated from payroll and is not complete on its own.</strong>
        The following cannot be answered from payroll data and are shown as nil or "NOT KNOWN" — that is
        not the same as knowing they are zero. Check each against your own records before filing:
        <ul style="margin: 3px 0 0 12px; padding: 0;">
            @foreach ($unanswered as $item)
                <li>{{ $item }}</li>
            @endforeach
        </ul>
        @unless ($ratesConfirmed)
            Statutory rates were not confirmed against KWSP / PERKESO / LHDN when this payroll was run,
            so the EPF, SOCSO, EIS and PCB totals above are estimates.
        @endunless
    </div>

    <div class="foot">
        Form E must reach LHDN by <strong>31 March {{ $year + 1 }}</strong>, with the CP8D listing.
        This is a field summary for checking and keying in — it is <strong>not</strong> the LHDN e-Filing
        submission itself, and the layout is not the certified upload format.
        Download the CP8D listing separately from the EA Forms screen.
    </div>

    <div class="sign-line">
        Signature &middot; {{ $employer['brand'] }}
        <div style="font-size: 6.5pt; color: #64748b; margin-top: 2px;">
            Name and designation of the person making this return
        </div>
    </div>
</body>
</html>
