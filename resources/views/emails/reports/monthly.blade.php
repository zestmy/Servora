@extends('emails.reports.layout')

@section('content')
<div class="card">
    <div class="card-header" style="background: linear-gradient(135deg, #8b5cf6 0%, #6d28d9 100%);">
        <h1>Monthly Summary Report</h1>
        <p>{{ $outletName }} &bull; {{ $periodLabel }}</p>
    </div>

    <div class="card-body">
        @php
            $thisMonth = $reportData['this_month'] ?? [];
            $comparisons = $reportData['comparisons'] ?? [];
        @endphp

        <!-- KPI Cards -->
        <table class="kpi-grid" cellpadding="0" cellspacing="12">
            <tr class="kpi-row">
                <td class="kpi-card">
                    <div class="kpi-value">RM {{ number_format($thisMonth['revenue'] ?? 0, 2) }}</div>
                    <div class="kpi-label">Total Revenue</div>
                    @if(!empty($comparisons['last_month']))
                        <div class="kpi-trend {{ $comparisons['last_month']['trend'] === 'up' ? 'trend-up' : ($comparisons['last_month']['trend'] === 'down' ? 'trend-down' : 'trend-flat') }}">
                            {{ $comparisons['last_month']['trend'] === 'up' ? '↑' : ($comparisons['last_month']['trend'] === 'down' ? '↓' : '→') }}
                            {{ $comparisons['last_month']['change_percent'] > 0 ? '+' : '' }}{{ $comparisons['last_month']['change_percent'] }}% vs last month
                        </div>
                    @endif
                </td>
                <td class="kpi-card">
                    <div class="kpi-value">{{ number_format($thisMonth['pax'] ?? 0) }}</div>
                    <div class="kpi-label">Total Pax</div>
                </td>
                <td class="kpi-card">
                    <div class="kpi-value">{{ number_format($thisMonth['transactions'] ?? 0) }}</div>
                    <div class="kpi-label">Transactions</div>
                </td>
            </tr>
            <tr class="kpi-row">
                <td class="kpi-card">
                    <div class="kpi-value">RM {{ number_format($thisMonth['avg_daily_revenue'] ?? 0, 2) }}</div>
                    <div class="kpi-label">Avg Daily Revenue</div>
                </td>
                <td class="kpi-card">
                    <div class="kpi-value">RM {{ number_format($thisMonth['avg_per_pax'] ?? 0, 2) }}</div>
                    <div class="kpi-label">Avg per Pax</div>
                </td>
                <td class="kpi-card">
                    <div class="kpi-value">{{ $thisMonth['days_with_sales'] ?? 0 }}</div>
                    <div class="kpi-label">Trading Days</div>
                </td>
            </tr>
            <tr class="kpi-row">
                <td class="kpi-card" colspan="3">
                    <div class="kpi-value">RM {{ number_format($thisMonth['discounts'] ?? 0, 2) }}</div>
                    <div class="kpi-label">Total Discounts Given</div>
                </td>
            </tr>
        </table>

        <!-- AI Insights -->
        @if(!empty($insights))
        <div class="insights-card">
            <div class="insights-header">
                <span class="insights-title">AI Monthly Analysis</span>
            </div>
            @if(!empty($insights['headline']))
                <div class="insights-headline">{{ $insights['headline'] }}</div>
            @endif
            @if(!empty($insights['comparison_summary']))
                <p style="font-size: 14px; color: #78350f; margin-bottom: 12px;">{{ $insights['comparison_summary'] }}</p>
            @endif
            @if(!empty($insights['key_metrics']))
                <div style="margin-bottom: 12px;">
                    <strong style="color: #374151; font-size: 12px;">KEY METRICS</strong>
                    <ul class="insights-list">
                        @foreach($insights['key_metrics'] as $metric)
                            <li>{{ $metric }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
            @if(!empty($insights['highlights']))
                <div style="margin-bottom: 12px;">
                    <strong style="color: #047857; font-size: 12px;">HIGHLIGHTS</strong>
                    <ul class="insights-list">
                        @foreach($insights['highlights'] as $highlight)
                            <li>{{ $highlight }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
            @if(!empty($insights['concerns']))
                <div style="margin-bottom: 12px;">
                    <strong style="color: #dc2626; font-size: 12px;">AREAS OF ATTENTION</strong>
                    <ul class="insights-list">
                        @foreach($insights['concerns'] as $concern)
                            <li>{{ $concern }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
            @if(!empty($insights['recommendations']))
                <div>
                    <strong style="color: #1d4ed8; font-size: 12px;">RECOMMENDATIONS</strong>
                    <ul class="insights-list">
                        @foreach($insights['recommendations'] as $rec)
                            <li>{{ $rec }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
        @endif

        <!-- Comparisons -->
        <div class="section">
            <div class="section-title">Period Comparisons</div>
            <table style="width: 100%; border-collapse: separate; border-spacing: 12px;">
                <tr>
                    @if(!empty($comparisons['last_month']))
                    <td style="background: {{ $comparisons['last_month']['trend'] === 'up' ? '#ecfdf5' : ($comparisons['last_month']['trend'] === 'down' ? '#fef2f2' : '#f9fafb') }}; border-radius: 8px; padding: 16px; text-align: center;">
                        <div style="font-size: 12px; color: #6b7280; text-transform: uppercase; margin-bottom: 4px;">vs Last Month</div>
                        <div style="font-size: 24px; font-weight: 700; color: {{ $comparisons['last_month']['trend'] === 'up' ? '#047857' : ($comparisons['last_month']['trend'] === 'down' ? '#dc2626' : '#374151') }};">
                            {{ $comparisons['last_month']['trend'] === 'up' ? '↑' : ($comparisons['last_month']['trend'] === 'down' ? '↓' : '→') }}
                            {{ $comparisons['last_month']['change_percent'] > 0 ? '+' : '' }}{{ $comparisons['last_month']['change_percent'] }}%
                        </div>
                        <div style="font-size: 12px; color: #6b7280;">{{ $comparisons['last_month']['change_amount'] >= 0 ? '+' : '' }}RM {{ number_format($comparisons['last_month']['change_amount'], 2) }}</div>
                    </td>
                    @endif
                    @if(!empty($comparisons['last_year']))
                    <td style="background: {{ $comparisons['last_year']['trend'] === 'up' ? '#ecfdf5' : ($comparisons['last_year']['trend'] === 'down' ? '#fef2f2' : '#f9fafb') }}; border-radius: 8px; padding: 16px; text-align: center;">
                        <div style="font-size: 12px; color: #6b7280; text-transform: uppercase; margin-bottom: 4px;">vs Same Month Last Year</div>
                        <div style="font-size: 24px; font-weight: 700; color: {{ $comparisons['last_year']['trend'] === 'up' ? '#047857' : ($comparisons['last_year']['trend'] === 'down' ? '#dc2626' : '#374151') }};">
                            {{ $comparisons['last_year']['trend'] === 'up' ? '↑' : ($comparisons['last_year']['trend'] === 'down' ? '↓' : '→') }}
                            {{ $comparisons['last_year']['change_percent'] > 0 ? '+' : '' }}{{ $comparisons['last_year']['change_percent'] }}%
                        </div>
                        <div style="font-size: 12px; color: #6b7280;">{{ $comparisons['last_year']['change_amount'] >= 0 ? '+' : '' }}RM {{ number_format($comparisons['last_year']['change_amount'], 2) }}</div>
                    </td>
                    @endif
                </tr>
            </table>
        </div>

        <!-- Daily Revenue Chart -->
        @if(!empty($charts['daily_revenue']))
        <div class="section">
            <div class="section-title">Daily Revenue Trend</div>
            <div class="chart-container">
                <img src="{{ $charts['daily_revenue'] }}" alt="Daily Revenue Chart" />
            </div>
        </div>
        @endif

        <!-- Weekly Breakdown -->
        @if(!empty($reportData['weekly_breakdown']))
        <div class="section">
            <div class="section-title">Weekly Breakdown</div>
            @if(!empty($charts['weekly']))
            <div class="chart-container">
                <img src="{{ $charts['weekly'] }}" alt="Weekly Revenue Chart" />
            </div>
            @endif
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Week Starting</th>
                        <th class="text-right">Revenue</th>
                        <th class="text-right">Pax</th>
                        <th class="text-right">Avg/Pax</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($reportData['weekly_breakdown'] as $week)
                    <tr>
                        <td>{{ $week['week_start'] }}</td>
                        <td class="text-right">RM {{ number_format($week['revenue'], 2) }}</td>
                        <td class="text-right">{{ number_format($week['pax']) }}</td>
                        <td class="text-right">RM {{ $week['pax'] > 0 ? number_format($week['revenue'] / $week['pax'], 2) : '0.00' }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif

        <!-- Meal Period Analysis -->
        @if(!empty($reportData['by_meal_period']))
        <div class="section">
            <div class="section-title">Revenue by Meal Period</div>
            @if(!empty($charts['meal_period']))
            <div class="chart-container">
                <img src="{{ $charts['meal_period'] }}" alt="Meal Period Chart" />
            </div>
            @endif
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Meal Period</th>
                        <th class="text-right">Revenue</th>
                        <th class="text-right">Pax</th>
                        <th class="text-right">%</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($reportData['by_meal_period'] as $period)
                    <tr>
                        <td>{{ $period['label'] }}</td>
                        <td class="text-right">RM {{ number_format($period['revenue'], 2) }}</td>
                        <td class="text-right">{{ number_format($period['pax']) }}</td>
                        <td class="text-right">{{ $period['percentage'] }}%</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif

        <!-- Top Items -->
        @if(!empty($reportData['top_items']))
        <div class="section">
            <div class="section-title">Top 15 Items</div>
            @if(!empty($charts['top_items']))
            <div class="chart-container">
                <img src="{{ $charts['top_items'] }}" alt="Top Items Chart" />
            </div>
            @endif
            <table class="data-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Item</th>
                        <th class="text-right">Qty</th>
                        <th class="text-right">Revenue</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach(array_slice($reportData['top_items'], 0, 15) as $index => $item)
                    <tr>
                        <td>{{ $index + 1 }}</td>
                        <td>{{ $item['name'] }}</td>
                        <td class="text-right">{{ number_format($item['quantity']) }}</td>
                        <td class="text-right">RM {{ number_format($item['revenue'], 2) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>
</div>
@endsection
