@extends('pdf.layout')

@section('title', 'Stock Take')

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
            <div class="doc-title">Stock Take</div>
            <div class="doc-number">{{ $stockTake->reference_number ?? 'ST-' . $stockTake->id }}</div>
            <div class="doc-status">{{ ucfirst($stockTake->status) }}</div>
        </div>
    </div>

    {{-- Meta --}}
    <table style="width: 100%; border-collapse: collapse; margin-bottom: 10px; border: 1px solid #e5e7eb;">
        <tr>
            <td style="width: 14%; padding: 6px 10px; background: #f9fafb; font-size: 8.5pt; font-weight: bold; color: #475569; text-transform: uppercase; letter-spacing: 0.5px; border-right: 1px solid #e5e7eb; border-bottom: 1px solid #e5e7eb;">Count Date</td>
            <td style="width: 36%; padding: 6px 10px; font-size: 9.5pt; color: #0f172a; border-right: 1px solid #e5e7eb; border-bottom: 1px solid #e5e7eb;">{{ $stockTake->stock_take_date?->format('d M Y') ?? '—' }}</td>
            <td style="width: 14%; padding: 6px 10px; background: #f9fafb; font-size: 8.5pt; font-weight: bold; color: #475569; text-transform: uppercase; letter-spacing: 0.5px; border-right: 1px solid #e5e7eb; border-bottom: 1px solid #e5e7eb;">Outlet</td>
            <td style="width: 36%; padding: 6px 10px; font-size: 9.5pt; color: #0f172a; border-bottom: 1px solid #e5e7eb;">{{ $stockTake->outlet?->name ?? '—' }}</td>
        </tr>
        <tr>
            <td style="padding: 6px 10px; background: #f9fafb; font-size: 8.5pt; font-weight: bold; color: #475569; text-transform: uppercase; letter-spacing: 0.5px; border-right: 1px solid #e5e7eb;">Department</td>
            <td style="padding: 6px 10px; font-size: 9.5pt; color: #0f172a; border-right: 1px solid #e5e7eb;">{{ $stockTake->department?->name ?? 'All' }}</td>
            <td style="padding: 6px 10px; background: #f9fafb; font-size: 8.5pt; font-weight: bold; color: #475569; text-transform: uppercase; letter-spacing: 0.5px; border-right: 1px solid #e5e7eb;">Counted By</td>
            <td style="padding: 6px 10px; font-size: 9.5pt; color: #0f172a;">{{ $stockTake->createdBy?->name ?? '—' }}</td>
        </tr>
    </table>

    {{-- What the count came to, before the detail that makes it up. --}}
    <table style="width: 100%; border-collapse: separate; border-spacing: 6px 0; margin-bottom: 12px;">
        <tr>
            <td style="width: 25%; border: 1px solid #e5e7eb; padding: 7px 10px;">
                <div style="font-size: 7.5pt; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px;">Items counted</div>
                <div style="font-size: 13pt; font-weight: bold; color: #0f172a;">{{ number_format($totals['items']) }}</div>
            </td>
            <td style="width: 25%; border: 1px solid #e5e7eb; padding: 7px 10px;">
                <div style="font-size: 7.5pt; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px;">Stock value</div>
                <div style="font-size: 13pt; font-weight: bold; color: #0f172a;">RM {{ number_format($totals['value'], 2) }}</div>
            </td>
            <td style="width: 25%; border: 1px solid #e5e7eb; padding: 7px 10px;">
                <div style="font-size: 7.5pt; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px;">Variance value</div>
                <div style="font-size: 13pt; font-weight: bold; color: {{ $totals['variance'] < 0 ? '#b91c1c' : '#0f172a' }};">
                    {{ $totals['variance'] > 0 ? '+' : '' }}RM {{ number_format($totals['variance'], 2) }}
                </div>
            </td>
            <td style="width: 25%; border: 1px solid #e5e7eb; padding: 7px 10px;">
                <div style="font-size: 7.5pt; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px;">Over / short</div>
                <div style="font-size: 13pt; font-weight: bold; color: #0f172a;">{{ $totals['over'] }} / {{ $totals['short'] }}</div>
            </td>
        </tr>
    </table>

    {{-- Items --}}
    <table class="items">
        <thead>
            <tr>
                <th style="width: 24px;">#</th>
                <th>Item</th>
                <th class="center" style="width: 40px;">UOM</th>
                <th class="right" style="width: 54px;">Expected</th>
                <th class="right" style="width: 54px;">Counted</th>
                <th class="right" style="width: 54px;">Variance</th>
                <th class="right" style="width: 58px;">Unit Cost</th>
                <th class="right" style="width: 62px;">Value</th>
                <th class="right" style="width: 62px;">Var. Cost</th>
            </tr>
        </thead>
        <tbody>
            @php $rowNum = 0; @endphp
            @forelse ($groups as $group)
                <tr>
                    <td colspan="7" style="background: #e5e7eb; font-weight: bold; font-size: 9px; text-transform: uppercase; letter-spacing: 0.5px; padding: 5px 8px; border-bottom: 2px solid #9ca3af;">
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
                            @if ($item['sub'])
                                <span style="color: #888; font-size: 8px;">{{ $item['sub'] }} &middot; </span>
                            @endif
                            {{ $item['name'] }}
                            @if ($item['code'])
                                <span style="color: #888; font-size: 8px;">&middot; {{ $item['code'] }}</span>
                            @endif
                        </td>
                        <td class="center">{{ $item['uom'] }}</td>
                        <td class="right">{{ rtrim(rtrim(number_format($item['system'], 4), '0'), '.') ?: '0' }}</td>
                        <td class="right">{{ rtrim(rtrim(number_format($item['counted'], 4), '0'), '.') ?: '0' }}</td>
                        {{-- Short is the one worth spotting on a page of numbers. --}}
                        <td class="right" style="{{ $item['variance'] < 0 ? 'color: #b91c1c; font-weight: bold;' : '' }}">
                            {{ $item['variance'] > 0 ? '+' : '' }}{{ rtrim(rtrim(number_format($item['variance'], 4), '0'), '.') ?: '0' }}
                        </td>
                        <td class="right">{{ number_format($item['unit_cost'], 4) }}</td>
                        <td class="right">{{ number_format($item['value'], 2) }}</td>
                        <td class="right" style="{{ $item['variance_cost'] < 0 ? 'color: #b91c1c;' : '' }}">
                            {{ $item['variance_cost'] > 0 ? '+' : '' }}{{ number_format($item['variance_cost'], 2) }}
                        </td>
                    </tr>
                @endforeach
            @empty
                <tr><td colspan="9" style="text-align: center; padding: 20px; color: #64748b;">No items on this count.</td></tr>
            @endforelse
            <tr>
                <td colspan="7" style="text-align: right; font-weight: bold; padding: 7px 8px; border-top: 2px solid #475569;">Total</td>
                <td class="right" style="font-weight: bold; padding: 7px 8px; border-top: 2px solid #475569;">{{ number_format($totals['value'], 2) }}</td>
                <td class="right" style="font-weight: bold; padding: 7px 8px; border-top: 2px solid #475569; {{ $totals['variance'] < 0 ? 'color: #b91c1c;' : '' }}">
                    {{ $totals['variance'] > 0 ? '+' : '' }}{{ number_format($totals['variance'], 2) }}
                </td>
            </tr>
        </tbody>
    </table>

    {{-- Notes --}}
    @if ($stockTake->notes)
        <div class="notes">
            <h4>Notes</h4>
            <p>{{ $stockTake->notes }}</p>
        </div>
    @endif

    {{-- Signatures --}}
    <table style="width: 100%; border-collapse: separate; border-spacing: 18px 0; margin-top: 30px; page-break-inside: avoid;">
        <tr>
            @foreach (['Counted By', 'Verified By', 'Approved By'] as $role)
                <td style="width: 33.33%; vertical-align: top;">
                    <div style="font-size: 9pt; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; color: #475569; margin-bottom: 6px;">{{ $role }}</div>
                    <div style="height: 56px; border: 1px solid #cbd5e1; background: #fafafa;"></div>
                    <div style="font-size: 8pt; color: #94a3b8; text-align: center; margin-top: 2px; letter-spacing: 0.5px;">Signature</div>
                    <div style="margin-top: 14px; border-bottom: 1px solid #555; height: 14px;"></div>
                    <div style="font-size: 8pt; color: #64748b; margin-top: 2px;">Name &amp; Date</div>
                </td>
            @endforeach
        </tr>
    </table>
@endsection
