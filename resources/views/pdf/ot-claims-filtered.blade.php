@extends('pdf.layout')

@section('title', 'Overtime Claims')

@section('content')
    {{-- The Overtime Claims LIST, printed as it appears on screen — same
         columns, same order, same rows. Deliberately not the per-employee
         claim form, which is a different document with signature blocks and
         approved hours only; this one reproduces what somebody is looking at,
         including who submitted each claim and its status. --}}
    <style>
        .ot-title      { font-size: 15pt; font-weight: bold; color: #104d4f; }
        .ot-sub        { font-size: 9pt; color: #6b7280; margin-top: 2px; }
        .ot-filters    { margin: 8px 0 10px; padding: 6px 9px; background: #f8fafc;
                         border: 1px solid #e2e8f0; border-radius: 4px; font-size: 8.5pt; color: #475569; }
        .ot-filters strong { color: #1f2937; }

        table.ot-list  { width: 100%; border-collapse: collapse; font-size: 8pt; }
        table.ot-list thead th {
            background: #104d4f; color: #fff; font-weight: bold; text-align: left;
            padding: 5px 6px; border: 1px solid #0d3f41; font-size: 7.5pt;
            text-transform: uppercase; letter-spacing: 0.3px;
        }
        table.ot-list tbody td { padding: 5px 6px; border: 1px solid #e2e8f0; vertical-align: top; }
        /* Zebra striping survives a photocopy better than hairline rules. */
        table.ot-list tbody tr:nth-child(even) td { background: #f8fafc; }
        table.ot-list tfoot td {
            padding: 6px; border: 1px solid #cbd5e1; background: #f1f5f9; font-weight: bold; font-size: 8.5pt;
        }
        .c   { text-align: center; }
        .r   { text-align: right; }
        .sub { font-size: 7pt; color: #6b7280; }
        .evt { font-size: 6.5pt; color: #b91c1c; display: block; }
        .pill { font-size: 7pt; font-weight: bold; }
    </style>

    <div class="ot-title">Overtime Claims</div>
    <div class="ot-sub">
        {{ $company->brand_name ?? $company->name }}
        &nbsp;·&nbsp; Generated {{ now()->format('d M Y, g:ia') }} by {{ $generatedBy }}
    </div>

    {{-- What was narrowed, on the document itself. A filtered list that does
         not say how it was filtered gets read as the complete record the
         moment it leaves the browser. --}}
    <div class="ot-filters">
        <strong>Showing:</strong> {{ implode('  ·  ', $filterSummary) }}
        &nbsp;·&nbsp; <strong>{{ $claims->count() }}</strong> claim(s)
    </div>

    <table class="ot-list">
        <thead>
            <tr>
                <th style="width: 13%;">Date</th>
                <th style="width: 22%;">Employee</th>
                <th class="c" style="width: 10%;">Time</th>
                <th class="c" style="width: 6%;">Hours</th>
                <th style="width: 11%;">Type</th>
                <th style="width: 26%;">Reason</th>
                <th class="c" style="width: 12%;">Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($claims as $claim)
                <tr>
                    <td>
                        {{ $claim->claim_date->format('d M Y') }}
                        <span class="sub">({{ $claim->claim_date->format('D') }})</span>
                        @foreach (\App\Models\CalendarEvent::onDate($calendarEvents, $claim->claim_date, $claim->employee?->outlet_id) as $ev)
                            <span class="evt">{{ $ev->title }}</span>
                        @endforeach
                    </td>
                    <td>
                        {{ $claim->employee?->name ?? '—' }}
                        {{-- Who raised it and when, which is the whole reason
                             this document exists rather than the claim form:
                             a claim disputed weeks later is argued over the
                             timing as much as the hours. --}}
                        <span class="sub" style="display: block;">
                            by {{ $claim->submitter?->name ?? 'unknown' }}@if ($claim->created_at) on {{ $claim->created_at->format('d M Y, g:ia') }}@endif
                        </span>
                    </td>
                    <td class="c">{{ substr($claim->ot_time_start, 0, 5) }} – {{ substr($claim->ot_time_end, 0, 5) }}</td>
                    <td class="c" style="font-weight: bold;">{{ number_format($claim->total_ot_hours, 1) }}h</td>
                    <td style="color: {{ match($claim->ot_type) {
                        'public_holiday' => '#b91c1c',
                        'rest_day'       => '#a16207',
                        default          => '#4b5563',
                    } }};">{{ $claim->otTypeLabel() }}</td>
                    <td>{{ $claim->reason }}</td>
                    <td class="c">
                        <span class="pill" style="color: {{ match($claim->status) {
                            'approved'  => '#15803d',
                            'rejected'  => '#b91c1c',
                            'submitted' => '#a16207',
                            default     => '#6b7280',
                        } }};">{{ ucfirst($claim->status) }}</span>
                        {{-- Rejected without the reason is an unanswerable
                             row; approved without the approver is unauditable. --}}
                        @if ($claim->status === 'rejected' && $claim->rejected_reason)
                            <span class="sub" style="display: block;">{{ $claim->rejected_reason }}</span>
                        @elseif ($claim->status === 'approved' && $claim->approver)
                            <span class="sub" style="display: block;">by {{ $claim->approver->name }}</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="c" style="padding: 24px; color: #9ca3af;">
                        No overtime claims match this filter.
                    </td>
                </tr>
            @endforelse
        </tbody>
        @if ($claims->isNotEmpty())
            <tfoot>
                <tr>
                    <td colspan="3" class="r">Total</td>
                    <td class="c">{{ number_format($totalHours, 1) }}h</td>
                    <td colspan="3">
                        {{-- Split by status, because a single total spanning
                             approved and rejected claims is a figure nobody can
                             use. Only when there is more than one to split. --}}
                        @if ($hoursByStatus->count() > 1)
                            <span style="font-weight: normal; font-size: 7.5pt; color: #475569;">
                                {{-- Separator as an expression, not @if: Blade
                                     does not recognise a directive glued to the
                                     preceding character, so "…h@if" compiles
                                     the @endif and leaves the @if as text. --}}
                                @foreach ($hoursByStatus as $status => $hrs)
                                    {{ ucfirst($status) }} {{ number_format($hrs, 1) }}h{{ $loop->last ? '' : '  ·  ' }}
                                @endforeach
                            </span>
                        @endif
                    </td>
                </tr>
            </tfoot>
        @endif
    </table>
@endsection
