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
    <table class="meta-table">
        <tr>
            <td class="label">Count Date:</td>
            <td class="value">{{ $stockTake->stock_take_date?->format('d M Y') ?? '—' }}</td>
            <td class="label">Outlet:</td>
            <td class="value">{{ $stockTake->outlet?->name ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">Department:</td>
            <td class="value">{{ $stockTake->department?->name ?? 'All' }}</td>
            <td class="label">Prepared By:</td>
            <td class="value">{{ $stockTake->createdBy?->name ?? '—' }}</td>
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
    <div class="signatures">
        <div class="sig-box">
            <div class="sig-line">Counted By</div>
            <div style="margin-top: 10px; font-size: 9px; color: #666;">Date: _______________</div>
        </div>
        <div class="sig-box">
            <div class="sig-line">Verified By</div>
            <div style="margin-top: 10px; font-size: 9px; color: #666;">Date: _______________</div>
        </div>
        <div class="sig-box">
            <div class="sig-line">Approved By</div>
            <div style="margin-top: 10px; font-size: 9px; color: #666;">Date: _______________</div>
        </div>
    </div>
@endsection
