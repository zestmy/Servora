<div>
    @if (session()->has('success'))
        <div wire:key="flash-{{ microtime(true) }}" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)"
             class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-700 text-sm rounded-lg">
            {{ session('success') }}
        </div>
    @endif

    {{-- Top bar --}}
    <div class="flex items-center gap-3 mb-6">
        <a href="{{ route('purchasing.invoices.index') }}" class="text-gray-400 hover:text-gray-600 transition flex-shrink-0">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
            </svg>
        </a>
        <div class="flex-1 min-w-0">
            <p class="text-xs text-gray-400">
                <a href="{{ route('purchasing.invoices.index') }}" class="hover:underline">Invoices</a>
                / {{ $invoice->invoice_number }}
            </p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('purchasing.pdf', ['type' => 'inv', 'id' => $invoice->id]) }}"
               class="px-3 py-1.5 text-sm text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50 transition inline-flex items-center gap-1.5">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                PDF
            </a>
            @if ($invoice->status === 'issued')
                <button wire:click="markPaid" wire:confirm="Mark this invoice as paid?"
                        class="px-3 py-1.5 text-sm bg-green-600 text-white rounded-lg hover:bg-green-700 transition">
                    Mark Paid
                </button>
                <button wire:click="cancelInvoice" wire:confirm="Cancel this invoice?"
                        class="px-3 py-1.5 text-sm bg-red-50 text-red-600 border border-red-200 rounded-lg hover:bg-red-100 transition">
                    Cancel
                </button>
            @endif
        </div>
    </div>

    {{-- Invoice Info Card --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mb-4">
        <div class="flex items-start justify-between">
            <div>
                <h2 class="text-lg font-semibold text-gray-800">{{ $invoice->invoice_number }}</h2>
                @if ($invoice->supplier_invoice_number)
                    <p class="text-xs text-gray-400 mt-0.5">Supplier Ref: {{ $invoice->supplier_invoice_number }}</p>
                @endif
            </div>
            <div class="flex items-center gap-2">
                <span class="px-2 py-0.5 rounded text-xs {{ $invoice->type === 'supplier' ? 'bg-purple-50 text-purple-600' : 'bg-amber-50 text-amber-600' }}">
                    {{ $invoice->type === 'supplier' ? 'Supplier' : 'CPU → Outlet' }}
                </span>
                @php
                    $badge = match($invoice->status) {
                        'draft'     => 'bg-gray-100 text-gray-600',
                        'issued'    => 'bg-blue-100 text-blue-700',
                        'paid'      => 'bg-green-100 text-green-700',
                        'overdue'   => 'bg-red-100 text-red-600',
                        'cancelled' => 'bg-gray-100 text-gray-500',
                        default     => 'bg-gray-100 text-gray-500',
                    };
                @endphp
                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium {{ $badge }}">
                    {{ ucfirst($invoice->status) }}
                </span>
            </div>
        </div>

        <div class="mt-4 grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
            <div>
                <p class="text-xs text-gray-400">Supplier</p>
                <p class="font-medium text-gray-700">{{ $invoice->supplier?->name ?? '—' }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-400">Outlet</p>
                <p class="font-medium text-gray-700">{{ $invoice->outlet?->name ?? '—' }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-400">Issued Date</p>
                <p class="font-medium text-gray-700">{{ $invoice->issued_date?->format('d M Y') ?? '—' }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-400">Due Date</p>
                <p class="font-medium {{ $invoice->due_date?->isPast() && !in_array($invoice->status, ['paid', 'cancelled']) ? 'text-red-600' : 'text-gray-700' }}">
                    {{ $invoice->due_date?->format('d M Y') ?? '—' }}
                </p>
            </div>
            @if ($invoice->purchaseOrder)
                <div>
                    <p class="text-xs text-gray-400">Purchase Order</p>
                    <p class="font-mono text-xs font-medium text-indigo-600">{{ $invoice->purchaseOrder->po_number }}</p>
                </div>
            @endif
            @if ($invoice->goodsReceivedNote)
                <div>
                    <p class="text-xs text-gray-400">GRN</p>
                    <p class="font-mono text-xs font-medium text-indigo-600">{{ $invoice->goodsReceivedNote->grn_number }}</p>
                </div>
            @endif
            @if ($invoice->stockTransferOrder)
                <div>
                    <p class="text-xs text-gray-400">Stock Transfer</p>
                    <p class="font-mono text-xs font-medium text-indigo-600">{{ $invoice->stockTransferOrder->sto_number }}</p>
                </div>
            @endif
            <div>
                <p class="text-xs text-gray-400">Created By</p>
                <p class="font-medium text-gray-700">{{ $invoice->createdBy?->name ?? '—' }}</p>
            </div>
        </div>
    </div>

    {{-- Uploaded File --}}
    @if ($invoice->original_file_path)
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 mb-4">
            <p class="text-xs text-gray-400 mb-2">Uploaded Invoice</p>
            @if (str_contains($invoice->original_file_path, '.pdf'))
                <a href="{{ asset('storage/' . $invoice->original_file_path) }}" target="_blank"
                   class="text-sm text-indigo-600 hover:underline inline-flex items-center gap-1">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                    View PDF
                </a>
            @else
                <img src="{{ asset('storage/' . $invoice->original_file_path) }}" alt="Invoice scan"
                     class="max-w-md rounded-lg border border-gray-200" />
            @endif
        </div>
    @endif

    {{-- Line Items --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden mb-4">
        <table class="min-w-full divide-y divide-gray-100 text-sm">
            <thead class="bg-gray-50 text-gray-500 uppercase text-xs tracking-wider">
                <tr>
                    <th class="px-4 py-3 text-left w-10">#</th>
                    <th class="px-4 py-3 text-left">Item</th>
                    <th class="px-4 py-3 text-left">Description</th>
                    <th class="px-4 py-3 text-center">Qty</th>
                    <th class="px-4 py-3 text-center">UOM</th>
                    <th class="px-4 py-3 text-right">Unit Price</th>
                    <th class="px-4 py-3 text-right">Total</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @foreach ($invoice->lines as $i => $line)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-gray-400">{{ $i + 1 }}</td>
                        <td class="px-4 py-3 font-medium text-gray-700">{{ $line->ingredient?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-gray-500">{{ $line->description ?? '' }}</td>
                        <td class="px-4 py-3 text-center tabular-nums">{{ floatval($line->quantity) }}</td>
                        <td class="px-4 py-3 text-center text-gray-500">{{ $line->uom?->abbreviation ?? '' }}</td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ number_format($line->unit_price, 2) }}</td>
                        <td class="px-4 py-3 text-right tabular-nums font-medium">{{ number_format($line->total_price, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- Totals --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <div class="max-w-xs ml-auto space-y-2 text-sm">
            <div class="flex justify-between">
                <span class="text-gray-500">Subtotal</span>
                <span class="tabular-nums">{{ number_format($invoice->subtotal, 2) }}</span>
            </div>
            @if ($invoice->taxRate || floatval($invoice->tax_amount) > 0)
                <div class="flex justify-between">
                    <span class="text-gray-500">Tax{{ $invoice->taxRate ? ' (' . $invoice->taxRate->name . ')' : '' }}</span>
                    <span class="tabular-nums">{{ number_format($invoice->tax_amount, 2) }}</span>
                </div>
            @endif
            @if (floatval($invoice->delivery_charges) > 0)
                <div class="flex justify-between">
                    <span class="text-gray-500">Delivery Charges</span>
                    <span class="tabular-nums">{{ number_format($invoice->delivery_charges, 2) }}</span>
                </div>
            @endif
            <div class="flex justify-between pt-2 border-t border-gray-200 font-semibold text-base">
                <span>Total</span>
                <span class="tabular-nums">{{ number_format($invoice->total_amount, 2) }}</span>
            </div>
            @if (floatval($invoice->credit_applied) > 0)
                <div class="flex justify-between text-green-600">
                    <span>Credit Applied</span>
                    <span class="tabular-nums">-{{ number_format($invoice->credit_applied, 2) }}</span>
                </div>
                <div class="flex justify-between font-semibold">
                    <span>Balance Due</span>
                    <span class="tabular-nums">{{ number_format($invoice->balance_due, 2) }}</span>
                </div>
            @endif
        </div>

        @if ($invoice->notes)
            <div class="mt-6 pt-4 border-t border-gray-100">
                <p class="text-xs text-gray-400 mb-1">Notes</p>
                <p class="text-sm text-gray-600">{{ $invoice->notes }}</p>
            </div>
        @endif
    </div>
</div>
