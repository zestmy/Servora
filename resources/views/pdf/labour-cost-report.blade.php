@extends('pdf.layout')

@section('title', 'Labour Cost Report — ' . $periodLabel)

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
            <div class="doc-title">Labour Cost Report</div>
            <div class="doc-number">{{ $periodLabel }}</div>
        </div>
    </div>

    {{-- Overall Summary --}}
    <table class="items" style="margin-bottom: 15px;">
        <thead>
            <tr>
                <th>Metric</th>
                <th class="right">Amount (RM)</th>
                <th class="right">% of Revenue</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Total Revenue</td>
                <td class="right">{{ number_format($labourData['total_revenue'], 2) }}</td>
                <td class="right">—</td>
            </tr>
            <tr>
                <td>FOH Labour Cost</td>
                <td class="right">{{ number_format($labourData['total_foh'], 2) }}</td>
                <td class="right">{{ $labourData['total_revenue'] > 0 ? round($labourData['total_foh'] / $labourData['total_revenue'] * 100, 1) : 0 }}%</td>
            </tr>
            <tr>
                <td>BOH Labour Cost</td>
                <td class="right">{{ number_format($labourData['total_boh'], 2) }}</td>
                <td class="right">{{ $labourData['total_revenue'] > 0 ? round($labourData['total_boh'] / $labourData['total_revenue'] * 100, 1) : 0 }}%</td>
            </tr>
        </tbody>
        <tfoot>
            <tr>
                <td>Total Labour Cost</td>
                <td class="right">{{ number_format($labourData['grand_total'], 2) }}</td>
                <td class="right">{{ $labourData['labour_pct'] }}%</td>
            </tr>
        </tfoot>
    </table>

    {{-- Per-Outlet Breakdown --}}
    @foreach ($labourData['outlets'] as $outletId => $o)
        <h3 style="font-size: 12px; font-weight: bold; margin: 18px 0 6px 0; border-bottom: 1px solid #ccc; padding-bottom: 4px;">
            {{ $o['outlet_name'] }}
            <span style="font-weight: normal; font-size: 10px; color: #666;">
                — Revenue: RM {{ number_format($o['revenue'], 2) }} | Labour Cost %: {{ $o['labour_pct'] }}%
            </span>
        </h3>

        <table class="items" style="margin-bottom: 8px;">
            <thead>
                <tr>
                    <th>Component</th>
                    <th class="right">FOH (RM)</th>
                    <th class="right">BOH (RM)</th>
                    <th class="right">Total (RM)</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $foh = $o['foh'];
                    $boh = $o['boh'];
                @endphp
                <tr>
                    <td>Basic Salary</td>
                    <td class="right">{{ $foh ? number_format($foh['basic_salary'], 2) : '—' }}</td>
                    <td class="right">{{ $boh ? number_format($boh['basic_salary'], 2) : '—' }}</td>
                    <td class="right">{{ number_format(($foh['basic_salary'] ?? 0) + ($boh['basic_salary'] ?? 0), 2) }}</td>
                </tr>
                <tr>
                    <td>Service Point</td>
                    <td class="right">{{ $foh ? number_format($foh['service_point'], 2) : '—' }}</td>
                    <td class="right">{{ $boh ? number_format($boh['service_point'], 2) : '—' }}</td>
                    <td class="right">{{ number_format(($foh['service_point'] ?? 0) + ($boh['service_point'] ?? 0), 2) }}</td>
                </tr>
                <tr>
                    <td>Overtime</td>
                    <td class="right">{{ $foh ? number_format($foh['overtime'] ?? 0, 2) : '—' }}</td>
                    <td class="right">{{ $boh ? number_format($boh['overtime'] ?? 0, 2) : '—' }}</td>
                    <td class="right">{{ number_format(($foh['overtime'] ?? 0) + ($boh['overtime'] ?? 0), 2) }}</td>
                </tr>

                {{-- Allowances --}}
                @php
                    // Merge allowance labels from both FOH and BOH
                    $allLabels = collect();
                    if ($foh) $allLabels = $allLabels->merge(collect($foh['allowances'])->pluck('label'));
                    if ($boh) $allLabels = $allLabels->merge(collect($boh['allowances'])->pluck('label'));
                    $allLabels = $allLabels->unique()->values();

                    $fohAllowanceMap = $foh ? collect($foh['allowances'])->keyBy('label') : collect();
                    $bohAllowanceMap = $boh ? collect($boh['allowances'])->keyBy('label') : collect();
                @endphp
                @foreach ($allLabels as $label)
                    @php
                        $fohAmt = $fohAllowanceMap[$label]['amount'] ?? 0;
                        $bohAmt = $bohAllowanceMap[$label]['amount'] ?? 0;
                    @endphp
                    <tr>
                        <td>{{ $label }}</td>
                        <td class="right">{{ $fohAmt ? number_format($fohAmt, 2) : '—' }}</td>
                        <td class="right">{{ $bohAmt ? number_format($bohAmt, 2) : '—' }}</td>
                        <td class="right">{{ number_format($fohAmt + $bohAmt, 2) }}</td>
                    </tr>
                @endforeach

                <tr>
                    <td>EPF</td>
                    <td class="right">{{ $foh ? number_format($foh['epf'], 2) : '—' }}</td>
                    <td class="right">{{ $boh ? number_format($boh['epf'], 2) : '—' }}</td>
                    <td class="right">{{ number_format(($foh['epf'] ?? 0) + ($boh['epf'] ?? 0), 2) }}</td>
                </tr>
                <tr>
                    <td>EIS</td>
                    <td class="right">{{ $foh ? number_format($foh['eis'], 2) : '—' }}</td>
                    <td class="right">{{ $boh ? number_format($boh['eis'], 2) : '—' }}</td>
                    <td class="right">{{ number_format(($foh['eis'] ?? 0) + ($boh['eis'] ?? 0), 2) }}</td>
                </tr>
                <tr>
                    <td>SOCSO</td>
                    <td class="right">{{ $foh ? number_format($foh['socso'], 2) : '—' }}</td>
                    <td class="right">{{ $boh ? number_format($boh['socso'], 2) : '—' }}</td>
                    <td class="right">{{ number_format(($foh['socso'] ?? 0) + ($boh['socso'] ?? 0), 2) }}</td>
                </tr>
            </tbody>
            <tfoot>
                <tr>
                    <td>Total</td>
                    <td class="right">{{ number_format($o['foh_total'], 2) }}</td>
                    <td class="right">{{ number_format($o['boh_total'], 2) }}</td>
                    <td class="right">{{ number_format($o['total'], 2) }}</td>
                </tr>
            </tfoot>
        </table>
    @endforeach
@endsection
