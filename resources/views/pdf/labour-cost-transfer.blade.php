@extends('pdf.layout')

@section('title', 'Labour Cost Transfer — ' . $transfer->transfer_number)

@section('content')
    @include('pdf.partials.ot-claim-styles')
    @include('pdf.partials.transfer-doc-styles')

    @php
        $statusClass = ['confirmed' => '', 'draft' => 'draft', 'cancelled' => 'cancelled'][$transfer->status] ?? 'draft';
        $lines = $transfer->lines;

        // "18–20 Sep 2026", "30 Sep – 2 Oct 2026": one line per stint.
        $range = function ($s, $e) {
            if ($s->isSameDay($e)) return $s->format('j M Y');
            if ($s->isSameMonth($e)) return $s->format('j') . '–' . $e->format('j M Y');
            if ($s->isSameYear($e)) return $s->format('j M') . ' – ' . $e->format('j M Y');
            return $s->format('j M Y') . ' – ' . $e->format('j M Y');
        };
        // A real minus sign, so credits line up with the plus signs.
        $signed = fn (float $v) => ($v > 0 ? '+' : ($v < 0 ? '−' : '')) . number_format(abs($v), 2);
    @endphp

    <div class="ot-header">
        <div class="hl">
            @if ($logo = $company?->logoDataUri())
                <img src="{{ $logo }}" class="company-logo" alt="">
            @endif
            <div class="company-name">{{ $company->brand_name ?? $company->name }}</div>
            @if ($company->registration_number)
                <div class="company-detail">Reg No: {{ $company->registration_number }}</div>
            @endif
            @if ($company->billing_address)
                <div class="company-detail">{{ $company->billing_address }}</div>
            @endif
        </div>
        <div class="hr">
            <div class="doc-title">Labour Cost Transfer</div>
            <div class="doc-number">{{ $transfer->transfer_number }}</div>
            <div class="doc-status {{ $statusClass }}">{{ $transfer->statusLabel() }}</div>
        </div>
    </div>

    <div class="info-grid">
        <div class="info-cell left">
            <div class="info-card">
                <h4>Transfer Details</h4>
                <div class="info-body">
                    <div class="kv name"><div class="k">Charged To</div><div class="v">{{ $transfer->toOutlet?->name ?? '—' }}</div></div>
                    <div class="kv"><div class="k">Purpose</div><div class="v">{{ $transfer->purposeLabel() }} · {{ $transfer->transfer_date->format('d M Y') }}</div></div>
                    @if ($transfer->reference)
                        <div class="kv"><div class="k">Event / Ref</div><div class="v">{{ $transfer->reference }}</div></div>
                    @endif
                    <div class="kv">
                        <div class="k">Period</div>
                        <div class="v">
                            @if ($lines->count())
                                {{ $lines->min('date_start')->format('d M Y') }} — {{ $lines->max('date_end')->format('d M Y') }}
                            @else
                                —
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="info-cell right">
            <div class="info-card">
                <h4>Cost Summary</h4>
                <div class="info-body">
                    <div class="kv"><div class="k">Staff / Days</div><div class="v">{{ $lines->pluck('employee_id')->unique()->count() }} staff · {{ number_format((float) $lines->sum('days'), 1) }} days</div></div>
                    <div class="hours-breakdown">
                        <table class="hours-table">
                            <tr><td class="type-label">Daily salary</td><td class="type-hours">RM {{ number_format((float) $lines->sum('salary_amount'), 2) }}</td></tr>
                            <tr><td class="type-label">Overtime ({{ number_format((float) $lines->sum('ot_hours'), 2) }} hrs)</td><td class="type-hours">RM {{ number_format((float) $lines->sum('ot_amount'), 2) }}</td></tr>
                            <tr class="total"><td class="type-label">Total Transfer</td><td class="type-hours">RM {{ number_format((float) $lines->sum('total_amount'), 2) }}</td></tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="section-title">Employees</div>
    <table class="items compact">
        <thead>
            <tr>
                <th style="width: 3%;">#</th>
                <th style="width: 21%;">Employee</th>
                <th style="width: 14%;">From</th>
                <th style="width: 15%;">Dates</th>
                <th class="num" style="width: 6%;">Days</th>
                <th class="num" style="width: 8%;">Rate/Day</th>
                <th class="num" style="width: 9%;">Salary</th>
                <th class="num" style="width: 6%;">OT Hrs</th>
                <th class="num" style="width: 8%;">OT Cost</th>
                <th class="num" style="width: 10%;">Total (RM)</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($lines as $i => $l)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td style="font-weight: bold;">
                        {{ $l->employee_name }}
                        @if ($l->employee?->staff_id || $l->employee?->designation)
                            <span class="sub" style="font-weight: normal;">{{ collect([$l->employee?->staff_id, $l->employee?->designation])->filter()->join(' · ') }}</span>
                        @endif
                    </td>
                    <td style="white-space: nowrap;">{{ $l->fromOutlet?->name ?? '—' }}</td>
                    <td style="white-space: nowrap;">{{ $range($l->date_start, $l->date_end) }}</td>
                    <td class="num">{{ rtrim(rtrim(number_format((float) $l->days, 2), '0'), '.') }}</td>
                    <td class="num">{{ number_format((float) $l->daily_rate, 2) }}</td>
                    <td class="num">{{ number_format((float) $l->salary_amount, 2) }}</td>
                    <td class="num">{{ number_format((float) $l->ot_hours, 2) }}</td>
                    <td class="num">{{ number_format((float) $l->ot_amount, 2) }}</td>
                    <td class="num" style="font-weight: bold;">{{ number_format((float) $l->total_amount, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr class="grand">
                <td colspan="4" class="total-label" style="text-align: right;">Total</td>
                <td class="num">{{ number_format((float) $lines->sum('days'), 1) }}</td>
                <td></td>
                <td class="num">{{ number_format((float) $lines->sum('salary_amount'), 2) }}</td>
                <td class="num">{{ number_format((float) $lines->sum('ot_hours'), 2) }}</td>
                <td class="num">{{ number_format((float) $lines->sum('ot_amount'), 2) }}</td>
                <td class="num">{{ number_format((float) $lines->sum('total_amount'), 2) }}</td>
            </tr>
        </tfoot>
    </table>

    <div class="section-title">Summary by Outlet</div>
    <table class="items compact">
        <thead>
            <tr>
                <th style="width: 26%;">Outlet</th>
                <th class="num">Staff Sent</th>
                <th class="num">Days</th>
                <th class="num">OT Hrs</th>
                <th class="num">Salary Out</th>
                <th class="num">OT Out</th>
                <th class="num">Received</th>
                <th class="num">Net (RM)</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($summary as $row)
                <tr>
                    <td style="font-weight: bold;">{{ $outletNames[$row['outlet_id']] ?? '—' }}</td>
                    <td class="num">{{ $row['staff'] ?: '—' }}</td>
                    <td class="num">{{ $row['days'] ? number_format($row['days'], 1) : '—' }}</td>
                    <td class="num">{{ $row['ot_hours'] ? number_format($row['ot_hours'], 2) : '—' }}</td>
                    <td class="num">{{ $row['salary_out'] ? number_format($row['salary_out'], 2) : '—' }}</td>
                    <td class="num">{{ $row['ot_out'] ? number_format($row['ot_out'], 2) : '—' }}</td>
                    <td class="num">{{ $row['received'] ? number_format($row['received'], 2) : '—' }}</td>
                    <td class="num {{ $row['net'] > 0 ? 'net-pos' : ($row['net'] < 0 ? 'net-neg' : '') }}">{{ $signed($row['net']) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @if ($transfer->notes)
        <div class="notes"><h4>Notes</h4><p>{{ $transfer->notes }}</p></div>
    @endif

    <div class="signatures">
        <div class="sig-cell">
            <div class="sig-role">Prepared By</div>
            <div class="sig-name">{{ $transfer->createdBy?->name ?? '—' }}</div>
            <div class="sig-title">{{ $transfer->created_at?->format('d M Y, H:i') }}</div>
        </div>
        <div class="sig-cell">
            <div class="sig-role">Confirmed By</div>
            <div class="sig-name">{{ $transfer->confirmedBy?->name ?? '—' }}</div>
            <div class="sig-title">{{ $transfer->confirmed_at?->format('d M Y, H:i') ?? 'Not yet confirmed' }}</div>
        </div>
    </div>

    <div class="computer-generated-note">
        Net: + takes the cost on, − hands it off. Daily rate from the salary on file; overtime from approved claims settled in payroll.
        Computer-generated; no signature required.
    </div>
@endsection
