<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        /* Scoped reset — `html` must NOT be reset: dompdf implements @page
           margins via the root element, so html { margin: 0 } zeroes them. */
        body, div, p, h1, table, tr, td { margin: 0; padding: 0; }
        @@page { margin: 16mm 14mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1f2937; }
        h1 { font-size: 17px; margin-bottom: 2px; }
        .sub  { color: #6b7280; font-size: 9px; margin-bottom: 14px; }
        .rule { border-bottom: 1.5px solid #1f2937; margin-bottom: 12px; }
        .foot { margin-top: 20px; border-top: 1px solid #e5e7eb; padding-top: 8px;
                color: #6b7280; font-size: 8px; line-height: 1.5; }
    </style>
</head>
<body>
    <h1>Salary breakdown</h1>
    <p class="sub">
        {{ $employeeName ?: 'Estimated payslip' }} &middot; {{ now()->format('F Y') }}
        &middot; prepared with the free calculator at servora.com.my
    </p>
    <div class="rule"></div>

    @include('livewire.marketing.partials.payslip', ['figures' => $figures, 'lines' => $lines])

    <p class="foot">
        <strong>PCB is an estimate.</strong> It applies the standard reliefs to an annualised salary
        and divides by twelve. The real MTD schedule also accounts for the year to date, zakat, other
        reliefs claimed and prior deductions — check with LHDN before filing.
        EPF, SOCSO and EIS use the published rates and are calculated on different wage bases:
        EPF on basic and fixed allowances, SOCSO and EIS on those plus overtime, and tax on everything
        including service charge. Overtime is priced on basic salary over 26 days.
        This document is an estimate, not a payslip issued by an employer.
    </p>
</body>
</html>
