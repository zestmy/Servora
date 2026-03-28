@extends('pdf.layout')

@section('title', ($cn->type === 'debit_note' ? 'Debit Note' : 'Credit Note') . ' - ' . $cn->credit_note_number)

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
            <div class="doc-title">{{ $cn->type === 'debit_note' ? 'Debit Note' : 'Credit Note' }}</div>
            <div class="doc-number">{{ $cn->credit_note_number }}</div>
            <div class="doc-status">{{ ucfirst($cn->status) }}</div>
        </div>
    </div>

    {{-- Supplier & Document Details --}}
    <div class="info-grid">
        <div class="info-box">
            <h4>Supplier</h4>
            <p class="name">{{ $cn->supplier?->name ?? '—' }}</p>
            @if ($cn->supplier?->address)
                <p>{{ $cn->supplier->address }}</p>
            @endif
            @if ($cn->supplier?->phone)
                <p>Tel: {{ $cn->supplier->phone }}</p>
            @endif
            @if ($cn->supplier?->email)
                <p>{{ $cn->supplier->email }}</p>
            @endif
        </div>
        <div class="info-box">
            <h4>Document Details</h4>
            <p><strong>Direction:</strong> {{ ucfirst($cn->direction ?? '—') }}</p>
            @if ($cn->outlet)
                <p><strong>Outlet:</strong> {{ $cn->outlet->name }}</p>
            @endif
            @if ($cn->reason)
                <p><strong>Reason:</strong> {{ $cn->reason }}</p>
            @endif
        </div>
    </div>

    {{-- Meta --}}
    <table class="meta-table">
        <tr>
            <td class="label">Issued Date:</td>
            <td class="value">{{ $cn->issued_date?->format('d M Y') ?? '—' }}</td>
            <td class="label">Created By:</td>
            <td class="value">{{ $cn->createdBy?->name ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">Linked PO:</td>
            <td class="value">{{ $cn->purchaseOrder?->po_number ?? '—' }}</td>
            <td class="label">Linked GRN:</td>
            <td class="value">{{ $cn->goodsReceivedNote?->grn_number ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">Linked Invoice:</td>
            <td class="value">{{ $cn->procurementInvoice?->invoice_number ?? '—' }}</td>
            <td class="label"></td>
            <td class="value"></td>
        </tr>
    </table>

    {{-- Items --}}
    <table class="items">
        <thead>
            <tr>
                <th style="width: 30px;">#</th>
                <th>Item</th>
                <th>Description</th>
                <th class="center">Qty</th>
                <th class="center">UOM</th>
                <th class="right">Unit Price</th>
                <th class="right">Total</th>
                <th>Reason</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($cn->lines as $i => $line)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $line->ingredient?->name ?? '—' }}</td>
                    <td>{{ $line->description ?? '' }}</td>
                    <td class="center">{{ floatval($line->quantity) }}</td>
                    <td class="center">{{ $line->uom?->abbreviation ?? '' }}</td>
                    <td class="right">{{ number_format($line->unit_price, 2) }}</td>
                    <td class="right">{{ number_format($line->total_price, 2) }}</td>
                    <td>
                        @php
                            $reasonLabels = [
                                'damaged' => 'Damaged',
                                'rejected' => 'Rejected',
                                'short_delivery' => 'Short Delivery',
                                'return' => 'Return',
                                'overcharge' => 'Overcharge',
                                'other' => 'Other',
                            ];
                        @endphp
                        {{ $reasonLabels[$line->reason_code] ?? ucfirst($line->reason_code ?? '') }}
                    </td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            @if (floatval($cn->tax_amount) > 0)
                <tr>
                    <td colspan="6" class="right">Subtotal ({{ $company?->currency ?? 'RM' }})</td>
                    <td class="right">{{ number_format($cn->subtotal, 2) }}</td>
                    <td></td>
                </tr>
                <tr>
                    <td colspan="6" class="right">Tax</td>
                    <td class="right">{{ number_format($cn->tax_amount, 2) }}</td>
                    <td></td>
                </tr>
            @endif
            <tr>
                <td colspan="6" class="right"><strong>Total Amount ({{ $company?->currency ?? 'RM' }})</strong></td>
                <td class="right"><strong>{{ number_format($cn->total_amount, 2) }}</strong></td>
                <td></td>
            </tr>
        </tfoot>
    </table>

    @if ($cn->notes)
        <div class="notes">
            <h4>Notes</h4>
            <p>{{ $cn->notes }}</p>
        </div>
    @endif

    {{-- Signatures --}}
    <div class="signatures">
        <div class="sig-box">
            <div class="sig-line">Prepared By</div>
            @if ($cn->createdBy)
                <div class="sig-name">{{ strtoupper($cn->createdBy->name) }}</div>
            @endif
        </div>
        <div class="sig-box">
            <div class="sig-line">Authorized By</div>
        </div>
        <div class="sig-box">
            <div class="sig-line">Acknowledged By (Supplier)</div>
        </div>
    </div>
@endsection
