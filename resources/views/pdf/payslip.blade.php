<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    /* dompdf: absolute/table layout only — no flex, no grid. */
    @page { margin: 12mm 10mm; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 8.5pt; color: #0f172a; }

    /* Two payslips to an A4 page. One each would print fifty sheets for a
       fifty-person outlet, and a payslip is about twenty lines. */
    .slip { border: 1px solid #cbd5e1; padding: 10px 12px; margin-bottom: 10px; page-break-inside: avoid; }
    .slip:nth-child(2n) { margin-bottom: 18px; }

    .head { border-bottom: 1.5px solid #0f172a; padding-bottom: 6px; margin-bottom: 8px; }
    .brand { font-size: 11pt; font-weight: bold; }
    .co-meta { font-size: 6.5pt; color: #64748b; margin-top: 1px; }
    .doc { float: right; text-align: right; }
    .doc-title { font-size: 8pt; font-weight: bold; text-transform: uppercase; letter-spacing: 1.2px; color: #475569; }
    .doc-period { font-size: 9pt; font-weight: bold; margin-top: 2px; }
    .clear { clear: both; }

    table { width: 100%; border-collapse: collapse; }
    .who td { padding: 1px 0; font-size: 8pt; }
    .who .lbl { color: #64748b; width: 72px; }
    .who .val { font-weight: bold; }

    .money { margin-top: 8px; }
    .money th { font-size: 6.5pt; text-transform: uppercase; letter-spacing: 0.8px; color: #64748b;
                text-align: left; border-bottom: 1px solid #cbd5e1; padding: 3px 4px; }
    .money td { padding: 2.5px 4px; font-size: 8pt; vertical-align: top; }
    .money .amt { text-align: right; }
    .money .sub { color: #64748b; font-size: 6.5pt; }
    .money .rule td { border-top: 1px solid #e2e8f0; }
    .money .tot td { border-top: 1.5px solid #0f172a; font-weight: bold; padding-top: 4px; }

    .halves td { vertical-align: top; width: 50%; padding: 0; }
    .halves .gap { width: 12px; padding: 0; }

    .net { margin-top: 8px; background: #f1f5f9; border: 1px solid #cbd5e1; padding: 6px 8px; }
    .net-label { font-size: 7.5pt; text-transform: uppercase; letter-spacing: 1px; color: #475569; }
    .net-value { float: right; font-size: 13pt; font-weight: bold; }

    .note { margin-top: 6px; font-size: 6.5pt; color: #b45309; }
    .foot { margin-top: 6px; font-size: 6pt; color: #94a3b8; line-height: 1.4; }
</style>
</head>
<body>
@foreach ($lines as $line)
    @php
        $allowances = $line->allowanceLines();
        $deductions = $line->deductionLines();
        $statutoryRows = array_values(array_filter([
            (float) $line->epf_employee   > 0 ? ['EPF (KWSP)',   (float) $line->epf_employee]   : null,
            (float) $line->socso_employee > 0 ? ['SOCSO',        (float) $line->socso_employee] : null,
            (float) $line->eis_employee   > 0 ? ['EIS (SIP)',    (float) $line->eis_employee]   : null,
            (float) $line->pcb            > 0 ? ['PCB (MTD)',    (float) $line->pcb]            : null,
        ]));
    @endphp
    <div class="slip">
        <div class="head">
            <div class="doc">
                <div class="doc-title">Payslip</div>
                <div class="doc-period">{{ $run->periodLabel() }}</div>
                {{-- The dates actually worked for, when they are not the
                     calendar month. Someone checking a payslip against their
                     own record of hours needs the range, not the label. --}}
                @if ($run->hasCustomRange())
                    <div class="co-meta">{{ $run->rangeLabel() }}</div>
                @endif
                <div class="co-meta">{{ $run->reference }}</div>
            </div>
            {{-- Inlined as a data URI by Company::logoDataUri() — dompdf will
                 not fetch a remote image. Capped in height so a tall logo
                 cannot push the second slip off the page. --}}
            @if ($logoBase64 ?? null)
                <img src="{{ $logoBase64 }}" alt="" style="max-height: 30px; max-width: 150px; margin-bottom: 3px;">
            @endif
            <div class="brand">{{ $brandName }}</div>
            {{-- The legal entity, when it differs from the trading name. A
                 payslip is a record of employment by a COMPANY, and the brand
                 above the door is often not what is on the contract. --}}
            @if (($companyName ?? null) && $companyName !== $brandName)
                <div class="co-meta">{{ $companyName }}</div>
            @endif
            @if ($address)
                {{-- Collapsed to one line: dompdf honours the newlines in a
                     textarea-entered address and a five-line header would push
                     the second slip off the page. --}}
                <div class="co-meta">{{ preg_replace('/\s*\R\s*/', ', ', trim($address)) }}</div>
            @endif
            <div class="co-meta">
                @if ($companyReg) Co. No. {{ $companyReg }} @endif
                @if ($employerTaxNumber) &middot; E {{ $employerTaxNumber }} @endif
            </div>
            <div class="clear"></div>
        </div>

        <table class="who">
            <tr>
                <td class="lbl">Name</td><td class="val">{{ $line->employee_name }}</td>
                <td class="lbl">Staff ID</td><td class="val">{{ $line->staff_id ?: '—' }}</td>
            </tr>
            <tr>
                <td class="lbl">IC</td><td>{{ $line->ic_number ?: '—' }}</td>
                <td class="lbl">Position</td><td>{{ $line->designation ?: '—' }}</td>
            </tr>
            <tr>
                <td class="lbl">Outlet</td><td>{{ $line->outlet_name ?: '—' }}</td>
                <td class="lbl">EPF No.</td><td>{{ $line->epf_number ?: '—' }}</td>
            </tr>
        </table>

        {{-- Earnings and deductions side by side, the way a payslip is read. --}}
        <table class="halves money">
            <tr>
                <td>
                    <table>
                        <tr><th>Earnings</th><th class="amt">RM</th></tr>
                        <tr>
                            <td>Basic salary</td>
                            <td class="amt">{{ number_format((float) $line->basic, 2) }}</td>
                        </tr>
                        @foreach ($allowances as $a)
                            <tr>
                                <td>{{ $a['name'] ?? 'Allowance' }}</td>
                                <td class="amt">{{ number_format((float) ($a['amount'] ?? 0), 2) }}</td>
                            </tr>
                        @endforeach
                        @if ((float) $line->ot_amount > 0)
                            <tr>
                                <td>
                                    Overtime
                                    <span class="sub">{{ number_format((float) $line->ot_hours, 2) }} hours</span>
                                </td>
                                <td class="amt">{{ number_format((float) $line->ot_amount, 2) }}</td>
                            </tr>
                        @endif
                        @if ((float) $line->service_charge > 0)
                            @php $scd = $line->service_charge_detail ?? []; @endphp
                            <tr>
                                <td>
                                    Service charge
                                    @if (($scd['points'] ?? 0) > 0)
                                        <span class="sub">
                                            {{ rtrim(rtrim(number_format((float) $scd['points'], 2, '.', ''), '0'), '.') }} point(s)
                                            &times; RM {{ number_format((float) ($scd['per_point'] ?? 0)) }}
                                            @if ((float) ($scd['attendance'] ?? 0) + (float) ($scd['lateness'] ?? 0) + (float) ($scd['special'] ?? 0) > 0)
                                                , less deductions
                                            @endif
                                        </span>
                                    @endif
                                </td>
                                <td class="amt">{{ number_format((float) $line->service_charge, 2) }}</td>
                            </tr>
                        @endif
                        <tr class="tot">
                            <td>Gross</td>
                            <td class="amt">{{ number_format((float) $line->gross + (float) $line->deductions + (float) $line->service_charge, 2) }}</td>
                        </tr>
                    </table>
                </td>
                <td class="gap"></td>
                <td>
                    <table>
                        <tr><th>Deductions</th><th class="amt">RM</th></tr>
                        @forelse ($statutoryRows as [$label, $amount])
                            <tr>
                                <td>{{ $label }}</td>
                                <td class="amt">{{ number_format($amount, 2) }}</td>
                            </tr>
                        @empty
                        @endforelse
                        @foreach ($deductions as $d)
                            <tr>
                                <td>{{ $d['name'] ?? 'Deduction' }}</td>
                                <td class="amt">{{ number_format(abs((float) ($d['amount'] ?? 0)), 2) }}</td>
                            </tr>
                        @endforeach
                        {{-- A payslip with no deductions has to SAY so, or the
                             reader is left wondering whether something is missing. --}}
                        @if (! $statutoryRows && ! $deductions)
                            <tr><td colspan="2" class="sub">No deductions this period.</td></tr>
                        @endif
                        <tr class="tot">
                            <td>Total deductions</td>
                            <td class="amt">{{ number_format((float) $line->statutory_employee + (float) $line->deductions, 2) }}</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        <div class="net">
            <span class="net-label">Net pay</span>
            <span class="net-value">RM {{ number_format((float) $line->net, 2) }}</span>
            <div class="clear"></div>
        </div>

        @if ($line->bank_name || $line->bank_account_no)
            <div class="foot">
                Paid to {{ $line->bank_name ?: 'bank not recorded' }}
                @if ($line->bank_account_no)
                    &middot; a/c {{ $line->bank_account_no }}
                @endif
            </div>
        @endif

        @foreach (($line->statutory_notes ?? []) as $note)
            <div class="note">{{ $note }}</div>
        @endforeach

        @unless ($ratesConfirmed)
            <div class="note">
                Statutory rates were not confirmed against KWSP / PERKESO / LHDN when this payroll was run —
                EPF, SOCSO, EIS and PCB above are estimates.
            </div>
        @endunless

        <div class="foot">
            Employer contributions this period (not deducted from you): EPF
            {{ number_format((float) $line->epf_employer, 2) }} &middot; SOCSO
            {{ number_format((float) $line->socso_employer, 2) }} &middot; EIS
            {{ number_format((float) $line->eis_employer, 2) }}@if ((float) $line->hrdf_employer > 0) &middot; HRD Corp levy
            {{ number_format((float) $line->hrdf_employer, 2) }}@endif.
            This is a computer-generated payslip and needs no signature.
        </div>
    </div>
@endforeach
</body>
</html>
