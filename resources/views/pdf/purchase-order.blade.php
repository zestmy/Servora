@extends('pdf.layout')

@section('title', 'Purchase Order - ' . $po->po_number)

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
            @if ($company?->email)
                <div class="company-detail">{{ $company->email }}</div>
            @endif
        </div>
        <div class="header-right">
            <div class="doc-title">Purchase Order</div>
            <div class="doc-number">{{ $po->po_number }}</div>
            <div class="doc-status">{{ ucfirst($po->status) }}</div>
        </div>
    </div>

    {{-- Supplier & Delivery --}}
    <div class="info-grid">
        <div class="info-box">
            <h4>Supplier</h4>
            <p class="name">{{ $po->supplier?->name ?? '—' }}</p>
            @if ($po->supplier?->address)
                <p>{{ $po->supplier->address }}</p>
            @endif
            @if ($po->supplier?->phone)
                <p>Tel: {{ $po->supplier->phone }}</p>
            @endif
            @if ($po->supplier?->email)
                <p>{{ $po->supplier->email }}</p>
            @endif
        </div>
        <div class="info-box">
            <h4>Deliver To</h4>
            <p class="name">{{ $po->outlet?->name ?? '—' }}</p>
            @if ($po->outlet?->address)
                <p>{{ $po->outlet->address }}</p>
            @endif
            @if ($po->outlet?->phone)
                <p>Tel: {{ $po->outlet->phone }}</p>
            @endif
            @if ($po->receiver_name)
                <p style="margin-top: 4px;"><strong>Attn:</strong> {{ $po->receiver_name }}</p>
            @endif
            @if ($po->department)
                <p><strong>Dept:</strong> {{ $po->department->name }}</p>
            @endif
        </div>
    </div>

    {{-- Meta --}}
    <table class="meta-table">
        <tr>
            <td class="label">Order Date:</td>
            <td class="value">{{ $po->order_date?->format('d M Y, h:i A') ?? '—' }}</td>
            <td class="label">Expected Delivery:</td>
            <td class="value">{{ $po->expected_delivery_date?->format('d M Y') ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">Created By:</td>
            <td class="value">{{ $po->createdBy?->name ?? '—' }}</td>
            <td class="label">Approved By:</td>
            <td class="value">{{ $po->approvedBy?->name ?? '—' }}</td>
        </tr>
    </table>

    {{-- Items --}}
    <table class="items">
        <thead>
            <tr>
                <th style="width: 30px;">#</th>
                <th>Item</th>
                <th class="center">Qty</th>
                <th class="center">UOM</th>
                <th class="right">Unit Cost</th>
                <th class="right">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($po->lines as $i => $line)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>
                        {{ $line->ingredient?->name ?? '—' }}
                        @if ($line->uom?->abbreviation === 'pack' && $line->ingredient)
                            @php
                                $packSize = \Illuminate\Support\Facades\DB::table('supplier_ingredients')
                                    ->where('supplier_id', $po->supplier_id)
                                    ->where('ingredient_id', $line->ingredient_id)
                                    ->value('pack_size');
                                $packSize = floatval($packSize ?? 1);
                                $baseUomAbbr = $line->ingredient->baseUom?->abbreviation ?? '';
                            @endphp
                            @if ($packSize > 1 && $baseUomAbbr)
                                <span style="font-size: 9px; color: #4f46e5;">({{ rtrim(rtrim(number_format($packSize, 4, '.', ''), '0'), '.') }} {{ strtoupper($baseUomAbbr) }}/PACK)</span>
                            @endif
                        @endif
                    </td>
                    <td class="center">{{ floatval($line->quantity) }}</td>
                    <td class="center">{{ $line->uom?->abbreviation ?? '' }}</td>
                    <td class="right">{{ number_format($line->unit_cost, 2) }}</td>
                    <td class="right">{{ number_format($line->total_cost, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            @if ($po->tax_percent > 0)
                <tr>
                    <td colspan="5" class="right">Subtotal ({{ $company?->currency ?? 'RM' }})</td>
                    <td class="right">{{ number_format($po->subtotal, 2) }}</td>
                </tr>
                <tr>
                    <td colspan="5" class="right">{{ $company?->tax_type ?? 'Tax' }} ({{ number_format($po->tax_percent, 0) }}%)</td>
                    <td class="right">{{ number_format($po->tax_amount, 2) }}</td>
                </tr>
            @endif
            <tr>
                <td colspan="5" class="right"><strong>Grand Total ({{ $company?->currency ?? 'RM' }})</strong></td>
                <td class="right"><strong>{{ number_format($po->total_amount, 2) }}</strong></td>
            </tr>
        </tfoot>
    </table>

    @if ($po->notes)
        <div class="notes">
            <h4>Notes</h4>
            <p>{{ $po->notes }}</p>
        </div>
    @endif

    {{-- Signatures --}}
    <div class="signatures">
        <div class="sig-box">
            <div class="sig-line">Prepared By</div>
            @if ($po->createdBy)
                <div class="sig-name">{{ strtoupper($po->createdBy->name) }}</div>
            @endif
        </div>
        <div class="sig-box">
            <div class="sig-line">Approved By</div>
            @if ($po->approvedBy)
                <div class="sig-name">{{ strtoupper($po->approvedBy->name) }}</div>
            @endif
        </div>
        <div class="sig-box">
            <div class="sig-line">Received By</div>
            @if ($po->receiver_name)
                <div class="sig-name">{{ strtoupper($po->receiver_name) }}</div>
            @endif
        </div>
    </div>

    <div style="margin-top: 30px; text-align: center; font-size: 8px; color: #999;">
        Powered by Servora - https://servora.com.my/
    </div>
@endsection
