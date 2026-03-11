@extends('pdf.layout')

@section('title', 'Performance Report — ' . $periodLabel)

@section('content')
    <div class="header">
        <div class="header-left">
            <div class="company-name">{{ $company?->name ?? 'Servora' }}</div>
            <div class="company-detail">Outlet: {{ $outlet?->name ?? 'All Outlets' }}</div>
        </div>
        <div class="header-right">
            <div class="doc-title">Performance Report</div>
            <div class="doc-number">{{ $periodLabel }}</div>
        </div>
    </div>

    {{-- Summary --}}
    <table class="items" style="margin-bottom: 12px;">
        <thead>
            <tr>
                <th>Revenue (RM)</th>
                <th class="right">Pax</th>
                <th class="right">Avg Check (RM)</th>
                <th class="right">Cost %</th>
                <th class="right">Revenue MoM %</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>{{ number_format($data['cost_summary']['totals']['revenue'], 2) }}</td>
                <td class="right">{{ number_format($data['total_pax']) }}</td>
                <td class="right">{{ number_format($data['avg_check'], 2) }}</td>
                <td class="right">{{ $data['cost_summary']['totals']['cost_pct'] }}%</td>
                <td class="right" style="color: {{ ($data['rev_change'] ?? 0) >= 0 ? '#16a34a' : '#dc2626' }};">
                    {{ ($data['rev_change'] ?? 0) >= 0 ? '+' : '' }}{{ $data['rev_change'] ?? 0 }}%
                </td>
            </tr>
        </tbody>
    </table>

    {{-- MTD Comparison --}}
    @if (!empty($compareMode) && !empty($comparisonData['current']))
        @php
            $cur = $comparisonData['current'];
            $prev = $comparisonData['prev_month'];
            $ly = $comparisonData['prev_year'];
            $varPrev = $comparisonData['var_vs_prev'];
            $varLy = $comparisonData['var_vs_ly'];
        @endphp
        <h3 style="font-size: 12px; font-weight: bold; margin: 10px 0 8px 0;">MTD Performance Comparison (Day 1–{{ $cur['mtd_day'] }})</h3>
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
                <tr>
                    <td>Revenue (RM)</td>
                    <td class="right" style="font-weight: bold;">{{ number_format($cur['summary']['totals']['revenue'], 2) }}</td>
                    <td class="right">{{ number_format($prev['summary']['totals']['revenue'], 2) }}</td>
                    <td class="right" style="color: {{ $varPrev['revenue'] >= 0 ? '#16a34a' : '#dc2626' }};">{{ $varPrev['revenue'] >= 0 ? '+' : '' }}{{ $varPrev['revenue'] }}%</td>
                    <td class="right">{{ number_format($ly['summary']['totals']['revenue'], 2) }}</td>
                    <td class="right" style="color: {{ $varLy['revenue'] >= 0 ? '#16a34a' : '#dc2626' }};">{{ $varLy['revenue'] >= 0 ? '+' : '' }}{{ $varLy['revenue'] }}%</td>
                </tr>
                <tr>
                    <td>Pax</td>
                    <td class="right" style="font-weight: bold;">{{ number_format($cur['pax']) }}</td>
                    <td class="right">{{ number_format($prev['pax']) }}</td>
                    <td class="right" style="color: {{ $varPrev['pax'] >= 0 ? '#16a34a' : '#dc2626' }};">{{ $varPrev['pax'] >= 0 ? '+' : '' }}{{ $varPrev['pax'] }}%</td>
                    <td class="right">{{ number_format($ly['pax']) }}</td>
                    <td class="right" style="color: {{ $varLy['pax'] >= 0 ? '#16a34a' : '#dc2626' }};">{{ $varLy['pax'] >= 0 ? '+' : '' }}{{ $varLy['pax'] }}%</td>
                </tr>
                <tr>
                    <td>Avg Check (RM)</td>
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
                <tr>
                    <td>Cost %</td>
                    @php
                        $curCp = $cur['summary']['totals']['cost_pct'];
                        $prevCp = $prev['summary']['totals']['cost_pct'];
                        $lyCp = $ly['summary']['totals']['cost_pct'];
                        $cpDp = round($curCp - $prevCp, 1);
                        $cpDl = round($curCp - $lyCp, 1);
                    @endphp
                    <td class="right" style="font-weight: bold;">{{ $curCp }}%</td>
                    <td class="right">{{ $prevCp }}%</td>
                    <td class="right" style="color: {{ $cpDp <= 0 ? '#16a34a' : '#dc2626' }};">{{ $cpDp >= 0 ? '+' : '' }}{{ $cpDp }}%</td>
                    <td class="right">{{ $lyCp }}%</td>
                    <td class="right" style="color: {{ $cpDl <= 0 ? '#16a34a' : '#dc2626' }};">{{ $cpDl >= 0 ? '+' : '' }}{{ $cpDl }}%</td>
                </tr>
            </tbody>
        </table>
    @endif

    {{-- Daily Sales Breakdown --}}
    @if (!empty($data['daily_sales']))
        <h3 style="font-size: 12px; font-weight: bold; margin: 18px 0 8px 0;">Daily Sales Breakdown</h3>
        <table class="items">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Day</th>
                    <th class="right">Revenue (RM)</th>
                    <th class="right">Pax</th>
                    <th class="right">Avg Check (RM)</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($data['daily_sales'] as $day)
                    <tr>
                        <td>{{ $day['label'] }}</td>
                        <td>{{ $day['day'] }}</td>
                        <td class="right">{{ number_format($day['revenue'], 2) }}</td>
                        <td class="right">{{ number_format($day['pax']) }}</td>
                        <td class="right">{{ number_format($day['avg'], 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="2" class="right">Totals</td>
                    <td class="right">{{ number_format($data['cost_summary']['totals']['revenue'], 2) }}</td>
                    <td class="right">{{ number_format($data['total_pax']) }}</td>
                    <td class="right">{{ number_format($data['avg_check'], 2) }}</td>
                </tr>
            </tfoot>
        </table>
    @endif

    {{-- Day of Week Averages --}}
    @if (!empty($data['day_of_week']))
        <h3 style="font-size: 12px; font-weight: bold; margin: 18px 0 8px 0;">Day-of-Week Averages</h3>
        <table class="items">
            <thead>
                <tr>
                    <th>Day</th>
                    <th class="right">Avg Revenue (RM)</th>
                    <th class="right">Avg Pax</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($data['day_of_week'] as $dow)
                    <tr>
                        <td>{{ $dow['day'] }}</td>
                        <td class="right">{{ number_format($dow['avg_revenue'], 2) }}</td>
                        <td class="right">{{ number_format($dow['avg_pax']) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
@endsection
