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
                / AI Receive
            </p>
            <h2 class="text-lg font-semibold text-gray-700">AI Invoice Receiving</h2>
        </div>
    </div>

    {{-- ============ STEP 1: UPLOAD ============ --}}
    @if ($step === 1)
        <div class="max-w-xl mx-auto">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-8">
                <div class="text-center mb-6">
                    <div class="w-16 h-16 mx-auto bg-indigo-50 rounded-full flex items-center justify-center mb-4">
                        <svg class="w-8 h-8 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-800">Upload Supplier Invoice</h3>
                    <p class="text-sm text-gray-500 mt-1">Upload an invoice image or PDF. AI will extract and match the data automatically.</p>
                </div>

                <form wire:submit="upload">
                    {{-- Drag & drop zone --}}
                    <div x-data="{ dragover: false }"
                         x-on:dragover.prevent="dragover = true"
                         x-on:dragleave="dragover = false"
                         x-on:drop.prevent="dragover = false; $refs.fileInput.files = $event.dataTransfer.files; $refs.fileInput.dispatchEvent(new Event('change'))"
                         :class="dragover ? 'border-indigo-400 bg-indigo-50' : 'border-gray-300 bg-gray-50'"
                         class="border-2 border-dashed rounded-xl p-8 text-center transition cursor-pointer mb-4"
                         x-on:click="$refs.fileInput.click()">

                        <input type="file" wire:model="invoiceFile" x-ref="fileInput" class="hidden"
                               accept="image/jpeg,image/png,image/webp,application/pdf" />

                        @if ($invoiceFile)
                            <div class="text-sm text-gray-700">
                                <svg class="w-8 h-8 mx-auto text-green-500 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                <p class="font-medium">{{ $invoiceFile->getClientOriginalName() }}</p>
                                <p class="text-xs text-gray-400 mt-1">{{ number_format($invoiceFile->getSize() / 1024, 0) }} KB</p>
                            </div>
                        @else
                            <svg class="w-10 h-10 mx-auto text-gray-400 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                            <p class="text-sm text-gray-600">Drop file here or click to browse</p>
                            <p class="text-xs text-gray-400 mt-1">JPG, PNG, WebP, or PDF — max 10 MB</p>
                        @endif
                    </div>

                    @error('invoiceFile')
                        <p class="text-red-500 text-xs mb-3">{{ $message }}</p>
                    @enderror

                    @if ($errorMessage)
                        <div class="mb-4 px-4 py-3 bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg">
                            {{ $errorMessage }}
                        </div>
                    @endif

                    <button type="submit" @disabled(!$invoiceFile || $processing)
                            class="w-full px-4 py-3 bg-indigo-600 text-white font-medium rounded-lg hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed transition flex items-center justify-center gap-2">
                        @if ($processing)
                            <svg class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                            Scanning & Extracting...
                        @else
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                            Scan Invoice
                        @endif
                    </button>
                </form>
            </div>
        </div>
    @endif

    {{-- ============ STEP 3: REVIEW ============ --}}
    @if ($step === 3)
        <div class="grid grid-cols-1 lg:grid-cols-5 gap-4">
            {{-- LEFT: Extracted Data (60%) --}}
            <div class="lg:col-span-3 space-y-4">
                {{-- Exception Summary --}}
                @if (count($exceptions) > 0)
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
                        <div class="flex items-center gap-3">
                            @if ($errorCount > 0)
                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-red-100 text-red-700">
                                    <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clip-rule="evenodd"/></svg>
                                    {{ $errorCount }} {{ Str::plural('error', $errorCount) }}
                                </span>
                            @endif
                            @if ($warningCount > 0)
                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-amber-100 text-amber-700">
                                    <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 6a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 6zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/></svg>
                                    {{ $warningCount }} {{ Str::plural('warning', $warningCount) }}
                                </span>
                            @endif
                            <span class="text-xs text-gray-500">Review flagged items before approving</span>
                        </div>
                    </div>
                @endif

                {{-- Header Fields --}}
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                    <h3 class="text-sm font-semibold text-gray-700 mb-4">Invoice Details</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Supplier</label>
                            <select wire:model.live="selectedSupplierId" class="w-full rounded-lg border-gray-300 text-sm">
                                <option value="">-- Select Supplier --</option>
                                @foreach ($suppliers as $s)
                                    <option value="{{ $s->id }}">{{ $s->name }}</option>
                                @endforeach
                            </select>
                            @if ($supplierConfidence > 0 && $supplierConfidence < 1)
                                <p class="text-xs text-amber-600 mt-0.5">Match confidence: {{ round($supplierConfidence * 100) }}%</p>
                            @endif
                            @error('selectedSupplierId') <p class="text-red-500 text-xs mt-0.5">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Supplier Invoice #</label>
                            <input type="text" wire:model="supplierInvoiceNumber" class="w-full rounded-lg border-gray-300 text-sm" />
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Invoice Date</label>
                            <input type="date" wire:model="issuedDate" class="w-full rounded-lg border-gray-300 text-sm" />
                            @error('issuedDate') <p class="text-red-500 text-xs mt-0.5">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Due Date</label>
                            <input type="date" wire:model="dueDate" class="w-full rounded-lg border-gray-300 text-sm" />
                        </div>
                    </div>
                </div>

                {{-- Line Items --}}
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                    <div class="px-5 py-3 border-b border-gray-100">
                        <h3 class="text-sm font-semibold text-gray-700">Line Items</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-100 text-sm">
                            <thead class="bg-gray-50 text-gray-500 uppercase text-xs tracking-wider">
                                <tr>
                                    <th class="px-3 py-2 text-left w-8">#</th>
                                    <th class="px-3 py-2 text-left">Matched Ingredient</th>
                                    <th class="px-3 py-2 text-left">Description</th>
                                    <th class="px-3 py-2 text-center w-20">Qty</th>
                                    <th class="px-3 py-2 text-center w-24">UOM</th>
                                    <th class="px-3 py-2 text-right w-24">Unit Price</th>
                                    <th class="px-3 py-2 text-right w-24">Total</th>
                                    <th class="px-3 py-2 text-center w-16">PO Price</th>
                                    <th class="px-3 py-2 text-center w-8"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">
                                @foreach ($lines as $i => $line)
                                    @php
                                        $lineExceptions = collect($exceptions)->where('line_index', $i);
                                        $hasError = $lineExceptions->where('severity', 'error')->isNotEmpty();
                                        $hasWarning = $lineExceptions->where('severity', 'warning')->isNotEmpty();
                                        $rowBg = $hasError ? 'bg-red-50' : ($hasWarning ? 'bg-amber-50' : ($line['match_confidence'] >= 0.5 ? 'bg-green-50/30' : 'bg-gray-50/50'));
                                    @endphp
                                    <tr class="{{ $rowBg }}">
                                        <td class="px-3 py-2 text-gray-400">
                                            {{ $i + 1 }}
                                            @if ($hasError)
                                                <span class="inline-block w-2 h-2 rounded-full bg-red-500 ml-0.5" title="{{ $lineExceptions->where('severity', 'error')->pluck('message')->join('; ') }}"></span>
                                            @elseif ($hasWarning)
                                                <span class="inline-block w-2 h-2 rounded-full bg-amber-500 ml-0.5" title="{{ $lineExceptions->where('severity', 'warning')->pluck('message')->join('; ') }}"></span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-2">
                                            <select wire:model="lines.{{ $i }}.ingredient_id" class="w-full rounded border-gray-300 text-xs">
                                                <option value="">-- Unmatched --</option>
                                                @foreach ($ingredients as $ing)
                                                    <option value="{{ $ing->id }}">{{ $ing->name }}</option>
                                                @endforeach
                                            </select>
                                            @if ($line['match_confidence'] > 0 && $line['match_confidence'] < 0.5)
                                                <p class="text-xs text-amber-500 mt-0.5">Low match ({{ round($line['match_confidence'] * 100) }}%)</p>
                                            @endif
                                        </td>
                                        <td class="px-3 py-2 text-xs text-gray-600 max-w-48 truncate" title="{{ $line['description'] }}">
                                            {{ $line['description'] }}
                                        </td>
                                        <td class="px-3 py-2">
                                            <input type="number" wire:model.lazy="lines.{{ $i }}.quantity"
                                                   wire:change="recalcLine({{ $i }})"
                                                   step="0.01" min="0" class="w-full rounded border-gray-300 text-xs text-center" />
                                        </td>
                                        <td class="px-3 py-2">
                                            <select wire:model="lines.{{ $i }}.uom_id" class="w-full rounded border-gray-300 text-xs">
                                                <option value="">—</option>
                                                @foreach ($uoms as $uom)
                                                    <option value="{{ $uom->id }}">{{ $uom->abbreviation }}</option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td class="px-3 py-2">
                                            <input type="number" wire:model.lazy="lines.{{ $i }}.unit_price"
                                                   wire:change="recalcLine({{ $i }})"
                                                   step="0.01" min="0" class="w-full rounded border-gray-300 text-xs text-right" />
                                        </td>
                                        <td class="px-3 py-2 text-right tabular-nums text-xs font-medium">
                                            {{ number_format($line['total_price'], 2) }}
                                        </td>
                                        <td class="px-3 py-2 text-center">
                                            @if ($line['po_unit_price'] !== null)
                                                <span class="text-xs tabular-nums text-gray-400">{{ number_format($line['po_unit_price'], 2) }}</span>
                                            @else
                                                <span class="text-gray-300">—</span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-2 text-center">
                                            <button wire:click="removeLine({{ $i }})" wire:confirm="Remove this line?"
                                                    class="text-gray-400 hover:text-red-500 transition" title="Remove">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- Totals --}}
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                    <div class="max-w-xs ml-auto space-y-3">
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-500">Subtotal</span>
                            <span class="tabular-nums font-medium">{{ number_format($subtotal, 2) }}</span>
                        </div>
                        <div class="flex items-center justify-between text-sm gap-3">
                            <span class="text-gray-500">Tax</span>
                            <input type="number" wire:model.lazy="taxAmount" step="0.01" min="0"
                                   class="w-28 rounded-lg border-gray-300 text-xs text-right" />
                        </div>
                        <div class="flex items-center justify-between text-sm gap-3">
                            <span class="text-gray-500">Delivery</span>
                            <input type="number" wire:model.lazy="deliveryCharges" step="0.01" min="0"
                                   class="w-28 rounded-lg border-gray-300 text-xs text-right" />
                        </div>
                        <div class="flex justify-between pt-2 border-t border-gray-200 font-semibold">
                            <span>Total</span>
                            <span class="tabular-nums">{{ number_format($subtotal + floatval($taxAmount) + floatval($deliveryCharges), 2) }}</span>
                        </div>
                    </div>
                </div>

                {{-- Notes --}}
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                    <label class="block text-xs text-gray-500 mb-1">Notes</label>
                    <textarea wire:model="notes" rows="2" class="w-full rounded-lg border-gray-300 text-sm"></textarea>
                </div>

                {{-- Actions --}}
                <div class="flex items-center gap-3">
                    <button wire:click="approve" wire:confirm="Create invoice from this scanned data?"
                            class="px-6 py-2.5 bg-green-600 text-white font-medium rounded-lg hover:bg-green-700 transition inline-flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        Approve & Create Invoice
                    </button>
                    <button wire:click="reject" wire:confirm="Discard this scan?"
                            class="px-4 py-2.5 text-gray-600 bg-white border border-gray-300 font-medium rounded-lg hover:bg-gray-50 transition">
                        Reject
                    </button>
                    <a href="{{ route('purchasing.invoices.index') }}"
                       class="px-4 py-2.5 text-gray-500 text-sm hover:underline">Cancel</a>
                </div>
            </div>

            {{-- RIGHT: Reference Panel (40%) --}}
            <div class="lg:col-span-2 space-y-4">
                {{-- Uploaded Invoice Preview --}}
                @if ($uploadedFilePath)
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
                        <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">Uploaded Invoice</h4>
                        @if (str_contains($uploadedFilePath, '.pdf'))
                            <a href="{{ asset('storage/' . $uploadedFilePath) }}" target="_blank"
                               class="text-sm text-indigo-600 hover:underline inline-flex items-center gap-1">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                                Open PDF in new tab
                            </a>
                        @else
                            <img src="{{ asset('storage/' . $uploadedFilePath) }}" alt="Uploaded invoice"
                                 class="w-full rounded-lg border border-gray-200 cursor-pointer"
                                 x-on:click="window.open('{{ asset('storage/' . $uploadedFilePath) }}', '_blank')" />
                        @endif
                    </div>
                @endif

                {{-- Matched PO --}}
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
                    <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">Matched Purchase Order</h4>
                    @if ($selectedPoId)
                        <div class="space-y-2 mb-3">
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-500">PO Number</span>
                                <span class="font-mono text-xs font-medium text-indigo-600">{{ $matchedPoNumber }}</span>
                            </div>
                            @if ($poConfidence > 0)
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-500">Confidence</span>
                                    <span class="text-xs {{ $poConfidence >= 0.7 ? 'text-green-600' : ($poConfidence >= 0.4 ? 'text-amber-600' : 'text-red-600') }}">
                                        {{ round($poConfidence * 100) }}%
                                    </span>
                                </div>
                            @endif
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Change PO</label>
                            <select wire:model.live="selectedPoId" class="w-full rounded-lg border-gray-300 text-xs">
                                <option value="">-- No PO --</option>
                                @foreach ($recentPos as $rpo)
                                    <option value="{{ $rpo->id }}">{{ $rpo->po_number }} ({{ $rpo->order_date?->format('d M') }} — RM{{ number_format($rpo->total_amount, 2) }})</option>
                                @endforeach
                            </select>
                        </div>
                    @else
                        <p class="text-sm text-gray-400">No matching PO found</p>
                        @if (count($recentPos) > 0)
                            <div class="mt-2">
                                <label class="block text-xs text-gray-500 mb-1">Manually select PO</label>
                                <select wire:model.live="selectedPoId" class="w-full rounded-lg border-gray-300 text-xs">
                                    <option value="">-- No PO --</option>
                                    @foreach ($recentPos as $rpo)
                                        <option value="{{ $rpo->id }}">{{ $rpo->po_number }} ({{ $rpo->order_date?->format('d M') }})</option>
                                    @endforeach
                                </select>
                            </div>
                        @endif
                    @endif
                </div>

                {{-- Matched GRN --}}
                @if ($selectedGrnId)
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
                        <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Matched GRN</h4>
                        <span class="font-mono text-xs font-medium text-indigo-600">{{ $matchedGrnNumber }}</span>
                    </div>
                @endif

                {{-- Exceptions Detail --}}
                @if (count($exceptions) > 0)
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
                        <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">Exceptions</h4>
                        <div class="space-y-2 max-h-80 overflow-y-auto">
                            @foreach ($exceptions as $ex)
                                @php
                                    $exColor = match($ex['severity']) {
                                        'error'   => 'border-l-red-500 bg-red-50 text-red-700',
                                        'warning' => 'border-l-amber-500 bg-amber-50 text-amber-700',
                                        default   => 'border-l-blue-500 bg-blue-50 text-blue-700',
                                    };
                                @endphp
                                <div class="border-l-4 rounded-r-lg p-2.5 text-xs {{ $exColor }}">
                                    <span class="font-medium">{{ str_replace('_', ' ', ucfirst($ex['type'])) }}</span>
                                    @if ($ex['line_index'] !== null)
                                        <span class="text-gray-400 ml-1">(Line {{ $ex['line_index'] + 1 }})</span>
                                    @endif
                                    <p class="mt-0.5 opacity-80">{{ $ex['message'] }}</p>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </div>
    @endif
</div>
