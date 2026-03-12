@extends('pdf.layout')

@section('title', 'Wastage Report — ' . $periodLabel)

@section('content')
    <div class="header">
        <div class="header-left">
            @if ($company?->logo)
                <img src="{{ public_path('storage/' . $company->logo) }}" class="company-logo" alt="">
            @endif
            <div class="company-name">{{ $company?->name ?? 'Servora' }}</div>
            <div class="company-detail">Outlet: {{ $outlet?->name ?? 'All Outlets' }}</div>
        </div>
        <div class="header-right">
            <div class="doc-title">Wastage Report</div>
            <div class="doc-number">{{ $periodLabel }}</div>
        </div>
    </div>

    {{-- Summary --}}
    @php
        $totalWastage = $data['cost_summary']['totals']['wastage'];
        $totalStaffMeals = $data['cost_summary']['totals']['staff_meals'];
        $totalRevenue = $data['cost_summary']['totals']['revenue'];
        $wastagePct = $totalRevenue > 0 ? round($totalWastage / $totalRevenue * 100, 2) : 0;
        $staffMealsPct = $totalRevenue > 0 ? round($totalStaffMeals / $totalRevenue * 100, 2) : 0;
    @endphp
    <table class="items" style="margin-bottom: 12px;">
        <thead>
            <tr>
                <th>Metric</th>
                <th class="right">Amount (RM)</th>
                <th class="right">% of Revenue</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Total Wastage</td>
                <td class="right">{{ number_format($totalWastage, 2) }}</td>
                <td class="right">{{ $wastagePct }}%</td>
            </tr>
            <tr>
                <td>Total Staff Meals</td>
                <td class="right">{{ number_format($totalStaffMeals, 2) }}</td>
                <td class="right">{{ $staffMealsPct }}%</td>
            </tr>
            <tr style="font-weight: bold;">
                <td>Combined</td>
                <td class="right">{{ number_format($totalWastage + $totalStaffMeals, 2) }}</td>
                <td class="right">{{ $totalRevenue > 0 ? round(($totalWastage + $totalStaffMeals) / $totalRevenue * 100, 2) : 0 }}%</td>
            </tr>
        </tbody>
    </table>

    {{-- MTD Comparison --}}
    @if (!empty($compareMode) && !empty($comparisonData['current']))
        @php
            $cur = $comparisonData['current'];
            $prev = $comparisonData['prev_month'];
            $ly = $comparisonData['prev_year'];
        @endphp
        <h3 style="font-size: 12px; font-weight: bold; margin: 10px 0 8px 0;">Wastage MTD Comparison (Day 1–{{ $cur['mtd_day'] }})</h3>
        <table class="items" style="margin-bottom: 12px;">
            <thead>
                <tr>
                    <th>Metric</th>
                    <th class="right">{{ $cur['period_label'] }}</th>
                    <th class="right">{{ $prev['period_label'] }}</th>
                    <th class="right">vs LM</th>
                    <th class="right">{{ $ly['period_label'] }}</th>
                    <th class="right">vs LY</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $wRows = [
                        ['label' => 'Wastage (RM)', 'key' => 'wastage'],
                        ['label' => 'Staff Meals (RM)', 'key' => 'staff_meals'],
                    ];
                @endphp
                @foreach ($wRows as $r)
                    @php
                        $cv = $cur['summary']['totals'][$r['key']];
                        $pv = $prev['summary']['totals'][$r['key']];
                        $lv = $ly['summary']['totals'][$r['key']];
                        $dp = $pv > 0 ? round(($cv - $pv) / $pv * 100, 1) : 0;
                        $dl = $lv > 0 ? round(($cv - $lv) / $lv * 100, 1) : 0;
                    @endphp
                    <tr>
                        <td>{{ $r['label'] }}</td>
                        <td class="right" style="font-weight: bold;">{{ number_format($cv, 2) }}</td>
                        <td class="right">{{ number_format($pv, 2) }}</td>
                        <td class="right" style="color: {{ $dp <= 0 ? '#16a34a' : '#dc2626' }};">{{ $dp >= 0 ? '+' : '' }}{{ $dp }}%</td>
                        <td class="right">{{ number_format($lv, 2) }}</td>
                        <td class="right" style="color: {{ $dl <= 0 ? '#16a34a' : '#dc2626' }};">{{ $dl >= 0 ? '+' : '' }}{{ $dl }}%</td>
                    </tr>
                @endforeach
                <tr>
                    <td>Revenue (RM)</td>
                    <td class="right" style="font-weight: bold;">{{ number_format($cur['summary']['totals']['revenue'], 2) }}</td>
                    <td class="right">{{ number_format($prev['summary']['totals']['revenue'], 2) }}</td>
                    @php
                        $rv = $prev['summary']['totals']['revenue'] > 0 ? round(($cur['summary']['totals']['revenue'] - $prev['summary']['totals']['revenue']) / $prev['summary']['totals']['revenue'] * 100, 1) : 0;
                        $rl = $ly['summary']['totals']['revenue'] > 0 ? round(($cur['summary']['totals']['revenue'] - $ly['summary']['totals']['revenue']) / $ly['summary']['totals']['revenue'] * 100, 1) : 0;
                    @endphp
                    <td class="right" style="color: {{ $rv >= 0 ? '#16a34a' : '#dc2626' }};">{{ $rv >= 0 ? '+' : '' }}{{ $rv }}%</td>
                    <td class="right">{{ number_format($ly['summary']['totals']['revenue'], 2) }}</td>
                    <td class="right" style="color: {{ $rl >= 0 ? '#16a34a' : '#dc2626' }};">{{ $rl >= 0 ? '+' : '' }}{{ $rl }}%</td>
                </tr>
            </tbody>
        </table>
    @endif

    {{-- Top Wastage Items --}}
    @if (!empty($data['top_wastage']))
        <h3 style="font-size: 12px; font-weight: bold; margin: 18px 0 8px 0;">Top Wastage Items</h3>
        <table class="items">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Item</th>
                    <th>Category</th>
                    <th>Type</th>
                    <th class="right">Quantity</th>
                    <th>UOM</th>
                    <th class="right">Cost (RM)</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($data['top_wastage'] as $i => $item)
                    <tr>
                        <td>{{ $i + 1 }}</td>
                        <td>{{ $item['name'] }}</td>
                        <td>{{ $item['category'] }}</td>
                        <td>{{ ucfirst($item['type']) }}</td>
                        <td class="right">{{ number_format($item['quantity'], 2) }}</td>
                        <td>{{ $item['uom'] }}</td>
                        <td class="right">{{ number_format($item['total_cost'], 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    {{-- Wastage Breakdown --}}
    @if (!empty($summary['wastage_detail']['groups']))
        <h3 style="font-size: 12px; font-weight: bold; margin: 18px 0 8px 0;">Wastage Breakdown — RM {{ number_format($summary['wastage_detail']['total'], 2) }}</h3>
        <table class="items">
            <thead>
                <tr>
                    <th>Item</th>
                    <th>Type</th>
                    <th class="right">Quantity</th>
                    <th>UOM</th>
                    <th class="right">Total Cost (RM)</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($summary['wastage_detail']['groups'] as $catName => $group)
                    <tr style="background: #e5e5e5;">
                        <td colspan="4" style="font-weight: bold; font-size: 9px; text-transform: uppercase;">{{ $catName }}</td>
                        <td class="right" style="font-weight: bold;">{{ number_format($group['total'], 2) }}</td>
                    </tr>
                    @foreach ($group['items'] as $item)
                        <tr>
                            <td style="padding-left: 16px;">{{ $item['name'] }}{{ $item['is_prep'] ? ' [PREP]' : '' }}</td>
                            <td>{{ ucfirst($item['type']) }}</td>
                            <td class="right">{{ number_format($item['quantity'], 2) }}</td>
                            <td>{{ $item['uom'] }}</td>
                            <td class="right">{{ number_format($item['total_cost'], 2) }}</td>
                        </tr>
                    @endforeach
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="4" class="right">Total Wastage</td>
                    <td class="right">{{ number_format($summary['wastage_detail']['total'], 2) }}</td>
                </tr>
            </tfoot>
        </table>
    @endif

    {{-- Staff Meals Breakdown --}}
    @if (!empty($summary['staff_meals_detail']['groups']))
        <h3 style="font-size: 12px; font-weight: bold; margin: 18px 0 8px 0;">Staff Meals Breakdown — RM {{ number_format($summary['staff_meals_detail']['total'], 2) }}</h3>
        <table class="items">
            <thead>
                <tr>
                    <th>Item</th>
                    <th>Type</th>
                    <th class="right">Quantity</th>
                    <th>UOM</th>
                    <th class="right">Total Cost (RM)</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($summary['staff_meals_detail']['groups'] as $catName => $group)
                    <tr style="background: #e5e5e5;">
                        <td colspan="4" style="font-weight: bold; font-size: 9px; text-transform: uppercase;">{{ $catName }}</td>
                        <td class="right" style="font-weight: bold;">{{ number_format($group['total'], 2) }}</td>
                    </tr>
                    @foreach ($group['items'] as $item)
                        <tr>
                            <td style="padding-left: 16px;">{{ $item['name'] }}{{ $item['is_prep'] ? ' [PREP]' : '' }}</td>
                            <td>{{ ucfirst($item['type']) }}</td>
                            <td class="right">{{ number_format($item['quantity'], 2) }}</td>
                            <td>{{ $item['uom'] }}</td>
                            <td class="right">{{ number_format($item['total_cost'], 2) }}</td>
                        </tr>
                    @endforeach
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="4" class="right">Total Staff Meals</td>
                    <td class="right">{{ number_format($summary['staff_meals_detail']['total'], 2) }}</td>
                </tr>
            </tfoot>
        </table>
    @endif
@endsection
