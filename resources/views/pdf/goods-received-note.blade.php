@extends('pdf.layout')

@section('title', 'Goods Received Note - ' . $grn->grn_number)

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
            <div class="doc-title">Goods Received Note</div>
            <div class="doc-number">{{ $grn->grn_number }}</div>
            <div class="doc-status">{{ ucfirst($grn->status) }}</div>
        </div>
    </div>

    {{-- Supplier & Outlet --}}
    <div class="info-grid">
        <div class="info-box">
            <h4>Supplier</h4>
            <p class="name">{{ $grn->supplier?->name ?? '—' }}</p>
            @if ($grn->supplier?->address)
                <p>{{ $grn->supplier->address }}</p>
            @endif
            @if ($grn->supplier?->phone)
                <p>Tel: {{ $grn->supplier->phone }}</p>
            @endif
        </div>
        <div class="info-box">
            <h4>Received At</h4>
            <p class="name">{{ $grn->outlet?->name ?? '—' }}</p>
            @if ($grn->outlet?->address)
                <p>{{ $grn->outlet->address }}</p>
            @endif
            @if ($grn->outlet?->phone)
                <p>Tel: {{ $grn->outlet->phone }}</p>
            @endif
        </div>
    </div>

    {{-- Meta --}}
    <table class="meta-table">
        <tr>
            <td class="label">DO Reference:</td>
            <td class="value">{{ $grn->deliveryOrder?->do_number ?? '—' }}</td>
            <td class="label">PO Reference:</td>
            <td class="value">{{ $grn->purchaseOrder?->po_number ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">Received Date:</td>
            <td class="value">{{ $grn->received_date?->format('d M Y, h:i A') ?? 'Pending' }}</td>
            <td class="label">Received By:</td>
            <td class="value">{{ $grn->receivedBy?->name ?? '—' }}</td>
        </tr>
    </table>

    {{-- Items --}}
    <table class="items">
        <thead>
            <tr>
                <th style="width: 30px;">#</th>
                <th>Item</th>
                <th class="center">Expected</th>
                <th class="center">Received</th>
                <th class="center">UOM</th>
                @if ($showPrice)
                    <th class="right">Unit Cost</th>
                @endif
                <th class="center">Condition</th>
                @if ($showPrice)
                    <th class="right">Total</th>
                @endif
            </tr>
        </thead>
        <tbody>
            @foreach ($grn->lines as $i => $line)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $line->ingredient?->name ?? '—' }}</td>
                    <td class="center">{{ floatval($line->expected_quantity) }}</td>
                    <td class="center">{{ floatval($line->received_quantity) }}</td>
                    <td class="center">{{ $line->uom?->abbreviation ?? '' }}</td>
                    @if ($showPrice)
                        <td class="right">{{ number_format($line->unit_cost, 2) }}</td>
                    @endif
                    <td class="center">{{ ucfirst($line->condition) }}</td>
                    @if ($showPrice)
                        <td class="right">{{ number_format($line->total_cost, 2) }}</td>
                    @endif
                </tr>
            @endforeach
        </tbody>
        @if ($showPrice)
            <tfoot>
                <tr>
                    <td colspan="7" class="right">Total ({{ $company?->currency ?? 'RM' }})</td>
                    <td class="right">{{ number_format($grn->total_amount, 2) }}</td>
                </tr>
            </tfoot>
        @endif
    </table>

    @if ($grn->notes)
        <div class="notes">
            <h4>Notes</h4>
            <p>{{ $grn->notes }}</p>
        </div>
    @endif

    {{-- Signatures --}}
    <div class="signatures">
        <div class="sig-box">
            @if ($grn->createdBy)
                <div style="font-size: 10px; font-weight: bold; margin-bottom: 2px;">{{ strtoupper($grn->createdBy->name) }}</div>
            @endif
            <div class="sig-line">Checked By</div>
        </div>
        <div class="sig-box">
            @if ($grn->receivedBy)
                <div style="font-size: 10px; font-weight: bold; margin-bottom: 2px;">{{ strtoupper($grn->receivedBy->name) }}</div>
            @endif
            <div class="sig-line">Received By</div>
        </div>
        <div class="sig-box">
            <div class="sig-line">Acknowledged By</div>
        </div>
    </div>
@endsection
