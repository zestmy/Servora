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
        .roster-table .emp-station { font-size: 7px; color: #666; }
        .roster-table .shift { font-size: 8px; }
        .roster-table .off { color: #dc2626; font-weight: bold; }
        .roster-table .remark { font-size: 6px; color: #7c3aed; background: #f3e8ff; padding: 1px 3px; border-radius: 2px; display: inline-block; margin-top: 1px; }
        .roster-table .total { font-weight: bold; background: #f0f9ff; }
        .day-remark-row td { background: #fef3c7 !important; font-size: 7px; color: #92400e; padding: 3px 4px !important; }
        .summary-box { margin-top: 15px; padding: 10px; background: #f9fafb; border: 1px solid #e5e7eb; font-size: 9px; }
        .summary-box .row { display: flex; justify-content: space-between; padding: 3px 0; }
        .summary-box .label { color: #666; }
        .summary-box .value { font-weight: bold; }
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
                <th class="left" style="width: 20%;">Employee / Station</th>
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
                    <td class="left" style="font-weight: bold;">Day Remarks</td>
                    @foreach ($weekDays as $day)
                        <td>
                            @if (isset($dayRemarks[$day['date']]))
                                @php $remark = $dayRemarks[$day['date']]; @endphp
                                <span title="{{ $remark->remark_text }}">
                                    @if ($remark->remark_type === 'public_holiday')
                                        PH
                                    @elseif ($remark->remark_type === 'stocktake')
                                        ST
                                    @elseif ($remark->remark_type === 'event')
                                        EV
                                    @else
                                        *
                                    @endif
                                </span>
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
                        @php
                            $stations = collect($empData['entries'])->pluck('station.name')->filter()->unique()->implode(', ');
                        @endphp
                        @if ($stations)
                            <div class="emp-station">{{ $stations }}</div>
                        @endif
                    </td>
                    @foreach ($weekDays as $day)
                        <td>
                            @if (isset($empData['entries'][$day['date']]))
                                @php $entry = $empData['entries'][$day['date']]; @endphp
                                @if ($entry->is_off_day)
                                    <span class="off">{{ $entry->shift_short }}</span>
                                @elseif ($entry->shift_start && $entry->shift_end)
                                    <span class="shift">{{ $entry->shift_short }}</span>
                                    @if ($entry->station)
                                        <div class="emp-station">{{ Str::limit($entry->station->name, 6) }}</div>
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
        <strong>Legend:</strong>
        PH = Public Holiday &nbsp;|&nbsp;
        ST = Stocktake &nbsp;|&nbsp;
        EV = Event &nbsp;|&nbsp;
        * = Custom Remark &nbsp;|&nbsp;
        OFF = Day Off
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
