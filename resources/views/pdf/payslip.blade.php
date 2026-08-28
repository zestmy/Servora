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

    .postadj { margin-top: 8px; }
    .postadj td { padding: 2.5px 4px; font-size: 8pt; }

    .halves td { vertical-align: top; width: 50%; padding: 0; }
    .halves .gap { width: 12px; padding: 0; }

    .net { margin-top: 8px; background: #f1f5f9; border: 1px solid #cbd5e1; padding: 6px 8px; }
    .net-label { font-size: 7.5pt; text-transform: uppercase; letter-spacing: 1px; color: #475569; }
    .net-value { float: right; font-size: 13pt; font-weight: bold; }

    .note { margin-top: 6px; font-size: 6.5pt; color: #b45309; }
    .foot { margin-top: 6px; font-size: 6pt; color: #94a3b8; line-height: 1.4; }

    /* ── Employer contributions ──────────────────────────────────────────
       A section of its own, deliberately OUTSIDE the deductions column and
       below the net pay box. Both placements were wrong before it had one:
       a sixth of a person's EPF pot is contributed by the employer, and
       sitting it beside the employee's own deductions invites it to be read
       as money taken off the payslip. Below the net figure it can only be
       read as what it is — pay that exists and never passed through here. */
    .emp { margin-top: 8px; border: 1px solid #cbd5e1; border-top: 2px solid #475569; padding: 5px 8px 6px; }
    .emp-title { font-size: 6.5pt; font-weight: bold; text-transform: uppercase; letter-spacing: 0.8px; color: #475569; }
    .emp-sub { font-size: 6pt; color: #64748b; margin-top: 1px; }
    .emp table { margin-top: 3px; }
    .emp th { font-size: 6pt; font-weight: normal; text-transform: uppercase; letter-spacing: 0.5px;
              color: #64748b; text-align: right; padding: 2px 5px 0 5px; }
    .emp td { font-size: 8.5pt; text-align: right; padding: 0 5px 1px 5px; }
    .emp .total th, .emp .total td { color: #0f172a; font-weight: bold; border-left: 1px solid #cbd5e1; }
</style>
</head>
<body>
@foreach ($lines as $line)
    @php
        $allowances = $line->allowanceLines();
        $deductions = $line->deductionLines();
        // Two halves, because they enter the arithmetic in two places — see
        // PayrollRunLine::wageAdjustments().
        $wageAdjustments    = $line->wageAdjustments();
        $netAdjustments     = collect($line->netAdjustments());
        $netAdjustmentTotal = $line->netAdjustmentsTotal();
        $statutoryRows = array_values(array_filter([
            (float) $line->epf_employee   > 0 ? ['EPF (KWSP)',   (float) $line->epf_employee]   : null,
            (float) $line->socso_employee > 0 ? ['SOCSO',        (float) $line->socso_employee] : null,
            (float) $line->eis_employee   > 0 ? ['EIS (SIP)',    (float) $line->eis_employee]   : null,
            (float) $line->pcb            > 0 ? ['PCB (MTD)',    (float) $line->pcb]            : null,
            // SKBBK / LINDUNG 24 Jam. Named in full on the payslip because it
            // is new, employee-paid in full, and the first thing somebody will
            // query when their net drops.
            (float) $line->skbbk          > 0 ? ['SKBBK (LINDUNG 24 Jam)', (float) $line->skbbk] : null,
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
                            <td>
                                Basic salary
                                {{-- The working, for anybody whose basic is not
                                     simply their monthly figure. Somebody
                                     checking a payslip is checking the hours,
                                     the days, or the part-month — the total is
                                     the thing they cannot verify without them.

                                     A pro-rated month matters most here: a
                                     joiner who receives a third of a salary
                                     will ask why, and "12 of 31 days" answers
                                     it on the document they are holding. --}}
                                @if ($line->paid_hours !== null)
                                    <span class="sub">
                                        {{ rtrim(rtrim(number_format((float) $line->paid_hours, 2, '.', ''), '0'), '.') }} hrs
                                        &times; RM {{ number_format((float) $line->pay_rate, 2) }}
                                    </span>
                                @elseif ($line->isProrated())
                                    <span class="sub">
                                        {{ $line->prorationLabel() }} employed
                                        {{-- WHY, not just how much. "part month"
                                             told somebody their salary was short
                                             without saying what made it short,
                                             which is the question they were
                                             holding the payslip to ask. --}}
                                        &mdash; {{ $line->employmentNote() ?: 'part month' }}
                                    </span>
                                @elseif ($line->paid_days !== null)
                                    <span class="sub">
                                        {{ (int) $line->paid_days }} {{ \Illuminate\Support\Str::plural('day', (int) $line->paid_days) }}
                                        &times; RM {{ number_format((float) $line->pay_rate, 2) }}
                                    </span>
                                @endif
                            </td>
                            <td class="amt">{{ number_format((float) $line->basic, 2) }}</td>
                        </tr>
                        @foreach ($allowances as $a)
                            <tr>
                                <td>
                                    {{ $a['name'] ?? 'Allowance' }}
                                    {{-- Marked per line, because a reduced
                                         allowance sitting beside a full one
                                         reads as a mistake unless it says why. --}}
                                    @if ($a['prorated'] ?? false)
                                        <span class="sub">part month</span>
                                    @endif
                                </td>
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
        {{-- One-off corrections, ITEMISED. A payslip that is RM500 short of
                             what somebody expected has to say why on its face — a
                             net figure they cannot account for is the one thing
                             guaranteed to produce a conversation with the office.

                             ONLY THE ONES THAT COUNT AS WAGES BELONG HERE.
                             CompensationSummary puts those inside $gross and adds
                             the rest at NET and nowhere else, so listing an
                             after-statutory correction in this column printed a
                             column that did not add up to the Gross beneath it —
                             reported from a run where a −370.97 sat under an
                             816.13 basic above a Gross of 816.13. They are shown
                             below the two columns instead, where they apply. --}}
                        @foreach ($wageAdjustments as $adj)
                            <tr>
                                <td>{{ $adj['label'] ?? 'Adjustment' }}</td>
                                <td class="amt">{{ number_format((float) ($adj['amount'] ?? 0), 2) }}</td>
                            </tr>
                        @endforeach
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

        {{-- AFTER THE STATUTORY DEDUCTIONS, BEFORE NET — which is exactly
             where these are applied, so it is where they are printed.

             The reconciliation is spelled out rather than left to be worked
             out: this figure is the difference between a Gross and a Net that
             otherwise cannot be got from one to the other, and it is the line
             somebody rings the office about. --}}
        @if ($netAdjustments->isNotEmpty())
            <table class="money postadj">
                <tr>
                    <th>Adjustments after statutory deductions</th>
                    <th class="amt">RM</th>
                </tr>
                @foreach ($netAdjustments as $adj)
                    <tr>
                        <td>{{ $adj['label'] ?? 'Adjustment' }}</td>
                        <td class="amt">{{ number_format((float) ($adj['amount'] ?? 0), 2) }}</td>
                    </tr>
                @endforeach
                <tr class="rule">
                    <td colspan="2" class="sub">
                        Applied to take-home pay only — the contributions above are worked out on the
                        gross figure and are not changed by this.
                        Net pay is {{ number_format((float) $line->gross + (float) $line->deductions + (float) $line->service_charge, 2) }}
                        less {{ number_format((float) $line->statutory_employee + (float) $line->deductions, 2) }}
                        {{ $netAdjustmentTotal < 0 ? 'less' : 'plus' }} {{ number_format(abs($netAdjustmentTotal), 2) }}.
                    </td>
                </tr>
            </table>
        @endif

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
                {{-- Said on the payslip because the employee is the one person
                     who can tell us it is wrong, and the month they can still
                     do something about it is this one. --}}
                @if ($line->bank_account_name)
                    &middot; in the name of {{ $line->bank_account_name }}
                @endif
            </div>
        @endif

        {{-- ── Employer contributions ──────────────────────────────────────
             Its own section, because it is the part of the payslip people ask
             about and it used to be six words of grey 6pt text under the
             signature line. What it answers: your EPF statement will show more
             than the figure deducted from you, and this is where the rest of
             it comes from. Rendered even when every figure is zero — a blank
             row says "nothing was contributed this period", where an absent
             section says nothing at all and reads as an omission. --}}
        @php
            $employerRows = array_values(array_filter([
                ['EPF (KWSP)',     (float) $line->epf_employer],
                ['SOCSO',          (float) $line->socso_employer],
                ['EIS (SIP)',      (float) $line->eis_employer],
                // Only when it applies. HRD Corp is a levy on the employer's
                // payroll, not a contribution to anything the employee holds,
                // and most small operators are below the threshold entirely.
                (float) $line->hrdf_employer > 0 ? ['HRD Corp levy', (float) $line->hrdf_employer] : null,
            ]));
            $employerTotal = array_sum(array_column($employerRows, 1));
        @endphp
        <div class="emp">
            <div class="emp-title">Employer contributions</div>
            <div class="emp-sub">
                Paid by {{ $brandName }} on top of your pay for this period. Not deducted from you,
                and not included in the net figure above.
            </div>
            <table>
                <tr>
                    @foreach ($employerRows as [$label, $amount])
                        <th>{{ $label }}</th>
                    @endforeach
                    <th class="total">Total</th>
                </tr>
                <tr>
                    @foreach ($employerRows as [$label, $amount])
                        <td>{{ number_format($amount, 2) }}</td>
                    @endforeach
                    <td class="total">{{ number_format($employerTotal, 2) }}</td>
                </tr>
            </table>
        </div>

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
            This is a computer-generated payslip and needs no signature.
        </div>
    </div>
@endforeach
</body>
</html>
