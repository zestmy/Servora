{{-- KPI Cards --}}
<table class="kpi-grid" cellpadding="0" cellspacing="12">
    <tr class="kpi-row">
        <td class="kpi-card">
            <div class="kpi-value">RM {{ number_format($thisWeek['revenue'] ?? 0, 2) }}</div>
            <div class="kpi-label">Total Revenue</div>
            @if(!empty($comparisons['last_week']))
                <div class="kpi-trend {{ $comparisons['last_week']['trend'] === 'up' ? 'trend-up' : ($comparisons['last_week']['trend'] === 'down' ? 'trend-down' : 'trend-flat') }}">
                    {{ $comparisons['last_week']['trend'] === 'up' ? '↑' : ($comparisons['last_week']['trend'] === 'down' ? '↓' : '→') }}
                    {{ $comparisons['last_week']['change_percent'] > 0 ? '+' : '' }}{{ $comparisons['last_week']['change_percent'] }}% vs last week
                </div>
            @endif
        </td>
        <td class="kpi-card">
            <div class="kpi-value">{{ number_format($thisWeek['pax'] ?? 0) }}</div>
            <div class="kpi-label">Total Pax</div>
        </td>
        <td class="kpi-card">
            <div class="kpi-value">{{ number_format($thisWeek['transactions'] ?? 0) }}</div>
            <div class="kpi-label">Transactions</div>
        </td>
    </tr>
    <tr class="kpi-row">
        <td class="kpi-card">
            <div class="kpi-value">RM {{ number_format($thisWeek['avg_daily_revenue'] ?? 0, 2) }}</div>
            <div class="kpi-label">Avg Daily Revenue</div>
        </td>
        <td class="kpi-card">
            <div class="kpi-value">RM {{ number_format($thisWeek['avg_per_pax'] ?? 0, 2) }}</div>
            <div class="kpi-label">Avg per Pax</div>
        </td>
        <td class="kpi-card">
            <div class="kpi-value">{{ $thisWeek['days_with_sales'] ?? 0 }}</div>
            <div class="kpi-label">Trading Days</div>
        </td>
    </tr>
</table>

{{-- Best & Worst Days --}}
@if($bestDay || $worstDay)
<div class="section">
    <table style="width: 100%; border-collapse: separate; border-spacing: 12px;">
        <tr>
            @if($bestDay)
            <td style="background: #ecfdf5; border-radius: 8px; padding: 16px; text-align: center;">
                <div style="font-size: 12px; color: #047857; text-transform: uppercase; margin-bottom: 4px;">Best Day</div>
                <div style="font-size: 18px; font-weight: 700; color: #065f46;">{{ $bestDay['day_name'] }}</div>
                <div style="font-size: 14px; color: #047857;">RM {{ number_format($bestDay['revenue'], 2) }}</div>
            </td>
            @endif
            @if($worstDay)
            <td style="background: #fef2f2; border-radius: 8px; padding: 16px; text-align: center;">
                <div style="font-size: 12px; color: #dc2626; text-transform: uppercase; margin-bottom: 4px;">Lowest Day</div>
                <div style="font-size: 18px; font-weight: 700; color: #991b1b;">{{ $worstDay['day_name'] }}</div>
                <div style="font-size: 14px; color: #dc2626;">RM {{ number_format($worstDay['revenue'], 2) }}</div>
            </td>
            @endif
        </tr>
    </table>
</div>
@endif

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

{{-- Daily Revenue Chart --}}
@if(!empty($charts['daily_revenue']))
<div class="section">
    <div class="section-title">Daily Revenue Trend</div>
    <div class="chart-container">
        <img src="{{ $charts['daily_revenue'] }}" alt="Daily Revenue Chart" />
    </div>
</div>
@endif

{{-- Daily Breakdown Table --}}
@if(!empty($reportData['daily_breakdown']))
<div class="section">
    <div class="section-title">Daily Breakdown</div>
    <table class="data-table">
        <thead>
            <tr>
                <th>Day</th>
                <th>Date</th>
                <th class="text-right">Revenue</th>
                <th class="text-right">Pax</th>
                <th class="text-right">Avg/Pax</th>
            </tr>
        </thead>
        <tbody>
            @foreach($reportData['daily_breakdown'] as $day)
            <tr>
                <td>{{ $day['day_name'] }}</td>
                <td>{{ $day['date'] }}</td>
                <td class="text-right">RM {{ number_format($day['revenue'], 2) }}</td>
                <td class="text-right">{{ number_format($day['pax']) }}</td>
                <td class="text-right">RM {{ $day['pax'] > 0 ? number_format($day['revenue'] / $day['pax'], 2) : '0.00' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif

{{-- Comparisons --}}
<div class="section">
    <div class="section-title">Period Comparisons</div>
    <div class="comparison-grid">
        @if(!empty($comparisons['last_week']))
        <div class="comparison-card">
            <div class="comparison-label">vs Last Week</div>
            <div class="comparison-value {{ $comparisons['last_week']['trend'] === 'up' ? 'trend-up' : ($comparisons['last_week']['trend'] === 'down' ? 'trend-down' : '') }}">
                {{ $comparisons['last_week']['trend'] === 'up' ? '↑' : ($comparisons['last_week']['trend'] === 'down' ? '↓' : '→') }}
                {{ $comparisons['last_week']['change_percent'] > 0 ? '+' : '' }}{{ $comparisons['last_week']['change_percent'] }}%
                ({{ $comparisons['last_week']['change_amount'] >= 0 ? '+' : '' }}RM {{ number_format($comparisons['last_week']['change_amount'], 2) }})
            </div>
        </div>
        @endif
        @if(!empty($comparisons['last_year']))
        <div class="comparison-card">
            <div class="comparison-label">vs Same Week Last Year</div>
            <div class="comparison-value {{ $comparisons['last_year']['trend'] === 'up' ? 'trend-up' : ($comparisons['last_year']['trend'] === 'down' ? 'trend-down' : '') }}">
                {{ $comparisons['last_year']['trend'] === 'up' ? '↑' : ($comparisons['last_year']['trend'] === 'down' ? '↓' : '→') }}
                {{ $comparisons['last_year']['change_percent'] > 0 ? '+' : '' }}{{ $comparisons['last_year']['change_percent'] }}%
                ({{ $comparisons['last_year']['change_amount'] >= 0 ? '+' : '' }}RM {{ number_format($comparisons['last_year']['change_amount'], 2) }})
            </div>
        </div>
        @endif
    </div>
</div>

{{-- Meal Period Chart --}}
@if(!empty($charts['meal_period']))
<div class="section">
    <div class="section-title">Revenue by Meal Period</div>
    <div class="chart-container">
        <img src="{{ $charts['meal_period'] }}" alt="Meal Period Chart" />
    </div>
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
                <th class="text-right">Qty</th>
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
                <td class="text-right">{{ number_format($category['quantity']) }}</td>
                <td class="text-right">RM {{ number_format($category['revenue'], 2) }}</td>
                <td class="text-right">{{ $category['percentage'] }}%</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif
