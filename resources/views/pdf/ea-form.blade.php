<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    /* dompdf: table layout only — no flex, no grid. */
    @page { margin: 10mm 10mm; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 8pt; color: #0f172a; }

    /* One form per employee per page: an EA is a document someone files a tax
       return from, not a line on a listing. */
    .form { page-break-after: always; }
    .form:last-child { page-break-after: auto; }

    .head { border-bottom: 2px solid #0f172a; padding-bottom: 5px; margin-bottom: 8px; }
    .title { font-size: 12pt; font-weight: bold; }
    .subtitle { font-size: 7.5pt; color: #475569; margin-top: 1px; }
    .yr { float: right; text-align: right; }
    .yr-big { font-size: 14pt; font-weight: bold; }
    .clear { clear: both; }

    table { width: 100%; border-collapse: collapse; }
    .kv td { padding: 1.5px 0; font-size: 8pt; vertical-align: top; }
    .kv .lbl { color: #64748b; width: 34%; }

    .part { margin-top: 9px; }
    .part-h { background: #e2e8f0; font-weight: bold; font-size: 8pt;
              padding: 3px 5px; text-transform: uppercase; letter-spacing: 0.5px; }
    .rows td { padding: 2.5px 5px; font-size: 8pt; border-bottom: 1px solid #f1f5f9; }
    .rows .amt { text-align: right; width: 22%; }
    .rows .sub { color: #64748b; font-size: 6.5pt; }
    .rows .tot td { border-top: 1.5px solid #0f172a; border-bottom: none;
                    font-weight: bold; padding-top: 4px; }
    .nil { color: #94a3b8; }

    .note { margin-top: 8px; padding: 5px 6px; border: 1px solid #fbbf24;
            background: #fffbeb; font-size: 6.5pt; color: #92400e; line-height: 1.5; }
    .foot { margin-top: 10px; font-size: 6.5pt; color: #64748b; line-height: 1.5; }
    .sign { margin-top: 16px; font-size: 7.5pt; }
    .sign-line { border-top: 1px solid #475569; width: 45%; margin-top: 22px; padding-top: 3px; }
</style>
</head>
<body>
@foreach ($forms as $ea)
    <div class="form">
        <div class="head">
            <div class="yr">
                <div class="subtitle">Year of remuneration</div>
                <div class="yr-big">{{ $year }}</div>
            </div>
            <div class="title">Borang EA</div>
            <div class="subtitle">
                Statement of remuneration from employment &middot; C.P.8A &mdash; Pin. {{ $year }}
            </div>
            <div class="clear"></div>
        </div>

        {{-- Employer --}}
        <div class="part">
            <div class="part-h">Employer</div>
            <table class="kv">
                <tr>
                    <td class="lbl">Name</td>
                    <td>{{ $employer['name'] ?: '—' }}</td>
                </tr>
                @if ($employer['reg_no'])
                    <tr><td class="lbl">Company no.</td><td>{{ $employer['reg_no'] }}</td></tr>
                @endif
                <tr>
                    <td class="lbl">Employer's no. (E)</td>
                    <td>{{ $employer['tax_number'] ?: 'NOT SET' }}</td>
                </tr>
                @if ($employer['address'])
                    <tr>
                        <td class="lbl">Address</td>
                        <td>{{ preg_replace('/\s*\R\s*/', ', ', trim($employer['address'])) }}</td>
                    </tr>
                @endif
            </table>
        </div>

        {{-- Part A --}}
        <div class="part">
            <div class="part-h">A &nbsp; Particulars of employee</div>
            <table class="kv">
                <tr>
                    <td class="lbl">Name</td><td><strong>{{ $ea['name'] }}</strong></td>
                </tr>
                <tr><td class="lbl">IC / passport no.</td><td>{{ $ea['ic_number'] ?: '—' }}</td></tr>
                <tr><td class="lbl">Income tax no.</td><td>{{ $ea['tax_number'] ?: '—' }}</td></tr>
                <tr><td class="lbl">EPF no.</td><td>{{ $ea['epf_number'] ?: '—' }}</td></tr>
                <tr><td class="lbl">SOCSO no.</td><td>{{ $ea['socso_number'] ?: '—' }}</td></tr>
                <tr><td class="lbl">Staff no.</td><td>{{ $ea['staff_id'] ?: '—' }}</td></tr>
                <tr><td class="lbl">Designation</td><td>{{ $ea['designation'] ?: '—' }}</td></tr>
                <tr>
                    <td class="lbl">Period of employment</td>
                    <td>{{ $ea['from']->format('d/m/Y') }} &ndash; {{ $ea['to']->format('d/m/Y') }}
                        <span class="sub">({{ $ea['months'] }} month(s) of payroll)</span></td>
                </tr>
            </table>
        </div>

        {{-- Part B --}}
        <div class="part">
            <div class="part-h">B &nbsp; Employment income, benefits and living accommodation</div>
            <table class="rows">
                <tr>
                    <td>1 (a) &nbsp; Gross salary, wages or leave pay</td>
                    <td class="amt">{{ number_format($ea['basic'], 2) }}</td>
                </tr>
                <tr>
                    <td>
                        &nbsp; &nbsp; &nbsp; &nbsp; Overtime payments
                        <span class="sub">approved overtime paid through payroll</span>
                    </td>
                    <td class="amt">{{ number_format($ea['overtime'], 2) }}</td>
                </tr>
                <tr>
                    <td>&nbsp; &nbsp; &nbsp; &nbsp; Allowances</td>
                    <td class="amt">{{ number_format($ea['allowances'], 2) }}</td>
                </tr>
                <tr>
                    <td>
                        &nbsp; &nbsp; &nbsp; &nbsp; Service charge
                        <span class="sub">distributed from the pool collected on your behalf</span>
                    </td>
                    <td class="amt">{{ number_format($ea['service_charge'], 2) }}</td>
                </tr>
                <tr>
                    <td>1 (b) &nbsp; Benefits in kind
                        <span class="sub">not processed through payroll — see note below</span></td>
                    <td class="amt nil">0.00</td>
                </tr>
                <tr>
                    <td>1 (c) &nbsp; Value of living accommodation
                        <span class="sub">not processed through payroll — see note below</span></td>
                    <td class="amt nil">0.00</td>
                </tr>
                <tr>
                    <td>3 &nbsp; &nbsp; &nbsp; Tax exempt allowances, perquisites and gifts
                        <span class="sub">not classified in this system — see note below</span></td>
                    <td class="amt nil">0.00</td>
                </tr>
                <tr class="tot">
                    <td>Total gross remuneration</td>
                    <td class="amt">{{ number_format($ea['gross_income'], 2) }}</td>
                </tr>
            </table>
        </div>

        {{-- Part D --}}
        <div class="part">
            <div class="part-h">D &nbsp; Total deduction</div>
            <table class="rows">
                <tr>
                    <td>1 &nbsp; Monthly tax deduction (MTD / PCB)</td>
                    <td class="amt">{{ number_format($ea['pcb'], 2) }}</td>
                </tr>
                <tr>
                    <td>2 &nbsp; CP38 deduction
                        <span class="sub">no CP38 order is processed by this system</span></td>
                    <td class="amt nil">0.00</td>
                </tr>
                <tr>
                    <td>3 &nbsp; Zakat paid through salary deduction</td>
                    <td class="amt">{{ number_format($ea['zakat'], 2) }}</td>
                </tr>
                <tr>
                    <td>4 &nbsp; Relief claimed through TP1
                        <span class="sub">not processed through payroll — see note below</span></td>
                    <td class="amt nil">0.00</td>
                </tr>
                <tr class="tot">
                    <td>Total</td>
                    <td class="amt">{{ number_format($ea['pcb'] + $ea['zakat'], 2) }}</td>
                </tr>
            </table>
        </div>

        {{-- Part E --}}
        <div class="part">
            <div class="part-h">E &nbsp; Contributions</div>
            <table class="rows">
                <tr>
                    <td>1 &nbsp; Employee's contribution to approved provident fund (EPF)</td>
                    <td class="amt">{{ number_format($ea['epf_employee'], 2) }}</td>
                </tr>
                <tr>
                    <td>2 &nbsp; SOCSO contribution paid by the employee</td>
                    <td class="amt">{{ number_format($ea['socso_employee'], 2) }}</td>
                </tr>
                <tr>
                    <td>3 &nbsp; EIS (SIP) contribution paid by the employee</td>
                    <td class="amt">{{ number_format($ea['eis_employee'], 2) }}</td>
                </tr>
            </table>
        </div>

        {{-- Said on the form itself, not only on the screen it came from: this
             is the difference between a correct return and a quietly wrong one. --}}
        <div class="note">
            <strong>What this form does not include.</strong>
            It is generated from payroll and reports only what passed through it. The following are
            shown as nil because this system does not track them, NOT because they are necessarily zero —
            check each against your own records before issuing:
            @foreach ($ea['not_tracked'] as $i => $item){{ $i ? '; ' : '' }}{{ $item }}@endforeach.
            @unless ($ratesConfirmed)
                Statutory rates were not confirmed against KWSP / PERKESO / LHDN when this payroll was run,
                so EPF, SOCSO, EIS and PCB above are estimates.
            @endunless
        </div>

        <div class="foot">
            Built from {{ $ea['months'] }} approved payroll run(s) for {{ $year }}.
            Amounts are in Ringgit Malaysia.
            This form must be given to the employee by 28 February {{ $year + 1 }} and does not
            need to be submitted to LHDN by the employer.
        </div>

        <div class="sign">
            <div class="sign-line">
                Signature &middot; {{ $employer['brand'] }}
            </div>
            <div class="sub" style="font-size: 6.5pt; color: #64748b; margin-top: 2px;">
                Name and designation of the person issuing this statement
            </div>
        </div>
    </div>
@endforeach
</body>
</html>
