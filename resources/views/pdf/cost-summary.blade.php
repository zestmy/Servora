@extends('pdf.layout')

@section('title', ($mode === 'weekly' ? 'Weekly' : 'Monthly') . ' Cost Summary — ' . $periodLabel)

@section('content')
    <div class="header">
        <div class="header-left">
            <div class="company-name">{{ $company?->name ?? 'Servora' }}</div>
            @if ($outlet)
                <div class="company-detail">Outlet: {{ $outlet->name }}</div>
            @endif
        </div>
        <div class="header-right">
            <div class="doc-title">{{ $mode === 'weekly' ? 'Weekly' : 'Monthly' }} Cost Summary</div>
            <div class="doc-number">{{ $periodLabel }}</div>
        </div>
    </div>

    {{-- Summary row --}}
    <table class="items" style="margin-bottom: 12px;">
        <thead>
            <tr>
                <th>Total Revenue</th>
                <th class="right">Total COGS</th>
                <th class="right">Cost %</th>
                <th class="right">Wastage</th>
                <th class="right">Staff Meals</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>RM {{ number_format($summary['totals']['revenue'], 2) }}</td>
                <td class="right">RM {{ number_format($summary['totals']['cogs'], 2) }}</td>
                <td class="right">{{ $summary['totals']['cost_pct'] }}%</td>
                <td class="right">RM {{ number_format($summary['totals']['wastage'], 2) }}</td>
                <td class="right">RM {{ number_format($summary['totals']['staff_meals'], 2) }}</td>
            </tr>
        </tbody>
    </table>

    {{-- P&L Table --}}
    <table class="items">
        <thead>
            <tr>
                <th>Metric</th>
                @foreach ($summary['categories'] as $cat)
                    <th class="right">{{ $cat['name'] }}</th>
                @endforeach
                <th class="right">Total</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td style="font-weight: bold;">Revenue</td>
                @foreach ($summary['categories'] as $cat)
                    <td class="right">{{ number_format($cat['revenue'], 2) }}</td>
                @endforeach
                <td class="right" style="font-weight: bold;">{{ number_format($summary['totals']['revenue'], 2) }}</td>
            </tr>

            @if ($mode === 'monthly')
                <tr>
                    <td>Opening Stock</td>
                    @foreach ($summary['categories'] as $cat)
                        <td class="right">{{ number_format($cat['opening_stock'], 2) }}</td>
                    @endforeach
                    <td class="right">{{ number_format($summary['totals']['opening_stock'], 2) }}</td>
                </tr>
            @endif

            <tr>
                <td>(+) Purchases</td>
                @foreach ($summary['categories'] as $cat)
                    <td class="right">{{ number_format($cat['purchases'], 2) }}</td>
                @endforeach
                <td class="right">{{ number_format($summary['totals']['purchases'], 2) }}</td>
            </tr>

            @if ($summary['outlet_id'])
                <tr>
                    <td>(+) Transfer In</td>
                    @foreach ($summary['categories'] as $cat)
                        <td class="right">{{ number_format($cat['transfer_in'], 2) }}</td>
                    @endforeach
                    <td class="right">{{ number_format($summary['totals']['transfer_in'], 2) }}</td>
                </tr>
                <tr>
                    <td>(-) Transfer Out</td>
                    @foreach ($summary['categories'] as $cat)
                        <td class="right">{{ number_format($cat['transfer_out'], 2) }}</td>
                    @endforeach
                    <td class="right">{{ number_format($summary['totals']['transfer_out'], 2) }}</td>
                </tr>
            @endif

            @if ($mode === 'monthly')
                <tr>
                    <td>(-) Closing Stock</td>
                    @foreach ($summary['categories'] as $cat)
                        <td class="right">{{ number_format($cat['closing_stock'], 2) }}</td>
                    @endforeach
                    <td class="right">{{ number_format($summary['totals']['closing_stock'], 2) }}</td>
                </tr>
            @endif

            <tr style="border-top: 2px solid #000;">
                <td style="font-weight: bold;">= COGS</td>
                @foreach ($summary['categories'] as $cat)
                    <td class="right" style="font-weight: bold;">{{ number_format($cat['cogs'], 2) }}</td>
                @endforeach
                <td class="right" style="font-weight: bold;">{{ number_format($summary['totals']['cogs'], 2) }}</td>
            </tr>

            <tr>
                <td style="font-weight: bold;">Cost %</td>
                @foreach ($summary['categories'] as $cat)
                    <td class="right" style="font-weight: bold; color: {{ $cat['cost_pct'] > 35 ? '#dc2626' : ($cat['cost_pct'] > 30 ? '#d97706' : '#16a34a') }};">
                        {{ $cat['cost_pct'] }}%
                    </td>
                @endforeach
                <td class="right" style="font-weight: bold; color: {{ $summary['totals']['cost_pct'] > 35 ? '#dc2626' : ($summary['totals']['cost_pct'] > 30 ? '#d97706' : '#16a34a') }};">
                    {{ $summary['totals']['cost_pct'] }}%
                </td>
            </tr>
        </tbody>
    </table>

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
