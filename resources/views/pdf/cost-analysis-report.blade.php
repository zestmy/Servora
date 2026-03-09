@extends('pdf.layout')

@section('title', 'Cost Analysis — ' . $periodLabel)

@section('content')
    <div class="header">
        <div class="header-left">
            <div class="company-name">{{ $company?->name ?? 'Servora' }}</div>
            <div class="company-detail">Outlet: {{ $outlet?->name ?? 'All Outlets' }}</div>
        </div>
        <div class="header-right">
            <div class="doc-title">Cost Analysis</div>
            <div class="doc-number">{{ $periodLabel }}</div>
        </div>
    </div>

    {{-- MoM Comparison Summary --}}
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
                <td class="right" style="color: {{ $diff <= 0 ? '#16a34a' : '#dc2626' }};">{{ $diff >= 0 ? '+' : '' }}{{ $diff }}pp</td>
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
