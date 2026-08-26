@extends('pdf.layout')

@section('title', 'Overtime Claim Forms')

@section('content')
    @include('pdf.partials.ot-claim-styles')

    @forelse ($grouped as $idx => $group)
        @if ($idx > 0)
            <div style="page-break-before: always;"></div>
        @endif

        @include('pdf.partials.ot-claim-page', [
            'company'     => $company,
            'employee'    => $group['employee'],
            'claims'      => $group['claims'],
            'totalHours'  => $group['totalHours'],
            'hoursByType' => $group['hoursByType'],
            {{-- Passed EXPLICITLY. The single-employee view got this by
                 inheriting its parent's variables, which is why the split
                 rendered there and silently vanished here: each page's figure
                 lives inside its own $group and nothing handed it over, so the
                 partial fell back to an empty collection and printed time-off
                 hours as though they were payable. --}}
            'hoursBySettlement' => $group['hoursBySettlement'] ?? collect(),
            'submitters'  => $group['submitters'],
            'approvers'   => $group['approvers'],
            'calendarEvents' => $calendarEvents,
            'from'        => $from,
            'to'          => $to,
            'pendingHours' => $group['pendingHours'] ?? 0,
            'rejectedClaims' => $group['rejectedClaims'] ?? collect(),
            'timeOffHours' => $group['timeOffHours'] ?? 0,
        ])
    @empty
        <div style="text-align: center; padding: 40px 0; color: #999; font-size: 12px;">
            No approved overtime claims found for the selected period.
            @if (!empty($narrowedBy))
                <div style="margin-top: 6px; font-size: 10px;">
                    Narrowed by — {{ implode(' · ', $narrowedBy) }}
                </div>
            @endif
        </div>
    @endforelse
@endsection
