{{-- Service charge distribution, on its own.

     It used to be appended to the attendance grid, which put a landscape
     day-by-day matrix and a payout table on the same page and left both
     cramped. They answer different questions and get signed by different
     people, so they are two documents now. --}}
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Service Charge Distribution</title>
    @include('pdf.partials.attendance-styles')
</head>
<body>

    @include('pdf.partials.attendance-header', ['docTitle' => 'Service Charge Distribution'])

    @if (! empty($serviceCharge) && $serviceCharge['row'])
        @php
            $fmtPct = fn ($v) => rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.');
        @endphp
        <div class="sc">
            {{-- No heading of its own: the masthead above already says what
                 this is. It carried one when it was a section appended to the
                 attendance grid, where it had to announce itself. --}}
            <div class="sc-meta">
                Pool RM {{ number_format((float) $serviceCharge['row']->amount, 2) }}
                · Total Service Points {{ number_format($serviceCharge['totalPoints'], 2) }}
                · RM {{ number_format($serviceCharge['perPoint']) }} / point
                · MC deduction {{ $fmtPct($serviceCharge['mcPct']) }}% / day
                · Absent deduction {{ $fmtPct($serviceCharge['absPct']) }}% / day
            </div>
            <table class="sc-table">
                @php
                    // Optional column pairs only appear when they have something
                    // to show, so the usual month keeps a wide name column.
                    // dompdf honours widths literally, so rather than maintain a
                    // hand-tuned set per shape, weights are normalised to 100 —
                    // which cannot drift out of sync when a column is added.
                    $hasLate    = $serviceCharge['hasLate'] ?? false;
                    $hasSpecial = $serviceCharge['hasSpecial'] ?? false;

                    $weights = ['#' => 3, 'name' => 18, 'outlet' => 11, 'pts' => 7, 'mc' => 6, 'abs' => 6];
                    if ($hasLate)    $weights['latemin'] = 6;
                    $weights += ['pct' => 8, 'gross' => 10, 'ded' => 10];
                    if ($hasLate)    $weights['latermk'] = 9;
                    if ($hasSpecial) $weights['special'] = 9;
                    $weights['net'] = 11;

                    $sum = array_sum($weights);
                    $w   = array_map(fn ($x) => round($x * 100 / $sum, 2), $weights);

                    // Columns before the money columns, for the total row's span.
                    $leadSpan = 6 + ($hasLate ? 1 : 0) + 1;
                @endphp
                <thead>
                    <tr>
                        <th style="width: {{ $w['#'] }}%;">#</th>
                        <th style="width: {{ $w['name'] }}%;">Name</th>
                        <th style="width: {{ $w['outlet'] }}%;">Outlet</th>
                        <th style="width: {{ $w['pts'] }}%;">Svc Pts</th>
                        <th style="width: {{ $w['mc'] }}%;">MC Days</th>
                        <th style="width: {{ $w['abs'] }}%;">ABS Days</th>
                        @if ($hasLate)
                            <th style="width: {{ $w['latemin'] }}%;">Late (min)</th>
                        @endif
                        <th style="width: {{ $w['pct'] }}%;">Deduction %</th>
                        <th style="width: {{ $w['gross'] }}%;">Gross (RM)</th>
                        <th style="width: {{ $w['ded'] }}%;">Deduction (RM)</th>
                        @if ($hasLate)
                            <th style="width: {{ $w['latermk'] }}%;">Late (RM)</th>
                        @endif
                        @if ($hasSpecial)
                            <th style="width: {{ $w['special'] }}%;">Special (RM)</th>
                        @endif
                        <th style="width: {{ $w['net'] }}%;">Net (RM)</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($serviceCharge['rows'] as $i => $scRow)
                        <tr>
                            <td class="c" style="color: #6b7280;">{{ $i + 1 }}</td>
                            <td class="l" style="font-weight: bold;">{{ $scRow['employee']->name }}</td>
                            <td class="l">{{ $scRow['employee']->outlet?->name }}</td>
                            <td class="r">{{ $scRow['points'] > 0 ? number_format($scRow['points'], 2) : '—' }}</td>
                            <td class="c" style="{{ $scRow['mcDays'] > 0 ? 'color: #92400e; font-weight: bold;' : 'color: #cbd5e1;' }}">{{ $scRow['mcDays'] ?: '—' }}</td>
                            <td class="c" style="{{ $scRow['absDays'] > 0 ? 'color: #b91c1c; font-weight: bold;' : 'color: #cbd5e1;' }}">{{ $scRow['absDays'] ?: '—' }}</td>
                            @if ($hasLate)
                                <td class="c" style="{{ $scRow['lateMins'] > 0 ? 'color: #b91c1c; font-weight: bold;' : 'color: #cbd5e1;' }}">{{ $scRow['lateMins'] ?: '—' }}</td>
                            @endif
                            <td class="r" style="{{ $scRow['dedPct'] > 0 ? 'color: #b91c1c;' : 'color: #cbd5e1;' }}">{{ $scRow['dedPct'] > 0 ? $fmtPct($scRow['dedPct']) . '%' : '—' }}</td>
                            <td class="r">{{ $scRow['points'] > 0 ? number_format($scRow['gross'], 2) : '—' }}</td>
                            <td class="r" style="{{ $scRow['dedAmt'] > 0 ? 'color: #b91c1c;' : 'color: #cbd5e1;' }}">{{ $scRow['dedAmt'] > 0 ? '-' . number_format($scRow['dedAmt'], 2) : '—' }}</td>
                            @if ($hasLate)
                                <td class="r" style="{{ $scRow['lateAmt'] > 0 ? 'color: #b91c1c;' : 'color: #cbd5e1;' }}">{{ $scRow['lateAmt'] > 0 ? '-' . number_format($scRow['lateAmt'], 2) : '—' }}</td>
                            @endif
                            @if ($hasSpecial)
                                <td class="r" style="{{ $scRow['specialAmt'] > 0 ? 'color: #b91c1c;' : 'color: #cbd5e1;' }}">
                                    {{ $scRow['specialAmt'] > 0 ? '-' . number_format($scRow['specialAmt'], 2) : '—' }}
                                    @if ($scRow['specialAmt'] > 0 && $scRow['specialNote'] !== '')
                                        <span style="display: block; font-size: 6pt; color: #6b7280;">{{ $scRow['specialNote'] }}</span>
                                    @endif
                                </td>
                            @endif
                            <td class="r" style="font-weight: bold; color: #0f766e;">{{ $scRow['points'] > 0 ? number_format($scRow['net'], 2) : '—' }}</td>
                        </tr>
                    @endforeach
                    <tr class="sc-total">
                        <td class="l" colspan="{{ $leadSpan }}">Total</td>
                        <td class="r">{{ number_format($serviceCharge['totals']['gross'], 2) }}</td>
                        <td class="r" style="color: #b91c1c;">-{{ number_format($serviceCharge['totals']['deduction'], 2) }}</td>
                        @if ($hasLate)
                            <td class="r" style="color: #b91c1c;">-{{ number_format($serviceCharge['totals']['lateAmt'], 2) }}</td>
                        @endif
                        @if ($hasSpecial)
                            <td class="r" style="color: #b91c1c;">-{{ number_format($serviceCharge['totals']['specialAmt'], 2) }}</td>
                        @endif
                        <td class="r" style="color: #0f766e;">{{ number_format($serviceCharge['totals']['net'], 2) }}</td>
                    </tr>
                    @foreach ($serviceCharge['funds'] ?? [] as $fund)
                        {{-- Paid from the same pool at the same rate, so they belong
                             on the same table rather than in a note under it. --}}
                        <tr>
                            <td class="l" colspan="{{ $leadSpan }}" style="font-style: italic;">{{ $fund['name'] }}</td>
                            <td class="r">{{ number_format($fund['amount'], 2) }}</td>
                            <td colspan="{{ 1 + ($hasLate ? 1 : 0) + ($hasSpecial ? 1 : 0) }}"></td>
                            <td class="r">{{ number_format($fund['amount'], 2) }}</td>
                        </tr>
                    @endforeach
                    @if (! empty($serviceCharge['funds']))
                        <tr class="sc-total">
                            <td class="l" colspan="{{ $leadSpan + 2 + ($hasLate ? 1 : 0) + ($hasSpecial ? 1 : 0) }}">
                                Allocated of RM {{ number_format($serviceCharge['distributable'], 2) }} distributable
                            </td>
                            <td class="r" style="color: #0f766e;">{{ number_format($serviceCharge['allocated'], 2) }}</td>
                        </tr>
                    @endif
                </tbody>
            </table>
            <div class="sc-note">
                Distributable = collected RM {{ number_format($serviceCharge['collected'] ?? 0, 2) }}
                − {{ $fmtPct($serviceCharge['retentionPct'] ?? 0) }}% retention
                = RM {{ number_format($serviceCharge['distributable'] ?? 0, 2) }}.
                Gross = Service Points × RM/point (distributable ÷ total points of all active employees in the selected outlet, rounded down to the nearest RM).
                @if (($serviceCharge['fundPoints'] ?? 0) > 0)
                    Total points include {{ number_format($serviceCharge['fundPoints'], 2) }} allocated to funds, paid at the same rate.
                @endif
                Deduction = MC days × {{ $fmtPct($serviceCharge['mcPct']) }}%
                + Absent days × {{ $fmtPct($serviceCharge['absPct']) }}% of gross, capped at 100%.
                MC days count codes named MC or SL, or labelled "Sick"; ABS uses the built-in Absent code.
                @if ($hasLate)
                    Late (RM) is the web clock-in charge for minutes past the rostered start, after grace — one charge per shift, taken after the percentage deduction and never below a net of zero.
                @endif
                @if ($hasSpecial)
                    Special (RM) is agreed per person for this period and is taken last, never below a net of zero.
                @endif
                Employees without Service Points are excluded from the split.
            </div>
        </div>
    @endif

    @if (empty($serviceCharge) || ! $serviceCharge['row'])
        <div style="text-align: center; padding: 30px 0; color: #999; font-size: 9px;">
            No service charge pool has been saved for this period and outlet.
        </div>
    @endif

    @include('pdf.partials.attendance-signatures')

</body>
</html>
