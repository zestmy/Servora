@extends('pdf.layout')

@section('title', 'Cost Analysis — ' . $periodLabel)

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
            <div class="doc-title">Cost Analysis</div>
            <div class="doc-number">{{ $periodLabel }}</div>
        </div>
    </div>

    {{-- Comparison Summary --}}
    @if (!empty($compareMode) && !empty($comparisonData['current']))
        @php
            $cur = $comparisonData['current'];
            $prev = $comparisonData['prev_month'];
            $ly = $comparisonData['prev_year'];
        @endphp
        <h3 style="font-size: 12px; font-weight: bold; margin: 0 0 8px 0;">MTD Period Comparison (Day 1–{{ $cur['mtd_day'] }})</h3>
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
                    $compRows = [
                        ['label' => 'Revenue (RM)', 'key' => 'revenue', 'fmt' => 'num', 'good' => 'up'],
                        ['label' => 'COGS (RM)', 'key' => 'cogs', 'fmt' => 'num', 'good' => 'down'],
                        ['label' => 'Cost %', 'key' => 'cost_pct', 'fmt' => 'pct', 'good' => 'down'],
                        ['label' => 'Wastage (RM)', 'key' => 'wastage', 'fmt' => 'num', 'good' => 'down'],
                        ['label' => 'Staff Meals (RM)', 'key' => 'staff_meals', 'fmt' => 'num', 'good' => 'down'],
                    ];
                @endphp
                @foreach ($compRows as $r)
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
                        <td>{{ $r['label'] }}</td>
                        <td class="right" style="font-weight: bold;">{{ $r['fmt'] === 'pct' ? $cv . '%' : number_format($cv, 2) }}</td>
                        <td class="right">{{ $r['fmt'] === 'pct' ? $pv . '%' : number_format($pv, 2) }}</td>
                        <td class="right" style="color: {{ $pGood ? '#16a34a' : '#dc2626' }};">{{ $dpL }}</td>
                        <td class="right">{{ $r['fmt'] === 'pct' ? $lv . '%' : number_format($lv, 2) }}</td>
                        <td class="right" style="color: {{ $lGood ? '#16a34a' : '#dc2626' }};">{{ $dlL }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        @php $mom = $data['mom_comparison']; @endphp
        <h3 style="font-size: 12px; font-weight: bold; margin: 0 0 8px 0;">Month-over-Month Comparison</h3>
        <table class="items" style="margin-bottom: 12px;">
            <thead>
                <tr>
                    <th>Metric</th>
                    <th class="right">Current Month</th>
                    <th class="right">Previous Month</th>
                    <th class="right">Change</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Revenue (RM)</td>
                    <td class="right">{{ number_format($mom['current']['revenue'], 2) }}</td>
                    <td class="right">{{ number_format($mom['previous']['revenue'], 2) }}</td>
                    @php $chg = $mom['previous']['revenue'] > 0 ? round(($mom['current']['revenue'] - $mom['previous']['revenue']) / $mom['previous']['revenue'] * 100, 1) : 0; @endphp
                    <td class="right" style="color: {{ $chg >= 0 ? '#16a34a' : '#dc2626' }};">{{ $chg >= 0 ? '+' : '' }}{{ $chg }}%</td>
                </tr>
                <tr>
                    <td>COGS (RM)</td>
                    <td class="right">{{ number_format($mom['current']['cogs'], 2) }}</td>
                    <td class="right">{{ number_format($mom['previous']['cogs'], 2) }}</td>
                    @php $chg = $mom['previous']['cogs'] > 0 ? round(($mom['current']['cogs'] - $mom['previous']['cogs']) / $mom['previous']['cogs'] * 100, 1) : 0; @endphp
                    <td class="right" style="color: {{ $chg <= 0 ? '#16a34a' : '#dc2626' }};">{{ $chg >= 0 ? '+' : '' }}{{ $chg }}%</td>
                </tr>
                <tr>
                    <td>Cost %</td>
                    <td class="right">{{ $mom['current']['cost_pct'] }}%</td>
                    <td class="right">{{ $mom['previous']['cost_pct'] }}%</td>
                    @php $diff = round($mom['current']['cost_pct'] - $mom['previous']['cost_pct'], 1); @endphp
                    <td class="right" style="color: {{ $diff <= 0 ? '#16a34a' : '#dc2626' }};">{{ $diff >= 0 ? '+' : '' }}{{ $diff }}%</td>
                </tr>
                <tr>
                    <td>Wastage (RM)</td>
                    <td class="right">{{ number_format($mom['current']['wastage'], 2) }}</td>
                    <td class="right">{{ number_format($mom['previous']['wastage'], 2) }}</td>
                    @php $chg = $mom['previous']['wastage'] > 0 ? round(($mom['current']['wastage'] - $mom['previous']['wastage']) / $mom['previous']['wastage'] * 100, 1) : 0; @endphp
                    <td class="right" style="color: {{ $chg <= 0 ? '#16a34a' : '#dc2626' }};">{{ $chg >= 0 ? '+' : '' }}{{ $chg }}%</td>
                </tr>
                <tr>
                    <td>Staff Meals (RM)</td>
                    <td class="right">{{ number_format($mom['current']['staff_meals'], 2) }}</td>
                    <td class="right">{{ number_format($mom['previous']['staff_meals'], 2) }}</td>
                    @php $chg = $mom['previous']['staff_meals'] > 0 ? round(($mom['current']['staff_meals'] - $mom['previous']['staff_meals']) / $mom['previous']['staff_meals'] * 100, 1) : 0; @endphp
                    <td class="right" style="color: {{ $chg <= 0 ? '#16a34a' : '#dc2626' }};">{{ $chg >= 0 ? '+' : '' }}{{ $chg }}%</td>
                </tr>
            </tbody>
        </table>
    @endif

    {{-- Category Breakdown --}}
    @if (!empty($summary['categories']))
        <h3 style="font-size: 12px; font-weight: bold; margin: 18px 0 8px 0;">Category Breakdown</h3>
        <table class="items">
            <thead>
                <tr>
                    <th>Category</th>
                    <th class="right">Revenue (RM)</th>
                    <th class="right">COGS (RM)</th>
                    <th class="right">Cost %</th>
                    <th class="right">Purchases (RM)</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($summary['categories'] as $cat)
                    <tr>
                        <td>{{ $cat['name'] }}</td>
                        <td class="right">{{ number_format($cat['revenue'], 2) }}</td>
                        <td class="right">{{ number_format($cat['cogs'], 2) }}</td>
                        <td class="right" style="color: {{ $cat['cost_pct'] > 35 ? '#dc2626' : ($cat['cost_pct'] > 30 ? '#d97706' : '#16a34a') }};">{{ $cat['cost_pct'] }}%</td>
                        <td class="right">{{ number_format($cat['purchases'], 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td>Total</td>
                    <td class="right">{{ number_format($summary['totals']['revenue'], 2) }}</td>
                    <td class="right">{{ number_format($summary['totals']['cogs'], 2) }}</td>
                    <td class="right" style="color: {{ $summary['totals']['cost_pct'] > 35 ? '#dc2626' : ($summary['totals']['cost_pct'] > 30 ? '#d97706' : '#16a34a') }};">{{ $summary['totals']['cost_pct'] }}%</td>
                    <td class="right">{{ number_format($summary['totals']['purchases'], 2) }}</td>
                </tr>
            </tfoot>
        </table>
    @endif
@endsection
