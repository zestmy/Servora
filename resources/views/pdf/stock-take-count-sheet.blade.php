@extends('pdf.layout')

@section('title', 'Count Sheet')

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
            <div class="doc-title">Count Sheet</div>
            <div class="doc-number">{{ $stockTake->reference_number ?? 'ST-' . $stockTake->id }}</div>
            <div class="doc-status">{{ ucfirst($stockTake->status) }}</div>
        </div>
    </div>

    {{-- Meta --}}
    <table style="width: 100%; border-collapse: collapse; margin-bottom: 12px; border: 1px solid #e5e7eb;">
        <tr>
            <td style="width: 14%; padding: 6px 10px; background: #f9fafb; font-size: 8.5pt; font-weight: bold; color: #475569; text-transform: uppercase; letter-spacing: 0.5px; border-right: 1px solid #e5e7eb; border-bottom: 1px solid #e5e7eb;">Count Date</td>
            <td style="width: 36%; padding: 6px 10px; font-size: 9.5pt; color: #0f172a; border-right: 1px solid #e5e7eb; border-bottom: 1px solid #e5e7eb;">{{ $stockTake->stock_take_date?->format('d M Y') ?? '—' }}</td>
            <td style="width: 14%; padding: 6px 10px; background: #f9fafb; font-size: 8.5pt; font-weight: bold; color: #475569; text-transform: uppercase; letter-spacing: 0.5px; border-right: 1px solid #e5e7eb; border-bottom: 1px solid #e5e7eb;">Outlet</td>
            <td style="width: 36%; padding: 6px 10px; font-size: 9.5pt; color: #0f172a; border-bottom: 1px solid #e5e7eb;">{{ $stockTake->outlet?->name ?? '—' }}</td>
        </tr>
        <tr>
            <td style="padding: 6px 10px; background: #f9fafb; font-size: 8.5pt; font-weight: bold; color: #475569; text-transform: uppercase; letter-spacing: 0.5px; border-right: 1px solid #e5e7eb;">Department</td>
            <td style="padding: 6px 10px; font-size: 9.5pt; color: #0f172a; border-right: 1px solid #e5e7eb;">{{ $stockTake->department?->name ?? 'All' }}</td>
            <td style="padding: 6px 10px; background: #f9fafb; font-size: 8.5pt; font-weight: bold; color: #475569; text-transform: uppercase; letter-spacing: 0.5px; border-right: 1px solid #e5e7eb;">Prepared By</td>
            <td style="padding: 6px 10px; font-size: 9.5pt; color: #0f172a;">{{ $stockTake->createdBy?->name ?? '—' }}</td>
        </tr>
    </table>

    {{-- Items --}}
    <table class="items">
        <thead>
            <tr>
                <th style="width: 30px;">#</th>
                <th>Item</th>
                <th class="center" style="width: 50px;">UOM</th>
                <th class="center" style="width: 100px;">Quantity</th>
            </tr>
        </thead>
        <tbody>
            @php $rowNum = 0; @endphp
            @foreach ($groupedLines as $groupName => $lines)
                {{-- Category header --}}
                <tr>
                    <td colspan="4" style="background: #e5e7eb; font-weight: bold; font-size: 9px; text-transform: uppercase; letter-spacing: 0.5px; padding: 5px 8px; border-bottom: 2px solid #9ca3af;">
                        {{ $groupName }} ({{ $lines->count() }})
                    </td>
                </tr>
                @foreach ($lines as $line)
                    @php
                        $rowNum++;
                        $cat = $line->ingredient?->ingredientCategory;
                        $parent = $cat?->parent;
                        $subName = $parent ? $cat->name : '';
                    @endphp
                    <tr>
                        <td>{{ $rowNum }}</td>
                        <td>
                            @if ($subName)
                                <span style="color: #888; font-size: 9px;">{{ $subName }} &middot; </span>
                            @endif
                            {{ $line->ingredient?->name ?? '—' }}
                        </td>
                        <td class="center">{{ $line->ingredient?->baseUom?->abbreviation ?? '' }}</td>
                        <td style="border: 1px solid #000; min-height: 20px;">&nbsp;</td>
                    </tr>
                @endforeach
            @endforeach
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
    <table style="width: 100%; border-collapse: separate; border-spacing: 14px 0; margin-top: 30px; page-break-inside: avoid;">
        <tr>
            @foreach (['Counted By', 'Verified By', 'Approved By'] as $role)
                <td style="width: 33.33%; vertical-align: top;">
                    <div style="border-top: 1px solid #555; padding-top: 6px; text-align: center; font-size: 9.5pt; font-weight: bold; color: #0f172a;">{{ $role }}</div>
                    <div style="margin-top: 10px; font-size: 9px; color: #666; text-align: center;">Date: _______________</div>
                </td>
            @endforeach
        </tr>
    </table>
@endsection
