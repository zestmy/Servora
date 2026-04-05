@extends('pdf.layout')

@section('title', 'Overtime Claim Form — ' . $employee->name)

@section('content')
    {{-- Header --}}
    <div class="header">
        <div class="header-left">
            <div class="company-name">{{ $company->brand_name ?? $company->name }}</div>
            @if ($company->registration_number)
                <div class="company-detail">Reg No: {{ $company->registration_number }}</div>
            @endif
            @if ($company->billing_address)
                <div class="company-detail">{{ $company->billing_address }}</div>
            @endif
        </div>
        <div class="header-right">
            <div class="doc-title">Overtime Claim Form</div>
            <div class="doc-status">Approved</div>
        </div>
    </div>

    {{-- Employee Info --}}
    <div class="info-grid">
        <div class="info-box">
            <h4>Employee Details</h4>
            <p class="name">{{ $employee->name }}</p>
            @if ($employee->position)
                <p>Position: {{ $employee->position }}</p>
            @endif
            <p>Outlet: {{ $claims->first()?->outlet?->name ?? '—' }}</p>
        </div>
        <div class="info-box">
            <h4>Claim Summary</h4>
            <table class="meta-table">
                <tr>
                    <td class="label">Period:</td>
                    <td class="value">
                        @if ($claims->count())
                            {{ $claims->first()->claim_date->format('d M Y') }} — {{ $claims->last()->claim_date->format('d M Y') }}
                        @else
                            —
                        @endif
                    </td>
                </tr>
                <tr>
                    <td class="label">Total Claims:</td>
                    <td class="value">{{ $claims->count() }}</td>
                </tr>
                <tr>
                    <td class="label">Total Hours:</td>
                    <td class="value" style="font-size: 11px; font-weight: bold;">{{ number_format($totalHours, 2) }} hrs</td>
                </tr>
            </table>
        </div>
    </div>

    {{-- Hours Breakdown by Type --}}
    @if ($hoursByType->count() > 1)
        <table style="width: 100%; margin-bottom: 10px; border-collapse: collapse;">
            <tr>
                @foreach ($hoursByType as $type => $hours)
                    <td style="padding: 4px 8px; border: 1px solid #ccc; font-size: 9px; text-align: center;">
                        <span style="color: #666; text-transform: uppercase; font-size: 7px; letter-spacing: 0.5px; display: block;">
                            {{ match($type) { 'normal_day' => 'Normal Day', 'public_holiday' => 'Public Holiday', 'rest_day' => 'Rest Day', default => ucfirst($type) } }}
                        </span>
                        <span style="font-weight: bold; font-size: 11px;">{{ number_format($hours, 2) }} hrs</span>
                    </td>
                @endforeach
            </tr>
        </table>
    @endif

    {{-- Claims Table --}}
    <table class="items">
        <thead>
            <tr>
                <th style="width: 8%;">#</th>
                <th style="width: 15%;">Date</th>
                <th style="width: 12%;">Day</th>
                <th class="center" style="width: 10%;">Start</th>
                <th class="center" style="width: 10%;">End</th>
                <th class="center" style="width: 10%;">Hours</th>
                <th style="width: 15%;">OT Type</th>
                <th style="width: 20%;">Reason</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($claims as $i => $claim)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td style="font-weight: 500;">{{ $claim->claim_date->format('d M Y') }}</td>
                    <td>{{ $claim->claim_date->format('l') }}</td>
                    <td class="center">{{ substr($claim->ot_time_start, 0, 5) }}</td>
                    <td class="center">{{ substr($claim->ot_time_end, 0, 5) }}</td>
                    <td class="center" style="font-weight: bold;">{{ number_format($claim->total_ot_hours, 2) }}</td>
                    <td>
                        {{ match($claim->ot_type) {
                            'normal_day'     => 'Normal Day',
                            'public_holiday' => 'Public Holiday',
                            'rest_day'       => 'Rest Day',
                            default          => ucfirst($claim->ot_type),
                        } }}
                    </td>
                    <td style="font-size: 8px;">{{ $claim->reason }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="5" style="text-align: right;">Total Overtime Hours</td>
                <td style="text-align: center;">{{ number_format($totalHours, 2) }}</td>
                <td colspan="2"></td>
            </tr>
        </tfoot>
    </table>

    {{-- Approval Info --}}
    @php $lastApproval = $claims->whereNotNull('approved_by')->sortByDesc('approved_at')->first(); @endphp
    @if ($lastApproval && $lastApproval->approver)
        <div style="margin-bottom: 8px; font-size: 8px; color: #666;">
            Approved by: <strong style="color: #000;">{{ $lastApproval->approver->name }}</strong>
            on {{ $lastApproval->approved_at?->format('d M Y, h:i A') }}
        </div>
    @endif

    {{-- Signatures --}}
    <div class="signatures">
        <div class="sig-box">
            <div style="height: 40px;"></div>
            <div class="sig-line">Employee Signature</div>
            <div class="sig-name">{{ $employee->name }}</div>
        </div>
        <div class="sig-box">
            <div style="height: 40px;"></div>
            <div class="sig-line">Verified By (HOD / Manager)</div>
        </div>
        <div class="sig-box">
            <div style="height: 40px;"></div>
            <div class="sig-line">Approved By (HR / Management)</div>
        </div>
    </div>
@endsection
