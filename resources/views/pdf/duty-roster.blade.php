@extends('pdf.layout')

@section('title', 'Duty Roster — ' . $periodLabel)

@section('content')
    <div class="header">
        <div class="header-left">
            @if ($company?->logo)
                <img src="{{ public_path('storage/' . $company->logo) }}" class="company-logo" alt="">
            @endif
            <div class="company-name">{{ $company?->name ?? 'Servora' }}</div>
            <div class="company-detail">{{ $roster->outlet?->name ?? 'Unknown Outlet' }}</div>
        </div>
        <div class="header-right">
            <div class="doc-title">Duty Roster</div>
            <div class="doc-number">{{ $periodLabel }}</div>
            <div style="font-size: 9px; color: #666; margin-top: 4px;">
                @if ($roster->section)
                    Section: {{ $roster->section->name }}
                @else
                    All Sections
                @endif
                &middot;
                <span style="text-transform: uppercase;">{{ $roster->status }}</span>
            </div>
        </div>
    </div>

    <style>
        .roster-table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 8px; }
        .roster-table th { background: #333; color: #fff; padding: 6px 4px; text-align: center; font-size: 7px; text-transform: uppercase; }
        .roster-table th.left { text-align: left; }
        .roster-table td { padding: 5px 4px; border-bottom: 1px solid #ddd; text-align: center; vertical-align: middle; }
        .roster-table td.left { text-align: left; }
        .roster-table tr:nth-child(even) { background: #f9f9f9; }
        .roster-table .emp-name { font-weight: bold; font-size: 9px; }
        .roster-table .emp-designation { font-size: 7px; color: #666; }
        .roster-table .station-name { font-size: 7px; color: #374151; font-weight: 500; margin-top: 2px; }
        .roster-table .shift { font-size: 8px; padding: 2px 4px; border-radius: 3px; display: inline-block; }
        .roster-table .off { font-weight: bold; }
        .roster-table .remark { font-size: 6px; color: #7c3aed; background: #f3e8ff; padding: 1px 3px; border-radius: 2px; display: inline-block; margin-top: 1px; }
        .roster-table .total { font-weight: bold; background: #f0f9ff; }
        .day-remark-row td { background: #fef3c7 !important; font-size: 8px; color: #92400e; padding: 4px !important; font-weight: 600; }
        .day-remark-row .remark-text { font-size: 7px; font-weight: normal; display: block; margin-top: 2px; }
        .summary-box { margin-top: 15px; padding: 10px; background: #f9fafb; border: 1px solid #e5e7eb; font-size: 9px; }
        .summary-box .row { display: flex; justify-content: space-between; padding: 3px 0; }
        .summary-box .label { color: #666; }
        .summary-box .value { font-weight: bold; }
        /* Shift color coding */
        .shift-opening { background: #d1fae5; color: #065f46; }
        .shift-middle { background: #e0f2fe; color: #0369a1; }
        .shift-closing { background: #ede9fe; color: #5b21b6; }
        /* Leave type colors */
        .leave-off { background: #e5e7eb; color: #374151; }
        .leave-al { background: #fef3c7; color: #92400e; }
        .leave-rph { background: #fce7f3; color: #9d174d; }
        .leave-mc { background: #fee2e2; color: #991b1b; }
        .leave-rdo { background: #ffedd5; color: #9a3412; }
        .leave-ch { background: #cffafe; color: #0e7490; }
    </style>

    {{-- Day Remarks Row --}}
    @php
        $hasRemarks = false;
        foreach ($weekDays as $day) {
            if (isset($dayRemarks[$day['date']])) {
                $hasRemarks = true;
                break;
            }
        }
    @endphp

    <table class="roster-table">
        <thead>
            <tr>
                <th class="left" style="width: 20%;">Employee / Designation</th>
                @foreach ($weekDays as $day)
                    <th style="width: 10%;">
                        {{ $day['dayName'] }}<br>{{ $day['dayNum'] }}
                    </th>
                @endforeach
                <th style="width: 6%;">Total</th>
                <th style="width: 6%;">OT</th>
            </tr>
        </thead>
        <tbody>
            {{-- Day Remarks Row --}}
            @if ($hasRemarks)
                <tr class="day-remark-row">
                    <td class="left">Day Remarks</td>
                    @foreach ($weekDays as $day)
                        <td>
                            @if (isset($dayRemarks[$day['date']]))
                                @php $remark = $dayRemarks[$day['date']]; @endphp
                                <div>
                                    @if ($remark->remark_type === 'public_holiday')
                                        PH
                                    @elseif ($remark->remark_type === 'stocktake')
                                        ST
                                    @elseif ($remark->remark_type === 'event')
                                        EV
                                    @else
                                        *
                                    @endif
                                </div>
                                @if ($remark->remark_text)
                                    <div class="remark-text">{{ $remark->remark_text }}</div>
                                @endif
                            @endif
                        </td>
                    @endforeach
                    <td></td>
                    <td></td>
                </tr>
            @endif

            {{-- Employee Rows --}}
            @forelse ($entriesGrouped as $empData)
                <tr>
                    <td class="left">
                        <div class="emp-name">{{ $empData['employee']?->name ?? 'Unknown' }}</div>
                        @if ($empData['employee']?->designation)
                            <div class="emp-designation">{{ $empData['employee']->designation }}</div>
                        @endif
                    </td>
                    @foreach ($weekDays as $day)
                        <td>
                            @if (isset($empData['entries'][$day['date']]))
                                @php
                                    $entry = $empData['entries'][$day['date']];
                                    $shiftClass = '';
                                    if ($entry->is_off_day) {
                                        // Leave type colors
                                        $shiftClass = match($entry->leave_type) {
                                            'off' => 'shift leave-off',
                                            'al' => 'shift leave-al',
                                            'rph' => 'shift leave-rph',
                                            'mc' => 'shift leave-mc',
                                            'rdo' => 'shift leave-rdo',
                                            'ch' => 'shift leave-ch',
                                            default => 'shift leave-off',
                                        };
                                    } elseif ($entry->shift_start) {
                                        // Shift time colors
                                        $hour = (int) \Carbon\Carbon::parse($entry->shift_start)->format('G');
                                        if ($hour < 10) {
                                            $shiftClass = 'shift shift-opening';
                                        } elseif ($hour < 14) {
                                            $shiftClass = 'shift shift-middle';
                                        } else {
                                            $shiftClass = 'shift shift-closing';
                                        }
                                    }
                                @endphp
                                @if ($entry->is_off_day)
                                    <span class="{{ $shiftClass }}">{{ $entry->shift_short }}</span>
                                @elseif ($entry->shift_start && $entry->shift_end)
                                    <span class="{{ $shiftClass }}">{{ $entry->shift_short }}</span>
                                    @if ($entry->station)
                                        <div class="station-name">{{ $entry->station->name }}</div>
                                    @endif
                                @else
                                    -
                                @endif
                            @else
                                -
                            @endif
                        </td>
                    @endforeach
                    <td class="total">{{ number_format($empData['total_hours'], 1) }}h</td>
                    <td class="total">{{ number_format($empData['total_ot'], 1) }}h</td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ count($weekDays) + 3 }}" style="text-align: center; padding: 20px; color: #666;">
                        No entries in this roster.
                    </td>
                </tr>
            @endforelse

            {{-- Daily Summary Row --}}
            @if (count($entriesGrouped) > 0)
                @php
                    $dailyStats = [];
                    foreach ($weekDays as $day) {
                        $dailyStats[$day['date']] = ['ot' => 0, 'opening' => 0, 'middle' => 0, 'closing' => 0, 'off' => 0, 'leave' => 0];
                    }
                    foreach ($entriesGrouped as $empData) {
                        foreach ($weekDays as $day) {
                            if (isset($empData['entries'][$day['date']])) {
                                $entry = $empData['entries'][$day['date']];
                                $dailyStats[$day['date']]['ot'] += (float) $entry->planned_ot;
                                if ($entry->is_off_day) {
                                    if ($entry->leave_type === 'off') {
                                        $dailyStats[$day['date']]['off']++;
                                    } else {
                                        $dailyStats[$day['date']]['leave']++;
                                    }
                                } elseif ($entry->shift_start) {
                                    $hour = (int) \Carbon\Carbon::parse($entry->shift_start)->format('G');
                                    if ($hour < 10) {
                                        $dailyStats[$day['date']]['opening']++;
                                    } elseif ($hour < 14) {
                                        $dailyStats[$day['date']]['middle']++;
                                    } else {
                                        $dailyStats[$day['date']]['closing']++;
                                    }
                                }
                            }
                        }
                    }
                @endphp
                <tr style="background: #e0e7ff; border-top: 2px solid #6366f1;">
                    <td class="left" style="font-weight: bold; font-size: 7px; color: #4338ca;">Daily Summary</td>
                    @foreach ($weekDays as $day)
                        <td style="font-size: 6px; line-height: 1.3; vertical-align: top; padding: 3px 2px;">
                            @if ($dailyStats[$day['date']]['ot'] > 0)
                                <div style="color: #ea580c;">OT: {{ number_format($dailyStats[$day['date']]['ot'], 1) }}h</div>
                            @endif
                            @if ($dailyStats[$day['date']]['opening'] > 0)
                                <div style="color: #059669;">Open: {{ $dailyStats[$day['date']]['opening'] }}</div>
                            @endif
                            @if ($dailyStats[$day['date']]['middle'] > 0)
                                <div style="color: #0284c7;">Mid: {{ $dailyStats[$day['date']]['middle'] }}</div>
                            @endif
                            @if ($dailyStats[$day['date']]['closing'] > 0)
                                <div style="color: #7c3aed;">Close: {{ $dailyStats[$day['date']]['closing'] }}</div>
                            @endif
                            @if ($dailyStats[$day['date']]['off'] > 0)
                                <div style="color: #6b7280;">Off: {{ $dailyStats[$day['date']]['off'] }}</div>
                            @endif
                            @if ($dailyStats[$day['date']]['leave'] > 0)
                                <div style="color: #d97706;">Leave: {{ $dailyStats[$day['date']]['leave'] }}</div>
                            @endif
                        </td>
                    @endforeach
                    <td></td>
                    <td></td>
                </tr>
            @endif
        </tbody>
    </table>

    {{-- Summary --}}
    <div class="summary-box">
        <table style="width: 100%; font-size: 9px;">
            <tr>
                <td style="width: 25%;">
                    <span style="color: #666;">Total Employees:</span>
                    <strong>{{ count($entriesGrouped) }}</strong>
                </td>
                <td style="width: 25%;">
                    <span style="color: #666;">Total Hours:</span>
                    <strong>{{ number_format(collect($entriesGrouped)->sum('total_hours'), 1) }}h</strong>
                </td>
                <td style="width: 25%;">
                    <span style="color: #666;">Total OT:</span>
                    <strong>{{ number_format(collect($entriesGrouped)->sum('total_ot'), 1) }}h</strong>
                </td>
                <td style="width: 25%;">
                    <span style="color: #666;">Status:</span>
                    <strong style="text-transform: uppercase;">{{ $roster->status }}</strong>
                </td>
            </tr>
        </table>
    </div>

    {{-- Legend --}}
    <div style="margin-top: 12px; font-size: 7px; color: #666;">
        <strong>Remarks:</strong>
        PH = Public Holiday &nbsp;|&nbsp;
        ST = Stocktake &nbsp;|&nbsp;
        EV = Event &nbsp;|&nbsp;
        * = Custom
    </div>
    <div style="margin-top: 6px; font-size: 7px;">
        <strong>Shifts:</strong>
        <span style="background: #d1fae5; color: #065f46; padding: 1px 4px; border-radius: 2px;">Opening (&lt;10AM)</span> &nbsp;
        <span style="background: #e0f2fe; color: #0369a1; padding: 1px 4px; border-radius: 2px;">Middle (10AM-2PM)</span> &nbsp;
        <span style="background: #ede9fe; color: #5b21b6; padding: 1px 4px; border-radius: 2px;">Closing (2PM+)</span>
        &nbsp;&nbsp;|&nbsp;&nbsp;
        <strong>Leave:</strong>
        <span style="background: #e5e7eb; color: #374151; padding: 1px 4px; border-radius: 2px;">OFF</span> &nbsp;
        <span style="background: #fef3c7; color: #92400e; padding: 1px 4px; border-radius: 2px;">AL</span> &nbsp;
        <span style="background: #fce7f3; color: #9d174d; padding: 1px 4px; border-radius: 2px;">RPH</span> &nbsp;
        <span style="background: #fee2e2; color: #991b1b; padding: 1px 4px; border-radius: 2px;">MC</span> &nbsp;
        <span style="background: #ffedd5; color: #9a3412; padding: 1px 4px; border-radius: 2px;">RDO</span> &nbsp;
        <span style="background: #cffafe; color: #0e7490; padding: 1px 4px; border-radius: 2px;">CH</span>
    </div>

    @if ($roster->notes)
        <div style="margin-top: 12px; padding: 8px; background: #f9fafb; border: 1px solid #e5e7eb; font-size: 8px;">
            <strong>Notes:</strong> {{ $roster->notes }}
        </div>
    @endif

    <div style="margin-top: 20px; font-size: 7px; color: #999; text-align: center;">
        Generated by Servora on {{ now()->format('M d, Y H:i') }}
    </div>
@endsection
