@extends('pdf.layout')

@section('title', 'Overtime Claim Form — ' . $employee->name)

@section('content')
    @include('pdf.partials.ot-claim-styles')
    {{-- hoursBySettlement is named here rather than left to reach the partial
         through the parent's scope. It arrived that way for a year and the
         all-employees view, which has no such variable to inherit, printed
         time-off hours as payable the whole time. --}}
    @include('pdf.partials.ot-claim-page', compact(
        'company', 'employee', 'claims', 'totalHours', 'hoursByType',
        'hoursBySettlement',
        'submitters', 'approvers', 'calendarEvents', 'from', 'to',
        'pendingHours', 'rejectedClaims', 'timeOffHours'
    ))
@endsection
