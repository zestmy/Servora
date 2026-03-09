@extends('pdf.layout')

@section('title', 'Wastage Report — ' . $periodLabel)

@section('content')
    <div class="header">
        <div class="header-left">
            <div class="company-name">{{ $company?->name ?? 'Servora' }}</div>
            @if ($outlet)
                <div class="company-detail">Outlet: {{ $outlet->name }}</div>
            @endif
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
