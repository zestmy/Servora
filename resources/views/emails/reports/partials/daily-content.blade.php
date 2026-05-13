{{-- KPI Cards --}}
<table class="kpi-grid" cellpadding="0" cellspacing="12">
    <tr class="kpi-row">
        <td class="kpi-card">
            <div class="kpi-value">RM {{ number_format($today['revenue'] ?? 0, 2) }}</div>
            <div class="kpi-label">Total Revenue</div>
            @if(!empty($comparisons['yesterday']))
                <div class="kpi-trend {{ $comparisons['yesterday']['trend'] === 'up' ? 'trend-up' : ($comparisons['yesterday']['trend'] === 'down' ? 'trend-down' : 'trend-flat') }}">
                    {{ $comparisons['yesterday']['trend'] === 'up' ? '↑' : ($comparisons['yesterday']['trend'] === 'down' ? '↓' : '→') }}
                    {{ $comparisons['yesterday']['change_percent'] > 0 ? '+' : '' }}{{ $comparisons['yesterday']['change_percent'] }}% vs yesterday
                </div>
            @endif
        </td>
        <td class="kpi-card">
            <div class="kpi-value">{{ number_format($today['pax'] ?? 0) }}</div>
            <div class="kpi-label">Total Pax</div>
        </td>
        <td class="kpi-card">
            <div class="kpi-value">{{ number_format($today['transactions'] ?? 0) }}</div>
            <div class="kpi-label">Transactions</div>
        </td>
    </tr>
    <tr class="kpi-row">
        <td class="kpi-card">
            <div class="kpi-value">RM {{ number_format($today['avg_per_pax'] ?? 0, 2) }}</div>
            <div class="kpi-label">Avg per Pax</div>
        </td>
        <td class="kpi-card">
            <div class="kpi-value">RM {{ number_format($today['avg_per_transaction'] ?? 0, 2) }}</div>
            <div class="kpi-label">Avg per Transaction</div>
        </td>
        <td class="kpi-card">
            <div class="kpi-value">RM {{ number_format($today['discounts'] ?? 0, 2) }}</div>
            <div class="kpi-label">Discounts</div>
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
        @if(!empty($comparisons['yesterday']))
        <div class="comparison-card">
            <div class="comparison-label">vs Yesterday</div>
            <div class="comparison-value {{ $comparisons['yesterday']['trend'] === 'up' ? 'trend-up' : ($comparisons['yesterday']['trend'] === 'down' ? 'trend-down' : '') }}">
                {{ $comparisons['yesterday']['trend'] === 'up' ? '↑' : ($comparisons['yesterday']['trend'] === 'down' ? '↓' : '→') }}
                {{ $comparisons['yesterday']['change_percent'] > 0 ? '+' : '' }}{{ $comparisons['yesterday']['change_percent'] }}%
                ({{ $comparisons['yesterday']['change_amount'] >= 0 ? '+' : '' }}RM {{ number_format($comparisons['yesterday']['change_amount'], 2) }})
            </div>
        </div>
        @endif
        @if(!empty($comparisons['last_week']))
        <div class="comparison-card">
            <div class="comparison-label">vs Same Day Last Week</div>
            <div class="comparison-value {{ $comparisons['last_week']['trend'] === 'up' ? 'trend-up' : ($comparisons['last_week']['trend'] === 'down' ? 'trend-down' : '') }}">
                {{ $comparisons['last_week']['trend'] === 'up' ? '↑' : ($comparisons['last_week']['trend'] === 'down' ? '↓' : '→') }}
                {{ $comparisons['last_week']['change_percent'] > 0 ? '+' : '' }}{{ $comparisons['last_week']['change_percent'] }}%
                ({{ $comparisons['last_week']['change_amount'] >= 0 ? '+' : '' }}RM {{ number_format($comparisons['last_week']['change_amount'], 2) }})
            </div>
        </div>
        @endif
        @if(!empty($comparisons['last_year']))
        <div class="comparison-card">
            <div class="comparison-label">vs Same Day Last Year</div>
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
