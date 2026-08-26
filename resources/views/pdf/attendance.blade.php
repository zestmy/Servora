<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Attendance Record</title>
    @include('pdf.partials.attendance-styles')
</head>
<body>

    @php
        // Info columns get the leftover width after the fixed-width day grid.
        $dayW = count($dates) > 28 ? '3.0%' : '3.4%';
    @endphp

    @include('pdf.partials.attendance-header', [
        'docTitle'   => 'Attendance Record',
        'countLabel' => $employees->count() . ' employee(s)',
    ])

    @if ($hourlyEmployees->isNotEmpty())
        <div class="table-title">Salaried staff</div>
    @endif

    <table class="grid">
        <thead>
            <tr>
                <th style="width: 2%;">#</th>
                <th class="info" style="width: 13%;">Name</th>
                <th class="info" style="width: 7.5%;">Position</th>
                <th class="info" style="width: 4.5%;">Emp ID</th>
                <th class="info" style="width: 4.5%;">Section</th>
                <th class="info" style="width: 5%;">Date Join</th>
                @if ($canViewPay)
                    <th style="width: 3.5%;">Svc Pts</th>
                    <th style="width: 6%;">Basic Salary</th>
                @endif
                @foreach ($dates as $d)
                    <th style="width: {{ $dayW }};" class="{{ $d->isSunday() ? 'sun' : ($d->isSaturday() ? 'sat' : '') }}">
                        {{ $d->day }}<span class="dow">{{ substr($d->format('D'), 0, 2) }}</span>
                    </th>
                @endforeach
                <th style="width: 3%;">✓</th>
                <th style="width: 3%;">ABS</th>
            </tr>
        </thead>
        <tbody>
            @php $n = 0; @endphp
            @foreach ($monthlyEmployees->groupBy(fn ($e) => $e->outlet?->name ?? 'No Outlet') as $groupName => $group)
                <tr class="outlet-row">
                    <td colspan="{{ ($canViewPay ? 10 : 8) + count($dates) }}">{{ $groupName }} ({{ $group->count() }})</td>
                </tr>
                @foreach ($group as $emp)
                    @php
                        $n++;
                        $present = 0;
                        $absent  = 0;
                    @endphp
                    <tr>
                        <td class="num">{{ $n }}</td>
                        <td class="info name">{{ $emp->name }}</td>
                        <td class="info">{{ $emp->designation }}</td>
                        <td class="info">{{ $emp->staff_id }}</td>
                        <td class="info">{{ $emp->section?->name }}</td>
                        <td class="info">{{ $emp->join_date?->format('d/m/y') }}</td>
                        @if ($canViewPay)
                            <td class="num">{{ $emp->service_points_entitlement !== null ? number_format((float) $emp->service_points_entitlement, 2) : '' }}</td>
                            <td class="pay">
                                @if ($emp->basic_salary !== null)
                                    {{ number_format((float) $emp->basic_salary, 2) }}<span class="suffix">{{ \App\Models\Employee::PAY_TYPE_SUFFIXES[$emp->pay_type] ?? '' }}</span>
                                @endif
                            </td>
                        @endif
                        @foreach ($dates as $d)
                            @php
                                $codeId = $cellMap[$emp->id . ':' . $d->format('Y-m-d')] ?? null;
                                $code   = $codeId ? ($codesById[$codeId] ?? null) : null;
                                $meta   = $code?->colorMeta();
                                if ($code?->system_key === 'present') $present++;
                                if ($code?->system_key === 'absent')  $absent++;
                            @endphp
                            @if ($code)
                                <td class="day" style="background: {{ $meta['bg'] }}; color: {{ $meta['text'] }};">{{ $code->code }}</td>
                            @else
                                <td class="day {{ $d->isSunday() ? 'sun-empty' : '' }}"></td>
                            @endif
                        @endforeach
                        <td class="total" style="color: #15803d;">{{ $present ?: '' }}</td>
                        <td class="total" style="color: #b91c1c;">{{ $absent ?: '' }}</td>
                    </tr>
                @endforeach
            @endforeach
            @if ($monthlyEmployees->isEmpty())
                <tr><td colspan="{{ ($canViewPay ? 10 : 8) + count($dates) }}" style="text-align: center; color: #94a3b8; padding: 10px;">No employees match the selected filters.</td></tr>
            @endif
        </tbody>
    </table>

    {{-- ── Hourly staff ──────────────────────────────────────────────────
         A second table rather than more rows on the first, because the day
         columns mean something different: a number here is what gets
         multiplied by a rate. The row totals are hours and pay, not ticks. --}}
    @if ($hourlyEmployees->isNotEmpty())
        <div class="table-title">Hourly staff — hours worked</div>

        <table class="grid">
            <thead>
                <tr>
                    <th style="width: 2%;">#</th>
                    <th class="info" style="width: 13%;">Name</th>
                    <th class="info" style="width: 7.5%;">Position</th>
                    <th class="info" style="width: 4.5%;">Emp ID</th>
                    <th class="info" style="width: 4.5%;">Section</th>
                    <th class="info" style="width: 5%;">Date Join</th>
                    @if ($canViewPay)
                        <th style="width: 3.5%;">Svc Pts</th>
                        <th style="width: 6%;">Rate</th>
                    @endif
                    @foreach ($dates as $d)
                        <th style="width: {{ $dayW }};" class="{{ $d->isSunday() ? 'sun' : ($d->isSaturday() ? 'sat' : '') }}">
                            {{ $d->day }}<span class="dow">{{ substr($d->format('D'), 0, 2) }}</span>
                        </th>
                    @endforeach
                    <th style="width: 3%;">Days</th>
                    <th style="width: 3.5%;">Hours</th>
                    @if ($canViewPay)
                        <th style="width: 5%;">Pay</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @php $hn = 0; @endphp
                @foreach ($hourlyEmployees->groupBy(fn ($e) => $e->outlet?->name ?? 'No Outlet') as $groupName => $group)
                    <tr class="outlet-row">
                        <td colspan="{{ ($canViewPay ? 11 : 9) + count($dates) }}">{{ $groupName }} ({{ $group->count() }})</td>
                    </tr>
                    @foreach ($group as $emp)
                        @php
                            $hn++;
                            $total = $hourTotals[$emp->id] ?? 0;
                            $days  = 0;
                            $rate  = $emp->basic_salary !== null ? (float) $emp->basic_salary : null;
                        @endphp
                        <tr>
                            <td class="num">{{ $hn }}</td>
                            <td class="info name">{{ $emp->name }}</td>
                            <td class="info">{{ $emp->designation }}</td>
                            <td class="info">{{ $emp->staff_id }}</td>
                            <td class="info">{{ $emp->section?->name }}</td>
                            <td class="info">{{ $emp->join_date?->format('d/m/y') }}</td>
                            @if ($canViewPay)
                                <td class="num">{{ $emp->service_points_entitlement !== null ? number_format((float) $emp->service_points_entitlement, 2) : '' }}</td>
                                <td class="pay">
                                    @if ($rate !== null)
                                        {{ number_format($rate, 2) }}<span class="suffix">/ hr</span>
                                    @endif
                                </td>
                            @endif
                            @foreach ($dates as $d)
                                @php
                                    $key    = $emp->id . ':' . $d->format('Y-m-d');
                                    $hours  = $hoursMap[$key] ?? null;
                                    $codeId = $cellMap[$key] ?? null;
                                    $code   = $codeId ? ($codesById[$codeId] ?? null) : null;
                                    $meta   = $code?->colorMeta();
                                    if ($hours !== null) $days++;
                                @endphp
                                @if ($hours !== null)
                                    <td class="day">{{ rtrim(rtrim(number_format($hours, 2, '.', ''), '0'), '.') }}</td>
                                @elseif ($code)
                                    <td class="day" style="background: {{ $meta['bg'] }}; color: {{ $meta['text'] }};">{{ $code->code }}</td>
                                @else
                                    <td class="day {{ $d->isSunday() ? 'sun-empty' : '' }}"></td>
                                @endif
                            @endforeach
                            <td class="total">{{ $days ?: '' }}</td>
                            <td class="total" style="color: #0f766e;">{{ $total > 0 ? rtrim(rtrim(number_format($total, 2, '.', ''), '0'), '.') : '' }}</td>
                            @if ($canViewPay)
                                <td class="pay">{{ $rate !== null && $total > 0 ? number_format($total * $rate, 2) : '' }}</td>
                            @endif
                        </tr>
                    @endforeach
                @endforeach
            </tbody>
        </table>
    @endif

    {{-- The legend explains the codes in the grid above it, so it lives with
         the grid. It sat between the two halves while they were one document
         and followed the wrong half out when they were split. --}}
    <div class="legend">
        <div class="legend-title">Legend</div>
        <table class="legend-table">
            @foreach ($legendCodes->chunk(4) as $chunk)
                <tr>
                    @foreach ($chunk as $code)
                        @php $meta = $code->colorMeta(); @endphp
                        <td>
                            <span class="swatch" style="background: {{ $meta['bg'] }}; color: {{ $meta['text'] }};">{{ $code->code }}</span>
                            {{ $code->label }}
                        </td>
                    @endforeach
                    @for ($i = $chunk->count(); $i < 4; $i++)
                        <td></td>
                    @endfor
                </tr>
            @endforeach
        </table>
    </div>

    @include('pdf.partials.attendance-signatures')

</body>
</html>
