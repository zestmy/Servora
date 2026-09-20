@extends('pdf.layout')

@section('title', 'Purchase Request - ' . $pr->pr_number)

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
        </div>
        <div class="header-right">
            <div class="doc-title">Purchase Request</div>
            <div class="doc-number">{{ $pr->pr_number }}</div>
            <div class="doc-status">{{ ucfirst($pr->status) }}</div>
        </div>
    </div>

    {{-- Info --}}
    <div class="info-grid">
        <div class="info-box">
            <h4>Requesting Outlet</h4>
            <p class="name">{{ $pr->outlet?->name ?? '—' }}</p>
            @if ($pr->outlet?->address)
                <p>{{ $pr->outlet->address }}</p>
            @endif
        </div>
        <div class="info-box">
            <h4>Details</h4>
            <table class="meta-table">
                <tr><td>Request Date:</td><td>{{ $pr->requested_date->format('d M Y') }}</td></tr>
                @if ($pr->needed_by_date)
                    <tr><td>Needed By:</td><td>{{ $pr->needed_by_date->format('d M Y') }}</td></tr>
                @endif
                @if ($pr->department)
                    <tr><td>Department:</td><td>{{ $pr->department->name }}</td></tr>
                @endif
                <tr><td>Requested By:</td><td>{{ $pr->createdBy?->name ?? '—' }}</td></tr>
                @if ($pr->approvedBy)
                    <tr><td>Approved By:</td><td>{{ $pr->approvedBy->name }}</td></tr>
                @endif
            </table>
        </div>
    </div>

    {{-- Items --}}
    <table class="items">
        <thead>
            <tr>
                <th style="width: 5%;">#</th>
                {{-- The supplier prints under the item name rather than in a
                     column of its own: it belongs to the item, and the room it
                     was taking is worth more to the figures. --}}
                <th style="width: 41%;">Item</th>
                <th style="width: 8%;" class="right">Qty</th>
                <th style="width: 7%;" class="center">UOM</th>
                <th style="width: 13%;" class="right">Price</th>
                <th style="width: 13%;" class="right">Total</th>
                <th style="width: 13%;">Notes</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($pr->lines as $i => $line)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>
                        {{ $line->displayName() }}
                        @if ($line->isAssetItem())
                            <small style="color: #0369a1;">(Asset)</small>
                        @elseif ($line->isKitchenItem())
                            <small style="color: #7c3aed;">(Kitchen)</small>
                        @elseif ($line->custom_name && ! $line->ingredient_id)
                            <small style="color: #b45309;">(Custom)</small>
                        @endif
                        @if ($line->preferredSupplier)
                            <br><small style="color: #64748b;">{{ $line->preferredSupplier->name }}</small>
                        @endif
                    </td>
                    <td class="right">{{ number_format($line->quantity, 1) }}</td>
                    <td class="center">{{ $line->uom?->abbreviation ?? '' }}</td>
                    @php
                        $price = $linePrices[$line->id] ?? 0;
                        $lineTotal = $price * floatval($line->quantity);
                    @endphp
                    <td class="right">{{ $price > 0 ? number_format($price, 2) : '—' }}</td>
                    <td class="right">{{ $price > 0 ? number_format($lineTotal, 2) : '—' }}</td>
                    <td><small>{{ $line->notes ?? '' }}</small></td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="2" class="total-label">Total &mdash; {{ $pr->lines->count() }} {{ \Illuminate\Support\Str::plural('item', $pr->lines->count()) }}</td>
                <td class="right total-value">{{ number_format($pr->lines->sum('quantity'), 1) }}</td>
                <td></td>
                <td></td>
                {{-- Estimated, and labelled as such: the price is settled on the
                     purchase order, against a real supplier. --}}
                <td class="right total-value">
                    {{ number_format($pr->lines->sum(fn ($l) => ($linePrices[$l->id] ?? 0) * floatval($l->quantity)), 2) }}
                </td>
                <td></td>
            </tr>
        </tfoot>
    </table>

    <p style="font-size: 7.5pt; color: #64748b; margin-top: 4px; font-style: italic;">
        Prices are estimates from the last price paid to each supplier. The amount payable is set on the purchase order.
    </p>

    {{-- Notes --}}
    @if ($pr->notes)
        <div class="notes-box">
            <strong>Notes</strong>
            <p>{{ $pr->notes }}</p>
        </div>
    @endif

    {{-- Signatures — two, not the order's three: nobody receives a request. --}}
    <div class="signatures">
        <div class="sig-box">
            <div class="sig-line">Requested By</div>
            @if ($pr->createdBy)
                <div class="sig-name">{{ strtoupper($pr->createdBy->name) }}</div>
            @endif
        </div>
        <div class="sig-box">
            <div class="sig-line">Approved By</div>
            @if ($pr->approvedBy)
                <div class="sig-name">{{ strtoupper($pr->approvedBy->name) }}</div>
            @endif
        </div>
        <div class="sig-box"></div>
    </div>
@endsection
