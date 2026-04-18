@extends('pdf.layout')

@section('title', 'Overtime Claim Form — ' . $employee->name)

@section('content')
    @include('pdf.partials.ot-claim-styles')
    @include('pdf.partials.ot-claim-page', compact(
        'company', 'employee', 'claims', 'totalHours', 'hoursByType',
        'submitters', 'approvers', 'from', 'to'
    ))
@endsection
