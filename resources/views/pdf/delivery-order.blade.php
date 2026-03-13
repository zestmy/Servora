@extends('pdf.layout')

@section('title', 'Delivery Order - ' . $do->do_number)

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
            <div class="company-detail">{{ $company?->billing_address ?? $company?->address ?? '' }}</div>
            @if ($company?->phone)
                <div class="company-detail">Tel: {{ $company->phone }}</div>
            @endif
        </div>
        <div class="header-right">
            <div class="doc-title">Delivery Order</div>
            <div class="doc-number">{{ $do->do_number }}</div>
            <div class="doc-status">{{ ucfirst($do->status) }}</div>
        </div>
    </div>

    {{-- Supplier & Delivery --}}
    <div class="info-grid">
        <div class="info-box">
            <h4>Supplier</h4>
            <p class="name">{{ $do->supplier?->name ?? '—' }}</p>
            @if ($do->supplier?->address)
                <p>{{ $do->supplier->address }}</p>
            @endif
            @if ($do->supplier?->phone)
                <p>Tel: {{ $do->supplier->phone }}</p>
            @endif
        </div>
        <div class="info-box">
            <h4>Deliver To</h4>
            <p class="name">{{ $do->outlet?->name ?? '—' }}</p>
            @if ($do->outlet?->address)
                <p>{{ $do->outlet->address }}</p>
            @endif
            @if ($do->outlet?->phone)
                <p>Tel: {{ $do->outlet->phone }}</p>
            @endif
            @if ($do->purchaseOrder?->receiver_name)
                <p style="margin-top: 4px;"><strong>Attn:</strong> {{ $do->purchaseOrder->receiver_name }}</p>
            @endif
            @if ($do->purchaseOrder?->department)
                <p><strong>Dept:</strong> {{ $do->purchaseOrder->department->name }}</p>
            @endif
        </div>
    </div>

    {{-- Meta --}}
    <table class="meta-table">
        <tr>
            <td class="label">Order Date:</td>
            <td class="value">{{ $do->created_at?->format('d M Y, h:i A') ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">PO Reference:</td>
            <td class="value">{{ $do->purchaseOrder?->po_number ?? '—' }}</td>
            <td class="label">Delivery Date:</td>
            <td class="value">{{ $do->delivery_date?->format('d M Y') ?? '—' }}</td>
        </tr>
    </table>

    {{-- Items --}}
    <table class="items">
        <thead>
            <tr>
                <th style="width: 30px;">#</th>
                <th>Item</th>
                <th class="center">Ordered Qty</th>
                <th class="center">Delivered Qty</th>
                <th class="center">UOM</th>
                @if ($showPrice)
                    <th class="right">Unit Cost</th>
                @endif
                <th class="center">Condition</th>
            </tr>
        </thead>
        <tbody>
            @php $total = 0; @endphp
            @foreach ($do->lines as $i => $line)
                @php $lineTotal = floatval($line->delivered_quantity) * floatval($line->unit_cost); $total += $lineTotal; @endphp
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>
                        {{ $line->ingredient?->name ?? '—' }}
                        @if ($line->uom?->abbreviation === 'pack' && $line->ingredient)
                            @php
                                $doPackSize = \Illuminate\Support\Facades\DB::table('supplier_ingredients')
                                    ->where('supplier_id', $do->supplier_id)
                                    ->where('ingredient_id', $line->ingredient_id)
                                    ->value('pack_size');
                                $doPackSize = floatval($doPackSize ?? 1);
                                $doBaseUomAbbr = $line->ingredient->baseUom?->abbreviation ?? '';
                            @endphp
                            @if ($doPackSize > 1 && $doBaseUomAbbr)
                                <span style="font-size: 9px; color: #4f46e5;">({{ rtrim(rtrim(number_format($doPackSize, 4, '.', ''), '0'), '.') }} {{ strtoupper($doBaseUomAbbr) }}/PACK)</span>
                            @endif
                        @endif
                    </td>
                    <td class="center">{{ floatval($line->ordered_quantity) }}</td>
                    <td class="center">{{ floatval($line->delivered_quantity) }}</td>
                    <td class="center">{{ $line->uom?->abbreviation ?? '' }}</td>
                    @if ($showPrice)
                        <td class="right">{{ number_format($line->unit_cost, 2) }}</td>
                    @endif
                    <td class="center">{{ ucfirst($line->condition) }}</td>
                </tr>
            @endforeach
        </tbody>
        @if ($showPrice)
            <tfoot>
                @php
                    $poTaxPct = floatval($do->purchaseOrder?->tax_percent ?? 0);
                    $taxAmt = $poTaxPct > 0 ? round($total * ($poTaxPct / 100), 2) : 0;
                @endphp
                @if ($poTaxPct > 0)
                    <tr>
                        <td colspan="6" class="right">Subtotal ({{ $company?->currency ?? 'RM' }})</td>
                        <td class="right">{{ number_format($total, 2) }}</td>
                    </tr>
                    <tr>
                        <td colspan="6" class="right">{{ $company?->tax_type ?? 'Tax' }} ({{ number_format($poTaxPct, 0) }}%)</td>
                        <td class="right">{{ number_format($taxAmt, 2) }}</td>
                    </tr>
                    <tr>
                        <td colspan="6" class="right"><strong>Grand Total ({{ $company?->currency ?? 'RM' }})</strong></td>
                        <td class="right"><strong>{{ number_format($total + $taxAmt, 2) }}</strong></td>
                    </tr>
                @else
                    <tr>
                        <td colspan="6" class="right"><strong>Total ({{ $company?->currency ?? 'RM' }})</strong></td>
                        <td class="right"><strong>{{ number_format($total, 2) }}</strong></td>
                    </tr>
                @endif
            </tfoot>
        @endif
    </table>

    @if ($do->notes)
        <div class="notes">
            <h4>Notes</h4>
            <p>{{ $do->notes }}</p>
        </div>
    @endif

    {{-- Signatures --}}
    <div class="signatures">
        <div class="sig-box">
            @if ($do->createdBy)
                <div style="font-size: 10px; font-weight: bold; margin-bottom: 2px;">{{ strtoupper($do->createdBy->name) }}</div>
            @endif
            <div class="sig-line">Issued By</div>
        </div>
        <div class="sig-box">
            <div class="sig-line">Delivered By</div>
        </div>
        <div class="sig-box">
            @if ($do->receivedBy)
                <div style="font-size: 10px; font-weight: bold; margin-bottom: 2px;">{{ strtoupper($do->receivedBy->name) }}</div>
            @endif
            <div class="sig-line">Received By</div>
        </div>
    </div>

    <div style="margin-top: 30px; text-align: center; font-size: 8px; color: #999;">
        Powered by Servora - https://servora.com.my/
    </div>
@endsection
