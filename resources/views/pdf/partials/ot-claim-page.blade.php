{{-- Single-employee OT claim page.
     Required variables:
       $company, $employee, $claims, $totalHours, $hoursByType,
       $submitters, $approvers, $from, $to --}}

{{-- Header --}}
<div class="ot-header">
    <div class="hl">
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
    <div class="hr">
        <div class="doc-title">Overtime Claim Form</div>
        <div class="doc-status">Approved</div>
    </div>
</div>

{{-- Employee + Claim summary --}}
<div class="info-grid">
    <div class="info-cell left">
        <div class="info-card">
            <h4>Employee Details</h4>
            <div class="info-body">
                <div class="kv name"><div class="k">Name</div><div class="v">{{ $employee->name }}</div></div>
                @if ($employee->staff_id)
                    <div class="kv"><div class="k">Staff ID</div><div class="v">{{ $employee->staff_id }}</div></div>
                @endif
                @if ($employee->designation)
                    <div class="kv"><div class="k">Designation</div><div class="v">{{ $employee->designation }}</div></div>
                @endif
                @if ($employee->section)
                    <div class="kv"><div class="k">Section</div><div class="v">{{ $employee->section->name }}</div></div>
                @endif
                <div class="kv"><div class="k">Outlet</div><div class="v">{{ $claims->first()?->outlet?->name ?? '—' }}</div></div>
            </div>
        </div>
    </div>

    <div class="info-cell right">
        <div class="info-card">
            <h4>Claim Summary</h4>
            <div class="info-body">
                <div class="kv">
                    <div class="k">Period</div>
                    <div class="v">
                        @if (!empty($from) && !empty($to))
                            {{ \Carbon\Carbon::parse($from)->format('d M Y') }} — {{ \Carbon\Carbon::parse($to)->format('d M Y') }}
                        @elseif ($claims->count())
                            {{ $claims->first()->claim_date->format('d M Y') }} — {{ $claims->last()->claim_date->format('d M Y') }}
                        @else
                            —
                        @endif
                    </div>
                </div>
                <div class="kv"><div class="k">Total Claims</div><div class="v">{{ $claims->count() }}</div></div>

                @php
                    $typeLabels = [
                        'normal_day'     => 'Normal Day',
                        'public_holiday' => 'Public Holiday',
                        'rest_day'       => 'Rest Day',
                    ];
                @endphp
                <div class="hours-breakdown">
                    <div class="bt-label">Hours by OT Type</div>
                    <table class="hours-table">
                        @foreach ($typeLabels as $type => $label)
                            @if (($hoursByType[$type] ?? 0) > 0)
                                <tr>
                                    <td class="type-label">{{ $label }}</td>
                                    <td class="type-hours">{{ number_format($hoursByType[$type], 2) }} hrs</td>
                                </tr>
                            @endif
                        @endforeach
                        @foreach ($hoursByType as $type => $hours)
                            @if (! array_key_exists($type, $typeLabels) && $hours > 0)
                                <tr>
                                    <td class="type-label">{{ ucfirst(str_replace('_', ' ', $type)) }}</td>
                                    <td class="type-hours">{{ number_format($hours, 2) }} hrs</td>
                                </tr>
                            @endif
                        @endforeach
                        <tr class="total">
                            <td class="type-label">Total OT</td>
                            <td class="type-hours">{{ number_format($totalHours, 2) }} hrs</td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

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
                        default          => ucfirst(str_replace('_', ' ', $claim->ot_type)),
                    } }}
                </td>
                <td style="font-size: 8pt;">{{ $claim->reason }}</td>
            </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr>
            <td colspan="5" style="text-align: right; font-weight: bold;">Total Overtime Hours</td>
            <td class="center" style="font-weight: bold;">{{ number_format($totalHours, 2) }}</td>
            <td colspan="2"></td>
        </tr>
    </tfoot>
</table>

{{-- Approvals (digital — see note below) --}}
<div class="signatures">
    <div class="sig-cell">
        <div class="sig-role">Employee</div>
        <div class="sig-name">{{ $employee->name }}</div>
        @if ($employee->designation)
            <div class="sig-title">{{ $employee->designation }}@if ($employee->staff_id) · {{ $employee->staff_id }}@endif</div>
        @elseif ($employee->staff_id)
            <div class="sig-title">{{ $employee->staff_id }}</div>
        @endif
    </div>

    <div class="sig-cell">
        <div class="sig-role">Verified By</div>
        @if ($submitters->count())
            @foreach ($submitters as $s)
                <div class="sig-name">{{ $s->name }}</div>
                @if ($s->designation)
                    <div class="sig-title">{{ $s->designation }}</div>
                @endif
            @endforeach
        @else
            <div class="sig-name">—</div>
        @endif
    </div>

    <div class="sig-cell">
        <div class="sig-role">Approved By</div>
        @if ($approvers->count())
            @foreach ($approvers as $a)
                <div class="sig-name">{{ $a->name }}</div>
                @if ($a->designation)
                    <div class="sig-title">{{ $a->designation }}</div>
                @endif
            @endforeach
        @else
            <div class="sig-name">—</div>
        @endif
    </div>
</div>

<div class="computer-generated-note">
    This is a computer-generated document; therefore, no signature is required.
    All approvals have been completed digitally.
</div>
