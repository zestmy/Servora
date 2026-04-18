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
            'submitters'  => $group['submitters'],
            'approvers'   => $group['approvers'],
            'from'        => $from,
            'to'          => $to,
        ])
    @empty
        <div style="text-align: center; padding: 40px 0; color: #999; font-size: 12px;">
            No approved overtime claims found for the selected period.
        </div>
    @endforelse
@endsection
