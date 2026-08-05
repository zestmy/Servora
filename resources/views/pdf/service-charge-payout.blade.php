@extends('pdf.layout')

@section('title', 'Service Charge Payout — ' . $from->format('d M Y') . ' to ' . $to->format('d M Y'))

@section('content')
<style>
    /* Two slips to an A4 page: a payout slip is a dozen lines, and one page
       each would print fifty sheets for a fifty-person outlet. */
    .slip {
        border: 1px solid #cbd5e1;
        padding: 14px 16px;
        margin-bottom: 14px;
        page-break-inside: avoid;
    }
    .slip-head { border-bottom: 1px solid #e2e8f0; padding-bottom: 8px; margin-bottom: 10px; }
    .slip-brand { font-size: 10pt; font-weight: bold; color: #0f172a; }
    .slip-doc   { font-size: 7.5pt; text-transform: uppercase; letter-spacing: 1.4px; color: #64748b; font-weight: bold; }
    .slip-period { font-size: 8pt; color: #475569; margin-top: 2px; }

    .slip-who { font-size: 11pt; font-weight: bold; color: #0f172a; }
    .slip-meta { font-size: 8pt; color: #64748b; margin-top: 1px; }

    table.lines { width: 100%; border-collapse: collapse; font-size: 8.5pt; margin-top: 10px; }
    table.lines td { padding: 4px 0; vertical-align: top; }
    table.lines td.r { text-align: right; white-space: nowrap; }
    table.lines td.desc { color: #475569; }
    table.lines td.sub { color: #94a3b8; font-size: 7.5pt; }
    table.lines tr.rule td { border-top: 1px solid #e2e8f0; }
    table.lines tr.net td {
        border-top: 2px solid #0f172a; padding-top: 6px;
        font-size: 10.5pt; font-weight: bold; color: #0f766e;
    }
    .slip-foot { margin-top: 8px; font-size: 7pt; color: #94a3b8; line-height: 1.4; }
    .neg { color: #b91c1c; }
</style>

@foreach ($rows as $row)
    @php
        $emp = $row['employee'];
        // Everything after gross is a deduction, so a slip that shows none
        // still has to explain why the net equals the gross.
        $hasDeductions = $row['dedAmt'] > 0 || $row['lateAmt'] > 0 || $row['specialAmt'] > 0;
    @endphp
    <div class="slip">
        <div class="slip-head">
            <div class="slip-brand">{{ $brandName }}</div>
            <div class="slip-doc">Service Charge Payout</div>
            <div class="slip-period">
                {{ $from->format('d M Y') }} – {{ $to->format('d M Y') }}
                @if ($outletName) &middot; {{ $outletName }} @endif
            </div>
        </div>

        <div class="slip-who">{{ $emp->name }}</div>
        <div class="slip-meta">
            @if ($emp->staff_id) {{ $emp->staff_id }} &middot; @endif
            {{ $emp->designation ?? '—' }}
            @if ($emp->outlet?->name) &middot; {{ $emp->outlet->name }} @endif
        </div>

        <table class="lines">
            <tr>
                <td class="desc">
                    Service points
                    <span class="sub">{{ number_format($row['points'], 2) }} × RM {{ number_format($serviceCharge['perPoint']) }} per point</span>
                </td>
                <td class="r">{{ number_format($row['gross'], 2) }}</td>
            </tr>

            @if ($row['dedAmt'] > 0)
                <tr>
                    <td class="desc">
                        Attendance deduction
                        <span class="sub">
                            @if ($row['mcDays'] > 0) {{ $row['mcDays'] }} MC/SL day(s) @endif
                            @if ($row['mcDays'] > 0 && $row['absDays'] > 0) + @endif
                            @if ($row['absDays'] > 0) {{ $row['absDays'] }} absent day(s) @endif
                            = {{ rtrim(rtrim(number_format($row['dedPct'], 2, '.', ''), '0'), '.') }}% of gross
                        </span>
                    </td>
                    <td class="r neg">-{{ number_format($row['dedAmt'], 2) }}</td>
                </tr>
            @endif

            @if ($row['lateAmt'] > 0)
                <tr>
                    <td class="desc">
                        Lateness
                        <span class="sub">
                            {{ $row['lateMins'] }} minute(s)
                            @if ($lateRate > 0)
                                × RM {{ rtrim(rtrim(number_format($lateRate, 2, '.', ''), '0'), '.') }} per minute
                            @endif
                        </span>
                    </td>
                    <td class="r neg">-{{ number_format($row['lateAmt'], 2) }}</td>
                </tr>
            @endif

            @if ($row['specialAmt'] > 0)
                <tr>
                    <td class="desc">
                        Special deduction
                        @if ($row['specialNote'] !== '')
                            <span class="sub">{{ $row['specialNote'] }}</span>
                        @endif
                    </td>
                    <td class="r neg">-{{ number_format($row['specialAmt'], 2) }}</td>
                </tr>
            @endif

            @unless ($hasDeductions)
                <tr>
                    <td class="desc sub" colspan="2">No deductions this period.</td>
                </tr>
            @endunless

            <tr class="net">
                <td>Net payable</td>
                <td class="r">RM {{ number_format($row['net'], 2) }}</td>
            </tr>
        </table>

        <div class="slip-foot">
            The pool for this period was RM {{ number_format($serviceCharge['collected'], 2) }} collected,
            @if ($serviceCharge['retentionPct'] > 0)
                less {{ rtrim(rtrim(number_format($serviceCharge['retentionPct'], 2, '.', ''), '0'), '.') }}% retained,
            @endif
            giving RM {{ number_format($serviceCharge['distributable'], 2) }} shared over
            {{ number_format($serviceCharge['totalPoints'], 2) }} points.
            @if ($serviceCharge['fundPoints'] > 0)
                That total includes {{ number_format($serviceCharge['fundPoints'], 2) }} points allocated to
                {{ collect($serviceCharge['funds'])->pluck('name')->join(', ', ' and ') }}.
            @endif
            <br>
            Generated {{ now()->format('d M Y, H:i') }} by {{ $exportedBy }}.
        </div>
    </div>
@endforeach
@endsection
