@extends('pdf.layout')

@section('title', 'Invoice ' . $invoice->invoice_number)

@section('content')
    {{--
        The platform's own invoice to a tenant. The seller block is Servora,
        the buyer block is the tenant — the exact opposite of
        pdf/procurement-invoice.blade.php, which is a tenant's supplier
        billing the tenant. Do not merge the two templates.

        Every buyer field reads from $invoice->bill_to, the snapshot frozen
        when the invoice was raised. Reading the company live would silently
        change the address on a document the customer already has.
    --}}
    @php
        $billTo   = $invoice->bill_to ?? [];
        $currency = $invoice->currency ?: 'MYR';
        $logo     = public_path('images/servora-logo-black.png');
    @endphp

    <div class="header">
        <div class="header-left">
            @if (file_exists($logo))
                <img src="{{ $logo }}" class="company-logo" alt="">
            @endif
            <div class="company-name">{{ $seller['name'] ?? 'Servora' }}</div>
            @if (!empty($seller['registration_number']))
                <div class="company-detail">Reg No: {{ $seller['registration_number'] }}</div>
            @endif
            @if (!empty($seller['tax_number']))
                <div class="company-detail">Tax No: {{ $seller['tax_number'] }}</div>
            @endif
            @if (!empty($seller['address']))
                <div class="company-detail">{{ $seller['address'] }}</div>
            @endif
            @if (!empty($seller['phone']))
                <div class="company-detail">Tel: {{ $seller['phone'] }}</div>
            @endif
            @if (!empty($seller['email']))
                <div class="company-detail">{{ $seller['email'] }}</div>
            @endif
        </div>
        <div class="header-right">
            <div class="doc-title">Tax Invoice</div>
            <div class="doc-number">{{ $invoice->invoice_number }}</div>
            <div class="doc-status">{{ $invoice->statusLabel() }}</div>
        </div>
    </div>

    <div class="info-grid">
        <div class="info-box">
            <h4>Bill To</h4>
            <p class="name">{{ $billTo['name'] ?? $invoice->companyName() }}</p>
            @if (!empty($billTo['registration_number']))
                <p>Reg No: {{ $billTo['registration_number'] }}</p>
            @endif
            @if (!empty($billTo['address']))
                <p>{{ $billTo['address'] }}</p>
            @endif
            @if (!empty($billTo['phone']))
                <p>Tel: {{ $billTo['phone'] }}</p>
            @endif
            @if (!empty($billTo['email']))
                <p>{{ $billTo['email'] }}</p>
            @endif
        </div>
        <div class="info-box">
            <h4>Subscription</h4>
            <p class="name">{{ $invoice->subscription?->plan?->name ?? '—' }}</p>
            @if ($invoice->subscription)
                <p>Billing: {{ ucfirst($invoice->subscription->billing_cycle ?? 'monthly') }}</p>
            @endif
            @if ($invoice->period_start && $invoice->period_end)
                <p>Service period: {{ $invoice->period_start->format('d M Y') }} – {{ $invoice->period_end->format('d M Y') }}</p>
            @endif
        </div>
    </div>

    <table class="meta-table">
        <tr>
            <td class="label">Invoice Date:</td>
            <td class="value">{{ $invoice->issued_at?->format('d M Y') ?? '—' }}</td>
            <td class="label">Due Date:</td>
            <td class="value">{{ $invoice->due_at?->format('d M Y') ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">Currency:</td>
            <td class="value">{{ $currency }}</td>
            <td class="label">Payment Date:</td>
            <td class="value">{{ $invoice->paid_at?->format('d M Y') ?? '—' }}</td>
        </tr>
        @if ($invoice->payment)
            <tr>
                <td class="label">Payment Method:</td>
                <td class="value">{{ ucfirst(str_replace('_', ' ', $invoice->payment->payment_method ?? '—')) }}</td>
                <td class="label">Payment Ref:</td>
                <td class="value">{{ $invoice->payment->chip_purchase_id ?? ($invoice->payment->metadata['reference'] ?? '—') }}</td>
            </tr>
        @endif
    </table>

    <table class="items">
        <thead>
            <tr>
                <th style="width: 30px;">#</th>
                <th>Description</th>
                <th class="center">Qty</th>
                <th class="right">Unit Price</th>
                <th class="right">Amount</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($invoice->line_items ?? [] as $i => $line)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $line['description'] ?? '' }}</td>
                    <td class="center">{{ floatval($line['quantity'] ?? 1) }}</td>
                    <td class="right">{{ number_format((float) ($line['unit_price'] ?? 0), 2) }}</td>
                    <td class="right">{{ number_format((float) ($line['amount'] ?? 0), 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="center">No line items.</td>
                </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                <td colspan="4" class="right">Subtotal</td>
                <td class="right">{{ number_format((float) $invoice->amount, 2) }}</td>
            </tr>
            @if ((float) $invoice->tax_amount > 0)
                <tr>
                    <td colspan="4" class="right">
                        {{ $invoice->tax_label ?: 'Tax' }}
                        @if ((float) $invoice->tax_rate > 0)
                            ({{ rtrim(rtrim(number_format((float) $invoice->tax_rate, 2), '0'), '.') }}%)
                        @endif
                    </td>
                    <td class="right">{{ number_format((float) $invoice->tax_amount, 2) }}</td>
                </tr>
            @endif
            <tr>
                <td colspan="4" class="right"><strong>Total ({{ $currency }})</strong></td>
                <td class="right"><strong>{{ number_format((float) $invoice->total, 2) }}</strong></td>
            </tr>
            @if ($invoice->isPaid())
                <tr>
                    <td colspan="4" class="right">Paid</td>
                    <td class="right">-{{ number_format((float) $invoice->total, 2) }}</td>
                </tr>
                <tr>
                    <td colspan="4" class="right"><strong>Balance Due</strong></td>
                    <td class="right"><strong>0.00</strong></td>
                </tr>
            @elseif (!$invoice->isVoid())
                <tr>
                    <td colspan="4" class="right"><strong>Balance Due</strong></td>
                    <td class="right"><strong>{{ number_format((float) $invoice->total, 2) }}</strong></td>
                </tr>
            @endif
        </tfoot>
    </table>

    @if ($invoice->isVoid())
        <div class="notes">
            <h4>Voided</h4>
            <p>This invoice was voided on {{ $invoice->voided_at?->format('d M Y') }} and is not payable.
               {{ $invoice->void_reason }}</p>
        </div>
    @endif

    @if ($invoice->notes)
        <div class="notes">
            <h4>Notes</h4>
            <p>{{ $invoice->notes }}</p>
        </div>
    @endif

    @if (!empty($seller['bank_details']) && $invoice->isOutstanding())
        <div class="notes">
            <h4>Payment Details</h4>
            <p>{!! nl2br(e($seller['bank_details'])) !!}</p>
        </div>
    @endif

    @if (!empty($seller['footer_note']))
        <div class="notes">
            <p>{{ $seller['footer_note'] }}</p>
        </div>
    @endif
@endsection
