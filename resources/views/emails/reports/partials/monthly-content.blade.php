{{-- KPI Cards --}}
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
</table>

{{-- AI Insights --}}
@if(!empty($insights))
<div class="insights-card">
    <div class="insights-header">
        <span class="insights-title">AI Analysis</span>
    </div>
    @if(!empty($insights['headline']))
        <div class="insights-headline">{{ $insights['headline'] }}</div>
    @endif
    @if(!empty($insights['comparison_summary']))
        <p style="font-size: 14px; color: #78350f; margin-bottom: 12px;">{{ $insights['comparison_summary'] }}</p>
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

{{-- Comparisons --}}
<div class="section">
    <div class="section-title">Period Comparisons</div>
    <div class="comparison-grid">
        @if(!empty($comparisons['last_month']))
        <div class="comparison-card">
            <div class="comparison-label">vs Last Month</div>
            <div class="comparison-value {{ $comparisons['last_month']['trend'] === 'up' ? 'trend-up' : ($comparisons['last_month']['trend'] === 'down' ? 'trend-down' : '') }}">
                {{ $comparisons['last_month']['trend'] === 'up' ? '↑' : ($comparisons['last_month']['trend'] === 'down' ? '↓' : '→') }}
                {{ $comparisons['last_month']['change_percent'] > 0 ? '+' : '' }}{{ $comparisons['last_month']['change_percent'] }}%
                ({{ $comparisons['last_month']['change_amount'] >= 0 ? '+' : '' }}RM {{ number_format($comparisons['last_month']['change_amount'], 2) }})
            </div>
        </div>
        @endif
        @if(!empty($comparisons['last_year']))
        <div class="comparison-card">
            <div class="comparison-label">vs Same Month Last Year</div>
            <div class="comparison-value {{ $comparisons['last_year']['trend'] === 'up' ? 'trend-up' : ($comparisons['last_year']['trend'] === 'down' ? 'trend-down' : '') }}">
                {{ $comparisons['last_year']['trend'] === 'up' ? '↑' : ($comparisons['last_year']['trend'] === 'down' ? '↓' : '→') }}
                {{ $comparisons['last_year']['change_percent'] > 0 ? '+' : '' }}{{ $comparisons['last_year']['change_percent'] }}%
                ({{ $comparisons['last_year']['change_amount'] >= 0 ? '+' : '' }}RM {{ number_format($comparisons['last_year']['change_amount'], 2) }})
            </div>
        </div>
        @endif
    </div>
</div>

{{-- Weekly Revenue Chart --}}
@if(!empty($charts['weekly_revenue']))
<div class="section">
    <div class="section-title">Weekly Revenue Trend</div>
    <div class="chart-container">
        <img src="{{ $charts['weekly_revenue'] }}" alt="Weekly Revenue Chart" />
    </div>
</div>
@endif

{{-- Weekly Breakdown Table --}}
@if(!empty($reportData['weekly_breakdown']))
<div class="section">
    <div class="section-title">Weekly Breakdown</div>
    <table class="data-table">
        <thead>
            <tr>
                <th>Week</th>
                <th class="text-right">Revenue</th>
                <th class="text-right">Pax</th>
                <th class="text-right">Avg/Pax</th>
                <th class="text-right">Trading Days</th>
            </tr>
        </thead>
        <tbody>
            @foreach($reportData['weekly_breakdown'] as $week)
            <tr>
                <td>{{ $week['week_label'] ?? 'Week ' . $loop->iteration }}</td>
                <td class="text-right">RM {{ number_format($week['revenue'], 2) }}</td>
                <td class="text-right">{{ number_format($week['pax']) }}</td>
                <td class="text-right">RM {{ $week['pax'] > 0 ? number_format($week['revenue'] / $week['pax'], 2) : '0.00' }}</td>
                <td class="text-right">{{ $week['days_with_sales'] ?? '-' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif

{{-- Meal Period Chart --}}
@if(!empty($charts['meal_period']))
<div class="section">
    <div class="section-title">Revenue by Meal Period</div>
    <div class="chart-container">
        <img src="{{ $charts['meal_period'] }}" alt="Meal Period Chart" />
    </div>
</div>
@endif

{{-- Meal Period Table --}}
@if(!empty($reportData['by_meal_period']))
<div class="section">
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

{{-- Sales by Category --}}
@if(!empty($reportData['sales_by_category']))
<div class="section">
    <div class="section-title">Sales by Category</div>
    @if(!empty($charts['sales_by_category']))
    <div class="chart-container">
        <img src="{{ $charts['sales_by_category'] }}" alt="Sales by Category Chart" />
    </div>
    @endif
    <table class="data-table">
        <thead>
            <tr>
                <th>Category</th>
                <th class="text-right">Revenue</th>
                <th class="text-right">%</th>
            </tr>
        </thead>
        <tbody>
            @foreach($reportData['sales_by_category'] as $category)
            <tr>
                <td>
                    <span style="display: inline-block; width: 10px; height: 10px; border-radius: 2px; background: {{ $category['color'] }}; margin-right: 6px;"></span>
                    {{ $category['name'] }}
                </td>
                <td class="text-right">RM {{ number_format($category['revenue'], 2) }}</td>
                <td class="text-right">{{ $category['percentage'] }}%</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif
