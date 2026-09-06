@extends('pdf.layout')

@section('title', 'Transfer Details')

@section('content')
    {{-- Header --}}
    <div class="header">
        <div class="header-left">
            @if ($company?->logo)
                <img src="{{ public_path('storage/' . $company->logo) }}" class="company-logo" alt="">
            @endif
            <div class="company-name">{{ $company?->name ?? 'Company' }}</div>
            @if ($company?->registration_number)
                <div class="company-detail">Reg No: {{ $company->registration_number }}</div>
            @endif
        </div>
        <div class="header-right">
            <div class="doc-title">Transfer Details</div>
            <div class="doc-number">{{ \Carbon\Carbon::parse($scope['from'])->format('d M Y') }} &ndash;
                {{ \Carbon\Carbon::parse($scope['to'])->format('d M Y') }}</div>
            <div class="doc-status">{{ $report['transfers']->count() }} {{ \Illuminate\Support\Str::plural('transfer', $report['transfers']->count()) }} merged</div>
        </div>
    </div>

    {{-- Scope --}}
    <table style="width: 100%; border-collapse: collapse; margin-bottom: 12px; border: 1px solid #e5e7eb;">
        <tr>
            <td style="width: 14%; padding: 6px 10px; background: #f9fafb; font-size: 8.5pt; font-weight: bold; color: #475569; text-transform: uppercase; letter-spacing: 0.5px; border-right: 1px solid #e5e7eb; border-bottom: 1px solid #e5e7eb;">Outlet</td>
            <td style="width: 36%; padding: 6px 10px; font-size: 9.5pt; color: #0f172a; border-right: 1px solid #e5e7eb; border-bottom: 1px solid #e5e7eb;">{{ $scope['outlet'] }}</td>
            <td style="width: 14%; padding: 6px 10px; background: #f9fafb; font-size: 8.5pt; font-weight: bold; color: #475569; text-transform: uppercase; letter-spacing: 0.5px; border-right: 1px solid #e5e7eb; border-bottom: 1px solid #e5e7eb;">Status</td>
            <td style="width: 36%; padding: 6px 10px; font-size: 9.5pt; color: #0f172a; border-bottom: 1px solid #e5e7eb;">{{ $scope['status'] }}</td>
        </tr>
        <tr>
            <td style="padding: 6px 10px; background: #f9fafb; font-size: 8.5pt; font-weight: bold; color: #475569; text-transform: uppercase; letter-spacing: 0.5px; border-right: 1px solid #e5e7eb;">Items</td>
            <td style="padding: 6px 10px; font-size: 9.5pt; color: #0f172a; border-right: 1px solid #e5e7eb;">{{ number_format($report['itemCount']) }}</td>
            <td style="padding: 6px 10px; background: #f9fafb; font-size: 8.5pt; font-weight: bold; color: #475569; text-transform: uppercase; letter-spacing: 0.5px; border-right: 1px solid #e5e7eb;">Generated</td>
            <td style="padding: 6px 10px; font-size: 9.5pt; color: #0f172a;">{{ now()->format('d M Y, H:i') }}</td>
        </tr>
    </table>

    @if ($report['itemCount'] === 0)
        <div style="border: 1px solid #e5e7eb; padding: 24px; text-align: center; color: #64748b; font-size: 10pt;">
            No transfers in this range under these filters.
        </div>
    @else
        {{-- Items --}}
        <table class="items">
            <thead>
                <tr>
                    <th style="width: 22px;">#</th>
                    <th>Item</th>
                    <th class="center" style="width: 46px;">UOM</th>
                    <th class="right" style="width: 68px;">Quantity</th>
                    <th class="right" style="width: 68px;">Unit Cost</th>
                    <th class="right" style="width: 78px;">Value (RM)</th>
                </tr>
            </thead>
            <tbody>
                @php $rowNum = 0; @endphp
                @foreach ($report['groups'] as $group)
                    <tr>
                        <td colspan="4" style="background: #e5e7eb; font-weight: bold; font-size: 9px; text-transform: uppercase; letter-spacing: 0.5px; padding: 5px 8px; border-bottom: 2px solid #9ca3af;">
                            {{ $group['name'] }} ({{ count($group['items']) }})
                        </td>
                        <td colspan="2" style="background: #e5e7eb; font-weight: bold; font-size: 9px; text-align: right; padding: 5px 8px; border-bottom: 2px solid #9ca3af;">
                            {{ number_format($group['value'], 2) }}
                        </td>
                    </tr>
                    @foreach ($group['items'] as $item)
                        @php $rowNum++; @endphp
                        <tr>
                            <td>{{ $rowNum }}</td>
                            <td>
                                {{ $item['name'] }}
                                @if ($item['code'])
                                    <span style="color: #888; font-size: 8px;">&middot; {{ $item['code'] }}</span>
                                @endif
                                @if ($item['lines'] > 1)
                                    <span style="color: #888; font-size: 8px;">({{ $item['lines'] }} transfers)</span>
                                @endif
                            </td>
                            <td class="center">{{ $item['uom_abbr'] }}</td>
                            <td class="right">{{ rtrim(rtrim(number_format($item['quantity'], 4), '0'), '.') ?: '0' }}</td>
                            <td class="right">{{ number_format($item['unit_cost'], 4) }}</td>
                            <td class="right">{{ number_format($item['value'], 2) }}</td>
                        </tr>
                    @endforeach
                @endforeach
                <tr>
                    <td colspan="5" style="text-align: right; font-weight: bold; padding: 7px 8px; border-top: 2px solid #475569;">Total transfer value</td>
                    <td class="right" style="font-weight: bold; padding: 7px 8px; border-top: 2px solid #475569;">
                        RM {{ number_format($report['total'], 2) }}
                    </td>
                </tr>
            </tbody>
        </table>
    @endif

    {{-- What was merged. --}}
    <div style="margin-top: 18px; page-break-inside: avoid;">
        <h4 style="font-size: 9pt; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; color: #475569; margin-bottom: 6px;">Transfers merged</h4>
        <table class="items">
            <thead>
                <tr>
                    <th style="width: 26px;">#</th>
                    <th style="width: 74px;">Date</th>
                    <th>Transfer #</th>
                    <th>From</th>
                    <th>To</th>
                    <th class="center" style="width: 64px;">Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($report['transfers'] as $i => $transfer)
                    <tr>
                        <td>{{ $i + 1 }}</td>
                        <td>{{ $transfer->transfer_date?->format('d M Y') ?? '—' }}</td>
                        <td>{{ $transfer->transfer_number ?? 'T-' . $transfer->id }}</td>
                        <td>{{ $transfer->fromOutlet?->name ?? '—' }}</td>
                        <td>{{ $transfer->toOutlet?->name ?? '—' }}</td>
                        <td class="center">{{ ucfirst(str_replace('_', ' ', $transfer->status)) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" style="text-align: center; color: #64748b;">None</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
