@extends('pdf.layout')

@section('title', ($mode === 'weekly' ? 'Weekly' : 'Monthly') . ' Cost Summary — ' . $periodLabel)

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
            <div class="doc-title">{{ $mode === 'weekly' ? 'Weekly' : 'Monthly' }} Cost Summary</div>
            <div class="doc-number">{{ $periodLabel }}</div>
        </div>
    </div>

    {{-- MTD Comparison --}}
    @if (!empty($compareMode) && !empty($comparisonData['current']))
        @php
            $cur = $comparisonData['current'];
            $prev = $comparisonData['prev_month'];
            $ly = $comparisonData['prev_year'];
            $varPrev = $comparisonData['var_vs_prev'];
            $varLy = $comparisonData['var_vs_ly'];
        @endphp
        <h3 style="font-size: 12px; font-weight: bold; margin: 0 0 8px 0;">MTD Period Comparison</h3>
        <table class="items" style="margin-bottom: 6px;">
            <thead>
                <tr>
                    <th>Period</th>
                    <th class="right">Date Range</th>
                </tr>
            </thead>
            <tbody>
                <tr><td>This Month MTD</td><td class="right">{{ $cur['label'] }}</td></tr>
                <tr><td>Last Month MTD</td><td class="right">{{ $prev['label'] }}</td></tr>
                <tr><td>Last Year MTD</td><td class="right">{{ $ly['label'] }}</td></tr>
            </tbody>
        </table>

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
                    $rows = [
                        ['label' => 'Revenue', 'key' => 'revenue', 'fmt' => 'num', 'good' => 'up'],
                        ['label' => 'Purchases', 'key' => 'purchases', 'fmt' => 'num', 'good' => 'down'],
                        ['label' => 'COGS', 'key' => 'cogs', 'fmt' => 'num', 'good' => 'down'],
                        ['label' => 'Cost %', 'key' => 'cost_pct', 'fmt' => 'pct', 'good' => 'down'],
                        ['label' => 'Wastage', 'key' => 'wastage', 'fmt' => 'num', 'good' => 'down'],
                        ['label' => 'Staff Meals', 'key' => 'staff_meals', 'fmt' => 'num', 'good' => 'down'],
                    ];
                @endphp
                @foreach ($rows as $r)
                    @php
                        $cv = $cur['summary']['totals'][$r['key']];
                        $pv = $prev['summary']['totals'][$r['key']];
                        $lv = $ly['summary']['totals'][$r['key']];
                        if ($r['fmt'] === 'pct') {
                            $dp = round($cv - $pv, 1); $dl = round($cv - $lv, 1);
                            $dpL = ($dp >= 0 ? '+' : '') . $dp . '%'; $dlL = ($dl >= 0 ? '+' : '') . $dl . '%';
                        } else {
                            $dp = $pv > 0 ? round(($cv - $pv) / $pv * 100, 1) : 0;
                            $dl = $lv > 0 ? round(($cv - $lv) / $lv * 100, 1) : 0;
                            $dpL = ($dp >= 0 ? '+' : '') . $dp . '%'; $dlL = ($dl >= 0 ? '+' : '') . $dl . '%';
                        }
                        $pGood = $r['good'] === 'up' ? $dp >= 0 : $dp <= 0;
                        $lGood = $r['good'] === 'up' ? $dl >= 0 : $dl <= 0;
                    @endphp
                    <tr>
                        <td style="font-weight: bold;">{{ $r['label'] }}</td>
                        <td class="right" style="font-weight: bold;">{{ $r['fmt'] === 'pct' ? $cv . '%' : number_format($cv, 2) }}</td>
                        <td class="right">{{ $r['fmt'] === 'pct' ? $pv . '%' : number_format($pv, 2) }}</td>
                        <td class="right" style="color: {{ $pGood ? '#16a34a' : '#dc2626' }};">{{ $dpL }}</td>
                        <td class="right">{{ $r['fmt'] === 'pct' ? $lv . '%' : number_format($lv, 2) }}</td>
                        <td class="right" style="color: {{ $lGood ? '#16a34a' : '#dc2626' }};">{{ $dlL }}</td>
                    </tr>
                @endforeach
                <tr style="border-top: 2px solid #000;">
                    <td style="font-weight: bold;">Pax</td>
                    <td class="right" style="font-weight: bold;">{{ number_format($cur['pax']) }}</td>
                    <td class="right">{{ number_format($prev['pax']) }}</td>
                    <td class="right" style="color: {{ $varPrev['pax'] >= 0 ? '#16a34a' : '#dc2626' }};">{{ $varPrev['pax'] >= 0 ? '+' : '' }}{{ $varPrev['pax'] }}%</td>
                    <td class="right">{{ number_format($ly['pax']) }}</td>
                    <td class="right" style="color: {{ $varLy['pax'] >= 0 ? '#16a34a' : '#dc2626' }};">{{ $varLy['pax'] >= 0 ? '+' : '' }}{{ $varLy['pax'] }}%</td>
                </tr>
                <tr>
                    <td style="font-weight: bold;">Avg Check</td>
                    <td class="right" style="font-weight: bold;">{{ number_format($cur['avg_check'], 2) }}</td>
                    <td class="right">{{ number_format($prev['avg_check'], 2) }}</td>
                    @php
                        $acDp = $prev['avg_check'] > 0 ? round(($cur['avg_check'] - $prev['avg_check']) / $prev['avg_check'] * 100, 1) : 0;
                        $acDl = $ly['avg_check'] > 0 ? round(($cur['avg_check'] - $ly['avg_check']) / $ly['avg_check'] * 100, 1) : 0;
                    @endphp
                    <td class="right" style="color: {{ $acDp >= 0 ? '#16a34a' : '#dc2626' }};">{{ $acDp >= 0 ? '+' : '' }}{{ $acDp }}%</td>
                    <td class="right">{{ number_format($ly['avg_check'], 2) }}</td>
                    <td class="right" style="color: {{ $acDl >= 0 ? '#16a34a' : '#dc2626' }};">{{ $acDl >= 0 ? '+' : '' }}{{ $acDl }}%</td>
                </tr>
            </tbody>
        </table>

        {{-- Revenue by Category comparison --}}
        @if (!empty($cur['summary']['categories']))
            <h3 style="font-size: 12px; font-weight: bold; margin: 0 0 8px 0;">Revenue by Category — MTD Comparison</h3>
            <table class="items" style="margin-bottom: 12px;">
                <thead>
                    <tr>
                        <th>Category</th>
                        <th class="right">{{ $cur['period_label'] }}</th>
                        <th class="right">{{ $prev['period_label'] }}</th>
                        <th class="right">vs LM</th>
                        <th class="right">{{ $ly['period_label'] }}</th>
                        <th class="right">vs LY</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($cur['summary']['categories'] as $idx => $cat)
                        @php
                            $pcr = $prev['summary']['categories'][$idx]['revenue'] ?? 0;
                            $lcr = $ly['summary']['categories'][$idx]['revenue'] ?? 0;
                            $cdp = $pcr > 0 ? round(($cat['revenue'] - $pcr) / $pcr * 100, 1) : 0;
                            $cdl = $lcr > 0 ? round(($cat['revenue'] - $lcr) / $lcr * 100, 1) : 0;
                        @endphp
                        <tr>
                            <td>{{ $cat['name'] }}</td>
                            <td class="right" style="font-weight: bold;">{{ number_format($cat['revenue'], 2) }}</td>
                            <td class="right">{{ number_format($pcr, 2) }}</td>
                            <td class="right" style="color: {{ $cdp >= 0 ? '#16a34a' : '#dc2626' }};">{{ $cdp >= 0 ? '+' : '' }}{{ $cdp }}%</td>
                            <td class="right">{{ number_format($lcr, 2) }}</td>
                            <td class="right" style="color: {{ $cdl >= 0 ? '#16a34a' : '#dc2626' }};">{{ $cdl >= 0 ? '+' : '' }}{{ $cdl }}%</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        <div style="border-bottom: 2px solid #000; margin-bottom: 18px; padding-bottom: 4px;">
            <span style="font-size: 9px; color: #666;">MTD = Month-to-Date (Day 1–{{ $cur['mtd_day'] }}). Stock values excluded in MTD view.</span>
        </div>
    @endif

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
