@extends('emails.reports.layout')

@section('content')
<div class="card">
    <div class="card-header" style="background: linear-gradient(135deg, #8b5cf6 0%, #6d28d9 100%);">
        <h1>Monthly Summary Report</h1>
        <p>{{ $outletName }} &bull; {{ $periodLabel }}</p>
    </div>

    <div class="card-body">
        @if($isMultiOutlet ?? false)
            {{-- Multi-outlet report: show each outlet separately --}}
            @foreach($outletsData as $outletIndex => $outletData)
                @php
                    $thisMonth = $outletData['data']['this_month'] ?? [];
                    $comparisons = $outletData['data']['comparisons'] ?? [];
                    $outletInsights = $outletData['insights'] ?? null;
                    $outletCharts = $outletData['charts'] ?? [];
                @endphp

                <div style="margin-bottom: 40px; {{ $outletIndex > 0 ? 'border-top: 3px solid #e5e7eb; padding-top: 30px;' : '' }}">
                    <h2 style="font-size: 20px; font-weight: 700; color: #1f2937; margin-bottom: 20px; display: flex; align-items: center;">
                        <span style="background: #8b5cf6; color: white; padding: 4px 12px; border-radius: 6px; font-size: 14px; margin-right: 12px;">{{ $outletIndex + 1 }}</span>
                        {{ $outletData['outlet_name'] }}
                    </h2>

                    @include('emails.reports.partials.monthly-content', [
                        'thisMonth' => $thisMonth,
                        'comparisons' => $comparisons,
                        'insights' => $outletInsights,
                        'charts' => $outletCharts,
                        'reportData' => $outletData['data'],
                    ])
                </div>
            @endforeach
        @else
            {{-- Single outlet report --}}
            @php
                $thisMonth = $reportData['this_month'] ?? [];
                $comparisons = $reportData['comparisons'] ?? [];
            @endphp

            @include('emails.reports.partials.monthly-content', [
                'thisMonth' => $thisMonth,
                'comparisons' => $comparisons,
                'insights' => $insights,
                'charts' => $charts,
                'reportData' => $reportData,
            ])
        @endif
    </div>
</div>
@endsection
