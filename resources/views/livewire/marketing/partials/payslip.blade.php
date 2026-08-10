{{-- The results, laid out as a payslip.

     Shared by the on-screen preview and the PDF so the file is the thing that
     was on screen — a download that differs from its preview is the download
     people stop trusting. The PDF wrapper supplies its own page styling; the
     classes here are ignored by dompdf, which reads the inline table rules. --}}
<table class="w-full text-sm" style="width:100%; border-collapse:collapse;">
    <tr>
        <td style="width:50%; vertical-align:top; padding-right:12px;">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500" style="font-size:9px; text-transform:uppercase; color:#6b7280; margin:0 0 4px;">
                Earnings
            </p>
            <table style="width:100%; border-collapse:collapse;">
                <tr>
                    <td style="padding:3px 0;">Basic salary</td>
                    <td style="padding:3px 0; text-align:right;" class="tabular-nums">{{ number_format($figures['basic'], 2) }}</td>
                </tr>

                @if ($figures['unpaid_leave'] > 0)
                    <tr>
                        <td style="padding:3px 0; color:#b91c1c;">
                            Less unpaid leave
                            ({{ rtrim(rtrim(number_format($figures['unpaid_days'], 1), '0'), '.') }}
                            day{{ $figures['unpaid_days'] == 1 ? '' : 's' }})
                        </td>
                        <td style="padding:3px 0; text-align:right; color:#b91c1c;" class="tabular-nums">
                            ({{ number_format($figures['unpaid_leave'], 2) }})
                        </td>
                    </tr>
                @endif

                @foreach ($lines as $line)
                    @if (($line['amount'] ?? 0) > 0)
                        <tr>
                            <td style="padding:3px 0;">{{ $line['label'] ?: 'Allowance' }}</td>
                            <td style="padding:3px 0; text-align:right;" class="tabular-nums">{{ number_format($line['amount'], 2) }}</td>
                        </tr>
                    @endif
                @endforeach

                @if ($figures['overtime'] > 0)
                    <tr>
                        <td style="padding:3px 0;">Overtime</td>
                        <td style="padding:3px 0; text-align:right;" class="tabular-nums">{{ number_format($figures['overtime'], 2) }}</td>
                    </tr>
                @endif

                @if ($figures['service_charge'] > 0)
                    <tr>
                        <td style="padding:3px 0;">Service charge</td>
                        <td style="padding:3px 0; text-align:right;" class="tabular-nums">{{ number_format($figures['service_charge'], 2) }}</td>
                    </tr>
                @endif

                <tr>
                    <td style="padding:6px 0 0; border-top:1px solid #e5e7eb; font-weight:bold;">Gross pay</td>
                    <td style="padding:6px 0 0; border-top:1px solid #e5e7eb; text-align:right; font-weight:bold;" class="tabular-nums">
                        {{ number_format($figures['gross'], 2) }}
                    </td>
                </tr>
            </table>
        </td>

        <td style="width:50%; vertical-align:top; padding-left:12px;">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500" style="font-size:9px; text-transform:uppercase; color:#6b7280; margin:0 0 4px;">
                Deductions
            </p>
            <table style="width:100%; border-collapse:collapse;">
                <tr>
                    <td style="padding:3px 0;">EPF ({{ rtrim(rtrim(number_format($figures['epf_rate'], 2), '0'), '.') }}%)</td>
                    <td style="padding:3px 0; text-align:right;" class="tabular-nums">{{ number_format($figures['epf_employee'], 2) }}</td>
                </tr>
                <tr>
                    <td style="padding:3px 0;">SOCSO</td>
                    <td style="padding:3px 0; text-align:right;" class="tabular-nums">{{ number_format($figures['socso_employee'], 2) }}</td>
                </tr>
                <tr>
                    <td style="padding:3px 0;">EIS</td>
                    <td style="padding:3px 0; text-align:right;" class="tabular-nums">{{ number_format($figures['eis_employee'], 2) }}</td>
                </tr>
                <tr>
                    <td style="padding:3px 0;">PCB (estimate)</td>
                    <td style="padding:3px 0; text-align:right;" class="tabular-nums">{{ number_format($figures['pcb'], 2) }}</td>
                </tr>
                <tr>
                    <td style="padding:6px 0 0; border-top:1px solid #e5e7eb; font-weight:bold;">Total deductions</td>
                    <td style="padding:6px 0 0; border-top:1px solid #e5e7eb; text-align:right; font-weight:bold;" class="tabular-nums">
                        {{ number_format($figures['deductions'], 2) }}
                    </td>
                </tr>
            </table>

            {{-- The employer's side, on the payslip rather than hidden: it is
                 what the person actually costs, and the number a pay
                 conversation usually forgets. --}}
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500" style="font-size:9px; text-transform:uppercase; color:#6b7280; margin:14px 0 4px;">
                Employer contributions
            </p>
            <table style="width:100%; border-collapse:collapse;">
                <tr>
                    <td style="padding:3px 0;">EPF</td>
                    <td style="padding:3px 0; text-align:right;" class="tabular-nums">{{ number_format($figures['epf_employer'], 2) }}</td>
                </tr>
                <tr>
                    <td style="padding:3px 0;">SOCSO</td>
                    <td style="padding:3px 0; text-align:right;" class="tabular-nums">{{ number_format($figures['socso_employer'], 2) }}</td>
                </tr>
                <tr>
                    <td style="padding:3px 0;">EIS</td>
                    <td style="padding:3px 0; text-align:right;" class="tabular-nums">{{ number_format($figures['eis_employer'], 2) }}</td>
                </tr>
                <tr>
                    <td style="padding:6px 0 0; border-top:1px solid #e5e7eb; font-weight:bold;">Cost to employer</td>
                    <td style="padding:6px 0 0; border-top:1px solid #e5e7eb; text-align:right; font-weight:bold;" class="tabular-nums">
                        {{ number_format($figures['employer_cost'], 2) }}
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<table style="width:100%; border-collapse:collapse; margin-top:14px;">
    <tr>
        <td style="background:#f3f4f6; padding:10px 12px; font-weight:bold; font-size:13px;">NET PAY</td>
        <td style="background:#f3f4f6; padding:10px 12px; text-align:right; font-weight:bold; font-size:15px;" class="tabular-nums">
            RM {{ number_format($figures['net'], 2) }}
        </td>
    </tr>
</table>
