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
