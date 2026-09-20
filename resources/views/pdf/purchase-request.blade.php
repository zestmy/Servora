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
                <th style="width: 37%;">Item</th>
                <th style="width: 9%;" class="right">Qty</th>
                <th style="width: 8%;" class="center">UOM</th>
                <th style="width: 24%;">Preferred Supplier</th>
                <th style="width: 17%;">Notes</th>
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
                    </td>
                    <td class="right">{{ number_format($line->quantity, 1) }}</td>
                    <td class="center">{{ $line->uom?->abbreviation ?? '' }}</td>
                    <td>{{ $line->preferredSupplier?->name ?? '—' }}</td>
                    <td><small>{{ $line->notes ?? '' }}</small></td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="2" class="total-label">Total &mdash; {{ $pr->lines->count() }} {{ \Illuminate\Support\Str::plural('item', $pr->lines->count()) }}</td>
                <td class="right total-value">{{ number_format($pr->lines->sum('quantity'), 1) }}</td>
                <td colspan="3"></td>
            </tr>
        </tfoot>
    </table>

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
