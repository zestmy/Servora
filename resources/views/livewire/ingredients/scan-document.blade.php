<div>
    {{-- Header --}}
    <div class="flex items-center gap-3 mb-6">
        <a href="{{ route('ingredients.index') }}" class="text-gray-400 hover:text-gray-600 transition flex-shrink-0">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
            </svg>
        </a>
        <div class="flex-1">
            <p class="text-xs text-gray-400">Price Watcher / Scan Documents</p>
            <h2 class="text-lg font-semibold text-gray-800 mt-0.5">Scan Documents</h2>
            <p class="text-xs text-gray-500 mt-0.5">Upload a supplier invoice / quotation / price list (PDF or photo). The AI reads the supplier name, date, and every line item — you'll review and match them in the next step.</p>
        </div>
        @if ($pendingReviewCount > 0)
            <a href="{{ route('ingredients.review-documents') }}"
               class="px-3 py-2 text-xs font-medium text-indigo-700 bg-indigo-50 border border-indigo-200 rounded-lg hover:bg-indigo-100 transition whitespace-nowrap">
                {{ $pendingReviewCount }} pending review →
            </a>
        @endif
    </div>

    {{-- Upload form --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 space-y-5">
        <div>
            <x-input-label value="Supplier (optional)" />
            <select wire:model="supplierId"
                    class="mt-1 block w-full sm:max-w-md rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">— Auto-detect from document —</option>
                @foreach ($suppliers as $s)
                    <option value="{{ $s->id }}">{{ $s->name }}</option>
                @endforeach
            </select>
            <p class="text-[11px] text-gray-400 mt-1">Leave blank to let the AI detect the supplier from the document. You can still confirm or override on the Review page.</p>
        </div>

        {{-- File picker + camera --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <label class="block border-2 border-dashed border-gray-300 bg-gray-50 rounded-xl p-6 text-center cursor-pointer hover:bg-gray-100 transition">
                <input type="file" wire:model="file" accept=".pdf,.jpg,.jpeg,.png,.webp" class="hidden" />
                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 mx-auto text-gray-400 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 13h6m-3-3v6m5 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <p class="text-sm font-medium text-gray-700">Choose File</p>
                <p class="text-[11px] text-gray-400 mt-1">PDF or image, up to 10 MB</p>
            </label>

            <label class="block border-2 border-dashed border-indigo-200 bg-indigo-50/40 rounded-xl p-6 text-center cursor-pointer hover:bg-indigo-50 transition">
                {{-- capture=environment opens the rear camera on mobile --}}
                <input type="file" wire:model="file" accept="image/*" capture="environment" class="hidden" />
                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 mx-auto text-indigo-500 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
                <p class="text-sm font-medium text-indigo-700">Take Photo</p>
                <p class="text-[11px] text-indigo-500 mt-1">Opens your camera</p>
            </label>
        </div>

        @if ($file)
            <div class="px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg flex items-center justify-between text-xs">
                <div class="min-w-0">
                    <p class="font-medium text-gray-700 truncate">{{ $file->getClientOriginalName() }}</p>
                    <p class="text-gray-400">{{ number_format($file->getSize() / 1024, 1) }} KB</p>
                </div>
                <button type="button" wire:click="$set('file', null)" class="text-gray-400 hover:text-gray-600 ml-3">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
        @endif

        <x-input-error :messages="$errors->get('file')" class="mt-1" />

        <div class="flex items-center justify-end">
            <button wire:click="processUpload"
                    wire:loading.attr="disabled"
                    @disabled(! $file)
                    class="px-5 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition disabled:opacity-50 disabled:cursor-not-allowed">
                <span wire:loading.remove wire:target="processUpload">Scan Document →</span>
                <span wire:loading wire:target="processUpload">Reading with AI…</span>
            </button>
        </div>
    </div>

    {{-- Last-scan result --}}
    @if ($lastScan)
        <div class="mt-6 rounded-xl shadow-sm border
                    {{ $lastScan->status === 'extracted' ? 'border-green-200 bg-green-50' : ($lastScan->status === 'failed' ? 'border-red-200 bg-red-50' : 'border-amber-200 bg-amber-50') }}
                    p-5">
            <div class="flex items-start gap-3">
                @if ($lastScan->status === 'extracted')
                    <div class="flex-shrink-0 w-9 h-9 bg-green-100 rounded-full flex items-center justify-center">
                        <svg class="h-5 w-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                    </div>
                @elseif ($lastScan->status === 'failed')
                    <div class="flex-shrink-0 w-9 h-9 bg-red-100 rounded-full flex items-center justify-center">
                        <svg class="h-5 w-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
                    </div>
                @else
                    <div class="flex-shrink-0 w-9 h-9 bg-amber-100 rounded-full flex items-center justify-center">
                        <svg class="h-5 w-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                @endif
                <div class="flex-1 min-w-0">
                    @if ($lastScan->status === 'extracted')
                        <h3 class="text-sm font-semibold text-green-900">Scanned successfully</h3>
                        <p class="text-sm text-green-800 mt-1">
                            <strong>{{ $lastScan->original_filename }}</strong>
                            — {{ count($lastScan->extracted_items ?? []) }} line item(s) extracted.
                        </p>
                        <div class="mt-2 text-xs text-green-800 space-y-0.5">
                            @if ($lastScan->supplier_name_detected)
                                <div>Supplier detected: <strong>{{ $lastScan->supplier_name_detected }}</strong>
                                    @if ($lastScan->supplier_id)
                                        — matched to your existing supplier.
                                    @else
                                        — not in your supplier list yet (you'll create it on the Review page).
                                    @endif
                                </div>
                            @endif
                            @if ($lastScan->document_date_detected)
                                <div>Document date: <strong>{{ $lastScan->document_date_detected->format('d M Y') }}</strong></div>
                            @endif
                        </div>
                    @elseif ($lastScan->status === 'failed')
                        <h3 class="text-sm font-semibold text-red-900">Scan rejected</h3>
                        <p class="text-sm text-red-800 mt-1">
                            <strong>{{ $lastScan->original_filename }}</strong>
                        </p>
                        <p class="text-sm text-red-700 mt-1">{{ $lastScan->error_message }}</p>
                    @else
                        <h3 class="text-sm font-semibold text-amber-900">Processing…</h3>
                    @endif

                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        @if ($lastScan->status === 'extracted')
                            <a href="{{ route('ingredients.review-documents.show', $lastScan->id) }}"
                               class="px-3 py-1.5 text-xs font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 transition">
                                Review & Import →
                            </a>
                        @endif
                        <button wire:click="scanAnother"
                                class="px-3 py-1.5 text-xs font-medium text-gray-700 bg-white border border-gray-200 rounded-lg hover:bg-gray-50 transition">
                            Scan another
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
