@extends('pdf.layout')

@section('title', 'Overtime Claim Form — ' . $employee->name)

@section('content')
    {{-- Header --}}
    <div class="header">
        <div class="header-left">
            @if ($company?->logo)
                <img src="{{ public_path('storage/' . $company->logo) }}" class="company-logo" alt="">
            @endif
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
                        @if (!empty($from) && !empty($to))
                            {{ \Carbon\Carbon::parse($from)->format('d M Y') }} — {{ \Carbon\Carbon::parse($to)->format('d M Y') }}
                        @elseif ($claims->count())
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
                <th style="width: 6%;">#</th>
                <th style="width: 14%;">Date</th>
                <th style="width: 12%;">Day</th>
                <th class="center" style="width: 9%;">Start</th>
                <th class="center" style="width: 9%;">End</th>
                <th class="center" style="width: 9%;">Hours</th>
                <th style="width: 14%;">OT Type</th>
                <th style="width: 27%;">Reason</th>
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

    {{-- Signatures --}}
    <div class="signatures">
        <div class="sig-box">
            <div style="height: 40px;"></div>
            <div class="sig-line">Employee Signature</div>
            <div class="sig-name">{{ $employee->name }}</div>
            @if ($employee->position)
                <div style="font-size: 8px; color: #666;">{{ $employee->position }}</div>
            @endif
        </div>
        <div class="sig-box">
            <div style="height: 40px;"></div>
            <div class="sig-line">Verified By</div>
            @foreach ($submitters as $s)
                <div class="sig-name">{{ $s->name }}</div>
                @if ($s->designation)
                    <div style="font-size: 8px; color: #666;">{{ $s->designation }}</div>
                @endif
            @endforeach
        </div>
        <div class="sig-box">
            <div style="height: 40px;"></div>
            <div class="sig-line">Approved By</div>
            @foreach ($approvers as $a)
                <div class="sig-name">{{ $a->name }}</div>
                @if ($a->designation)
                    <div style="font-size: 8px; color: #666;">{{ $a->designation }}</div>
                @endif
            @endforeach
        </div>
    </div>
@endsection
