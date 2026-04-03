@extends('pdf.layout')

@section('title', 'Invoice - ' . $invoice->invoice_number)

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
            <div class="doc-title">Procurement Invoice</div>
            <div class="doc-number">{{ $invoice->invoice_number }}</div>
            @if ($invoice->supplier_invoice_number)
                <div class="doc-detail" style="font-size: 10px; color: #666;">Ref: {{ $invoice->supplier_invoice_number }}</div>
            @endif
            <div class="doc-status">{{ ucfirst($invoice->status) }}</div>
        </div>
    </div>

    {{-- Supplier & Outlet --}}
    <div class="info-grid">
        <div class="info-box">
            <h4>Supplier</h4>
            <p class="name">{{ $invoice->supplier?->name ?? '—' }}</p>
            @if ($invoice->supplier?->address)
                <p>{{ $invoice->supplier->address }}</p>
            @endif
            @if ($invoice->supplier?->phone)
                <p>Tel: {{ $invoice->supplier->phone }}</p>
            @endif
            @if ($invoice->supplier?->email)
                <p>{{ $invoice->supplier->email }}</p>
            @endif
        </div>
        <div class="info-box">
            <h4>Outlet</h4>
            <p class="name">{{ $invoice->outlet?->name ?? '—' }}</p>
            @if ($invoice->outlet?->address)
                <p>{{ $invoice->outlet->address }}</p>
            @endif
            @if ($invoice->outlet?->phone)
                <p>Tel: {{ $invoice->outlet->phone }}</p>
            @endif
        </div>
    </div>

    {{-- Meta --}}
    <table class="meta-table">
        <tr>
            <td class="label">Issued Date:</td>
            <td class="value">{{ $invoice->issued_date?->format('d M Y') ?? '—' }}</td>
            <td class="label">Due Date:</td>
            <td class="value">{{ $invoice->due_date?->format('d M Y') ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">PO Number:</td>
            <td class="value">{{ $invoice->purchaseOrder?->po_number ?? '—' }}</td>
            <td class="label">GRN Number:</td>
            <td class="value">{{ $invoice->goodsReceivedNote?->grn_number ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">Created By:</td>
            <td class="value">{{ $invoice->createdBy?->name ?? '—' }}</td>
            <td class="label">Currency:</td>
            <td class="value">{{ $invoice->currency ?? 'MYR' }}</td>
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
            </tr>
        </thead>
        <tbody>
            @foreach ($invoice->lines as $i => $line)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $line->ingredient?->name ?? '—' }}</td>
                    <td>{{ $line->description ?? '' }}</td>
                    <td class="center">{{ floatval($line->quantity) }}</td>
                    <td class="center">{{ $line->uom?->abbreviation ?? '' }}</td>
                    <td class="right">{{ number_format($line->unit_price, 2) }}</td>
                    <td class="right">{{ number_format($line->total_price, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="6" class="right">Subtotal</td>
                <td class="right">{{ number_format($invoice->subtotal, 2) }}</td>
            </tr>
            @if (floatval($invoice->tax_amount) > 0)
                <tr>
                    <td colspan="6" class="right">{{ $invoice->taxRate?->name ?? 'Tax' }}</td>
                    <td class="right">{{ number_format($invoice->tax_amount, 2) }}</td>
                </tr>
            @endif
            @if (floatval($invoice->delivery_charges) > 0)
                <tr>
                    <td colspan="6" class="right">Delivery Charges</td>
                    <td class="right">{{ number_format($invoice->delivery_charges, 2) }}</td>
                </tr>
            @endif
            <tr>
                <td colspan="6" class="right"><strong>Total ({{ $invoice->currency ?? 'MYR' }})</strong></td>
                <td class="right"><strong>{{ number_format($invoice->total_amount, 2) }}</strong></td>
            </tr>
            @if (floatval($invoice->credit_applied) > 0)
                <tr>
                    <td colspan="6" class="right">Credit Applied</td>
                    <td class="right">-{{ number_format($invoice->credit_applied, 2) }}</td>
                </tr>
                <tr>
                    <td colspan="6" class="right"><strong>Balance Due</strong></td>
                    <td class="right"><strong>{{ number_format($invoice->balance_due, 2) }}</strong></td>
                </tr>
            @endif
        </tfoot>
    </table>

    @if ($invoice->notes)
        <div class="notes">
            <h4>Notes</h4>
            <p>{{ $invoice->notes }}</p>
        </div>
    @endif
@endsection
