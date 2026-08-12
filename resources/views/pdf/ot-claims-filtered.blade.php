@extends('pdf.layout')

@section('title', 'Overtime Statement')

@section('content')
    @include('pdf.partials.ot-claim-styles')

    {{-- Same per-employee statement as the approved-only export, one page
         each, so the two documents read alike and nobody has to learn a
         second layout. What differs is which claims reach it: this one
         follows the screen's filters, including statuses the approved-only
         document will never show. --}}
    @forelse ($grouped as $idx => $group)
        @if ($idx > 0)
            <div style="page-break-before: always;"></div>
        @endif

        @include('pdf.partials.ot-claim-page', [
            'company'           => $company,
            'employee'          => $group['employee'],
            'claims'            => $group['claims'],
            'totalHours'        => $group['totalHours'],
            'hoursByType'       => $group['hoursByType'],
            'hoursBySettlement' => $group['hoursBySettlement'],
            'hoursByStatus'     => $group['hoursByStatus'],
            'submitters'        => $group['submitters'],
            'approvers'         => $group['approvers'],
            'calendarEvents'    => $calendarEvents,
            'from'              => $from,
            'to'                => $to,
            // What this document IS, in place of the approved-only export's
            // fixed "Approved" stamp.
            'docStatus'         => $filter->status
                ? ucfirst(str_replace('_', ' ', $filter->status))
                : 'All statuses',
            'showStatus'        => $showStatus,
            'filterSummary'     => $filterSummary,
            // Deliberately absent: the pending and rejected footers exist to
            // explain what an APPROVED-only page left out. Here those claims
            // are either on the page or excluded by a filter the header
            // already states, so repeating them would contradict it.
        ])
    @empty
        <div style="text-align: center; padding: 40px 0; color: #999; font-size: 12px;">
            <p style="margin: 0 0 6px; font-size: 13px; color: #666;">
                No overtime claims match the current filter.
            </p>
            @foreach ($filterSummary as $line)
                <div style="font-size: 10px;">{{ $line }}</div>
            @endforeach
        </div>
    @endforelse
@endsection
