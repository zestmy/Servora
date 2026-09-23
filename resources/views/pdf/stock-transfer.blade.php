@extends('pdf.layout')

@section('title', 'Stock Transfer — ' . $transfer->transfer_number)

@section('content')
    @include('pdf.partials.ot-claim-styles')
    @include('pdf.partials.transfer-doc-styles')

    @php
        $statusClass = ['received' => '', 'in_transit' => 'transit', 'draft' => 'draft', 'cancelled' => 'cancelled'][$transfer->status] ?? 'draft';
        $statusLabel = ['draft' => 'Draft', 'in_transit' => 'In Transit', 'received' => 'Received', 'cancelled' => 'Cancelled'][$transfer->status] ?? ucfirst($transfer->status);
        $lines = $transfer->lines;
        $total = $lines->sum(fn ($l) => (float) $l->quantity * (float) $l->unit_cost);
    @endphp

    <div class="ot-header">
        <div class="hl">
            @if ($logo = $company?->logoDataUri())
                <img src="{{ $logo }}" class="company-logo" alt="">
            @endif
            <div class="company-name">{{ $company->brand_name ?? $company->name }}</div>
            @if ($company->registration_number)
                <div class="company-detail">Reg No: {{ $company->registration_number }}</div>
            @endif
            @if ($company->billing_address)
                <div class="company-detail">{{ $company->billing_address }}</div>
            @endif
        </div>
        <div class="hr">
            <div class="doc-title">Stock Transfer Note</div>
            <div class="doc-number">{{ $transfer->transfer_number }}</div>
            <div class="doc-status {{ $statusClass }}">{{ $statusLabel }}</div>
        </div>
    </div>

    <div class="info-grid">
        <div class="info-cell left">
            <div class="info-card">
                <h4>From</h4>
                <div class="info-body">
                    <div class="kv name"><div class="k">Outlet</div><div class="v">{{ $transfer->fromOutlet?->name ?? '—' }}</div></div>
                    @if ($transfer->fromOutlet?->address)
                        <div class="kv"><div class="k">Address</div><div class="v">{{ $transfer->fromOutlet->address }}</div></div>
                    @endif
                    <div class="kv"><div class="k">Date</div><div class="v">{{ $transfer->transfer_date->format('d M Y') }}</div></div>
                    <div class="kv"><div class="k">Raised By</div><div class="v">{{ $transfer->createdBy?->name ?? '—' }}</div></div>
                </div>
            </div>
        </div>
        <div class="info-cell right">
            <div class="info-card">
                <h4>To</h4>
                <div class="info-body">
                    <div class="kv name"><div class="k">Outlet</div><div class="v">{{ $transfer->toOutlet?->name ?? '—' }}</div></div>
                    @if ($transfer->toOutlet?->address)
                        <div class="kv"><div class="k">Address</div><div class="v">{{ $transfer->toOutlet->address }}</div></div>
                    @endif
                    <div class="kv"><div class="k">Items</div><div class="v">{{ $lines->count() }}</div></div>
                    <div class="kv"><div class="k">Total Value</div><div class="v money-total">RM {{ number_format($total, 2) }}</div></div>
                </div>
            </div>
        </div>
    </div>

    <div class="section-title">Items</div>
    <table class="items">
        <thead>
            <tr>
                <th style="width: 5%;">#</th>
                <th style="width: 43%;">Item</th>
                <th class="num" style="width: 11%;">Qty</th>
                <th style="width: 9%;">UOM</th>
                <th class="num" style="width: 14%;">Unit Cost</th>
                <th class="num" style="width: 18%;">Total (RM)</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($lines as $i => $l)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td style="font-weight: bold;">
                        {{ $l->item_name }}
                        @if ($l->ingredient?->is_prep)
                            <span class="tag tag-prep">PREP</span>
                        @elseif ($l->recipe_id)
                            <span class="tag tag-recipe">RECIPE</span>
                        @elseif (! $l->ingredient_id)
                            <span class="tag tag-custom">CUSTOM</span>
                        @endif
                        @if ($l->ingredient?->code || $l->recipe?->code)
                            <span class="sub" style="font-weight: normal;">{{ $l->ingredient?->code ?? $l->recipe?->code }}</span>
                        @endif
                    </td>
                    <td class="num">{{ rtrim(rtrim(number_format((float) $l->quantity, 4), '0'), '.') }}</td>
                    <td>{{ $l->uom?->abbreviation }}</td>
                    <td class="num">{{ number_format((float) $l->unit_cost, 4) }}</td>
                    <td class="num" style="font-weight: bold;">{{ number_format((float) $l->quantity * (float) $l->unit_cost, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr class="grand">
                <td colspan="5" class="total-label" style="text-align: right;">Total Value</td>
                <td class="num">RM {{ number_format($total, 2) }}</td>
            </tr>
        </tfoot>
    </table>

    @if ($transfer->notes)
        <div class="notes"><h4>Notes</h4><p>{{ $transfer->notes }}</p></div>
    @endif

    {{-- Goods change hands physically, so both ends sign. --}}
    <div class="signatures">
        <div class="sig-cell">
            <div class="sig-role">Released By ({{ $transfer->fromOutlet?->name }})</div>
            <div class="sig-space"></div>
            <div class="sig-title">Name / Signature / Date</div>
        </div>
        <div class="sig-cell">
            <div class="sig-role">Received By ({{ $transfer->toOutlet?->name }})</div>
            <div class="sig-space"></div>
            <div class="sig-title">Name / Signature / Date</div>
        </div>
    </div>

    <div class="computer-generated-note">
        Unit costs are from purchasing records at the time the transfer was raised; recipes at cost per yield unit.
        Recipe and custom items are recorded for value only and do not move stock on hand.
    </div>
@endsection
