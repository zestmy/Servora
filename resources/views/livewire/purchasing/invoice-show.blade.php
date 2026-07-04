<div>
    @if (session()->has('success'))
        <div wire:key="flash-{{ microtime(true) }}" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)"
             class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-700 text-sm rounded-lg">
            {{ session('success') }}
        </div>
    @endif
    @if (session()->has('error'))
        <div wire:key="flash-err-{{ microtime(true) }}" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)"
             class="mb-4 px-4 py-3 bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg">
            {{ session('error') }}
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
            @if (in_array($invoice->status, ['issued', 'partial', 'overdue']))
                <button wire:click="openPaymentModal"
                        class="px-3 py-1.5 text-sm bg-green-600 text-white rounded-lg hover:bg-green-700 transition">
                    Record Payment
                </button>
            @endif
            @if ($invoice->status === 'issued')
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
            @endif
            @php($paidTotal = $invoice->payments->sum('amount'))
            @if ($paidTotal > 0)
                <div class="flex justify-between text-green-600">
                    <span>Paid</span>
                    <span class="tabular-nums">-{{ number_format($paidTotal, 2) }}</span>
                </div>
            @endif
            @if (! in_array($invoice->status, ['draft', 'cancelled']))
                <div class="flex justify-between font-semibold {{ $invoice->outstanding() > 0 ? 'text-amber-700' : 'text-green-700' }}">
                    <span>Balance Due</span>
                    <span class="tabular-nums">{{ number_format($invoice->outstanding(), 2) }}</span>
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

    {{-- Payment History --}}
    @if (! in_array($invoice->status, ['draft', 'cancelled']) || $invoice->payments->isNotEmpty())
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden mt-4">
            <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100">
                <h3 class="text-sm font-semibold text-gray-700">Payments</h3>
                @if (in_array($invoice->status, ['issued', 'partial', 'overdue']))
                    <button wire:click="openPaymentModal"
                            class="px-3 py-1.5 text-xs bg-green-600 text-white rounded-lg hover:bg-green-700 transition">
                        Record Payment
                    </button>
                @endif
            </div>
            @if ($invoice->payments->isEmpty())
                <p class="px-4 py-6 text-sm text-gray-400 text-center">No payments recorded yet.</p>
            @else
                <table class="min-w-full divide-y divide-gray-100 text-sm">
                    <thead class="bg-gray-50 text-gray-500 uppercase text-xs tracking-wider">
                        <tr>
                            <th class="px-4 py-2.5 text-left">Date</th>
                            <th class="px-4 py-2.5 text-right">Amount (RM)</th>
                            <th class="px-4 py-2.5 text-left">Method</th>
                            <th class="px-4 py-2.5 text-left">Reference</th>
                            <th class="px-4 py-2.5 text-left">Recorded By</th>
                            <th class="px-4 py-2.5 text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($invoice->payments->sortByDesc('payment_date')->values() as $payment)
                            <tr wire:key="pay-{{ $payment->id }}">
                                <td class="px-4 py-2.5 text-gray-600">{{ $payment->payment_date->format('d M Y') }}</td>
                                <td class="px-4 py-2.5 text-right tabular-nums font-medium text-gray-800">{{ number_format($payment->amount, 2) }}</td>
                                <td class="px-4 py-2.5 text-gray-600">{{ $payment->methodLabel() }}</td>
                                <td class="px-4 py-2.5 text-gray-500 text-xs">{{ $payment->reference ?: '—' }}</td>
                                <td class="px-4 py-2.5 text-gray-500 text-xs">
                                    {{ $payment->recordedBy?->name ?? '—' }}
                                    @if ($payment->notes)
                                        <span class="block text-gray-400">{{ $payment->notes }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2.5 text-center">
                                    <button wire:click="deletePayment({{ $payment->id }})"
                                            wire:confirm="Remove this payment? The invoice balance will be restored."
                                            class="text-xs text-red-500 hover:text-red-700 transition">
                                        Remove
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    @endif

    {{-- Record Payment Modal --}}
    @if ($showPaymentModal)
        @teleport('body')
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40" wire:click.self="$set('showPaymentModal', false)">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-md mx-4 p-6">
                <h3 class="text-base font-semibold text-gray-800 mb-1">Record Payment</h3>
                <p class="text-xs text-gray-400 mb-4">
                    {{ $invoice->invoice_number }} — outstanding RM {{ number_format($invoice->outstanding(), 2) }}
                </p>

                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="pay_date" value="Payment Date *" />
                            <input id="pay_date" type="date" wire:model="pay_date" max="{{ now()->toDateString() }}"
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500" />
                            @error('pay_date') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <x-input-label for="pay_amount" value="Amount (RM) *" />
                            <input id="pay_amount" type="number" step="0.01" min="0.01" wire:model="pay_amount"
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500" />
                            @error('pay_amount') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>
                    <div>
                        <x-input-label for="pay_method" value="Payment Method *" />
                        <select id="pay_method" wire:model="pay_method"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @foreach (\App\Models\ProcurementInvoicePayment::METHODS as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('pay_method') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <x-input-label for="pay_reference" value="Reference" />
                        <input id="pay_reference" type="text" wire:model="pay_reference" placeholder="e.g. bank transaction no., cheque no."
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500" />
                    </div>
                    <div>
                        <x-input-label for="pay_notes" value="Notes" />
                        <textarea id="pay_notes" wire:model="pay_notes" rows="2"
                                  class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                    </div>
                </div>

                <div class="flex justify-end gap-2 mt-6">
                    <button wire:click="$set('showPaymentModal', false)"
                            class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800 transition">
                        Cancel
                    </button>
                    <button wire:click="recordPayment"
                            class="px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700 transition">
                        Save Payment
                    </button>
                </div>
            </div>
        </div>
        @endteleport
    @endif
</div>
