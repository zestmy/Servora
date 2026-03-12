@extends('pdf.layout')

@section('title', 'Sales Report — ' . $periodLabel)

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
            <div class="doc-title">Sales Report</div>
            <div class="doc-number">{{ $periodLabel }}</div>
        </div>
    </div>

    {{-- Summary --}}
    <table class="items" style="margin-bottom: 12px;">
        <thead>
            <tr>
                <th>Total Revenue (RM)</th>
                <th class="right">Total Pax</th>
                <th class="right">Avg Check (RM)</th>
                <th class="right">Records</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>{{ number_format($totalRevenue, 2) }}</td>
                <td class="right">{{ number_format($totalPax) }}</td>
                <td class="right">{{ number_format($avgCheck, 2) }}</td>
                <td class="right">{{ $records->count() }}</td>
            </tr>
        </tbody>
    </table>

    {{-- Revenue by Category --}}
    @if (!empty($categoryRevenues))
        <h3 style="font-size: 12px; font-weight: bold; margin: 18px 0 8px 0;">Revenue by Sales Category</h3>
        <table class="items">
            <thead>
                <tr>
                    <th>Category</th>
                    <th class="right">Revenue (RM)</th>
                    <th class="right">% of Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($categoryRevenues as $catRev)
                    <tr>
                        <td>{{ $catRev['name'] }}</td>
                        <td class="right">{{ number_format($catRev['revenue'], 2) }}</td>
                        <td class="right">{{ $totalRevenue > 0 ? number_format($catRev['revenue'] / $totalRevenue * 100, 1) : 0 }}%</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td class="right">Total</td>
                    <td class="right">{{ number_format($totalRevenue, 2) }}</td>
                    <td class="right">100%</td>
                </tr>
            </tfoot>
        </table>
    @endif

    {{-- Calendar Events --}}
    @if (!empty($events))
        <h3 style="font-size: 12px; font-weight: bold; margin: 18px 0 8px 0;">Events in Period</h3>
        <table class="items">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Event</th>
                    <th>Category</th>
                    <th class="center">Impact</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($events as $event)
                    <tr>
                        <td>{{ $event['date'] }}{{ $event['end_date'] ? ' — ' . $event['end_date'] : '' }}</td>
                        <td>{{ $event['title'] }}</td>
                        <td>{{ $event['category'] }}</td>
                        <td class="center" style="color: {{ $event['impact'] === 'positive' ? '#16a34a' : ($event['impact'] === 'negative' ? '#dc2626' : '#666') }};">
                            {{ ucfirst($event['impact']) }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    {{-- Missing Dates --}}
    @if (!empty($missingDatesData))
        <h3 style="font-size: 12px; font-weight: bold; margin: 18px 0 8px 0; color: #dc2626;">Missing Sales Dates ({{ count($missingDatesData) }})</h3>
        <div style="font-size: 9px; color: #666; margin-bottom: 6px;">No sales data recorded for these dates:</div>
        <table class="items">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Date</th>
                    <th>Reason</th>
                    <th>Notes</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($missingDatesData as $i => $md)
                    <tr>
                        <td>{{ $i + 1 }}</td>
                        <td>{{ $md['label'] }}</td>
                        <td style="{{ $md['reason'] ? 'color: #2563eb; font-weight: 500;' : 'color: #999;' }}">{{ $md['reason'] ?? 'Not tagged' }}</td>
                        <td style="color: #666;">{{ $md['notes'] ?? '' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    {{-- Daily Sales Breakdown --}}
    @if (!empty($dailySales))
        <h3 style="font-size: 12px; font-weight: bold; margin: 18px 0 8px 0;">Daily Sales Breakdown</h3>
        <table class="items">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Day</th>
                    <th class="right">Revenue (RM)</th>
                    <th class="right">Pax</th>
                    <th class="right">Avg Check (RM)</th>
                    <th class="right">Entries</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($dailySales as $day)
                    <tr>
                        <td>{{ $day['date'] }}</td>
                        <td>{{ $day['day'] }}</td>
                        <td class="right">{{ number_format($day['revenue'], 2) }}</td>
                        <td class="right">{{ number_format($day['pax']) }}</td>
                        <td class="right">{{ number_format($day['avg'], 2) }}</td>
                        <td class="right">{{ $day['count'] }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="2" class="right">Totals</td>
                    <td class="right">{{ number_format($totalRevenue, 2) }}</td>
                    <td class="right">{{ number_format($totalPax) }}</td>
                    <td class="right">{{ number_format($avgCheck, 2) }}</td>
                    <td class="right">{{ $records->count() }}</td>
                </tr>
            </tfoot>
        </table>
    @endif
@endsection
