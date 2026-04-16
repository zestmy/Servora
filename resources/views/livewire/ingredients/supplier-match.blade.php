<div>
    @if (session()->has('error'))
        <div wire:key="flash-{{ microtime(true) }}" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)"
             class="mb-4 px-4 py-3 bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg">
            {{ session('error') }}
        </div>
    @endif

    {{-- Header --}}
    <div class="flex items-center gap-3 mb-6">
        <a href="{{ route('ingredients.index') }}" class="text-gray-400 hover:text-gray-600 transition flex-shrink-0">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
            </svg>
        </a>
        <div class="flex-1">
            <p class="text-xs text-gray-400">
                <a href="{{ route('ingredients.index') }}" class="hover:underline">Ingredients</a> / Supplier Product Match
            </p>
            <h2 class="text-lg font-semibold text-gray-800 mt-0.5">Supplier Product Match</h2>
        </div>
    </div>

    {{-- Step indicator --}}
    <div class="mb-6 flex items-center gap-2 text-xs text-gray-400">
        <span class="{{ in_array($step, ['upload','preview','done']) ? 'text-indigo-600 font-semibold' : '' }}">1. Upload</span>
        <span>→</span>
        <span class="{{ in_array($step, ['preview','done']) ? 'text-indigo-600 font-semibold' : '' }}">2. Review & Match</span>
        <span>→</span>
        <span class="{{ $step === 'done' ? 'text-indigo-600 font-semibold' : '' }}">3. Import</span>
    </div>

    {{-- ── STEP 1: Upload ───────────────────────────────────────── --}}
    @if ($step === 'upload')

        <div class="mb-6 px-4 py-4 bg-blue-50 border border-blue-200 text-blue-800 text-sm rounded-xl space-y-2">
            <p class="font-semibold">How it works</p>
            <ol class="list-decimal list-inside space-y-1 text-blue-700">
                <li>Select the supplier and upload their invoice, quotation, or product list (PDF, image, or spreadsheet).</li>
                <li>AI extracts items and matches them against your existing ingredients.</li>
                <li>Review matches — link existing ingredients or create new ones. Linked suppliers appear as additional suppliers.</li>
            </ol>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 space-y-5">
            {{-- Supplier selector --}}
            <div>
                <x-input-label for="sm_supplier" value="Supplier *" />
                <select id="sm_supplier" wire:model="supplierId"
                        class="mt-1 block w-full sm:max-w-md rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">— Select Supplier —</option>
                    @foreach ($suppliers as $s)
                        <option value="{{ $s->id }}">{{ $s->name }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('supplierId')" class="mt-1" />
            </div>

            {{-- File upload --}}
            <div>
                <x-input-label value="Upload Document" />
                <div x-data="{ dragging: false }"
                     @dragover.prevent="dragging = true"
                     @dragleave.prevent="dragging = false"
                     @drop.prevent="dragging = false; $refs.fileInput.files = $event.dataTransfer.files; $refs.fileInput.dispatchEvent(new Event('change'))"
                     :class="dragging ? 'border-indigo-400 bg-indigo-50' : 'border-gray-300 bg-gray-50'"
                     class="mt-1 border-2 border-dashed rounded-xl p-8 text-center transition cursor-pointer"
                     @click="$refs.fileInput.click()">

                    <input type="file" x-ref="fileInput" wire:model="file" accept=".csv,.xlsx,.txt,.pdf,.jpg,.jpeg,.png,.webp" class="hidden" />

                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 mx-auto text-gray-300 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 13h6m-3-3v6m5 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>

                    @if ($file)
                        <p class="text-sm font-medium text-indigo-700">{{ $file->getClientOriginalName() }}</p>
                        <p class="text-xs text-gray-400 mt-0.5">{{ number_format($file->getSize() / 1024, 1) }} KB</p>
                    @else
                        <p class="text-sm font-medium text-gray-600">Click to browse or drag & drop</p>
                        <p class="text-xs text-gray-400 mt-1">PDF, image (JPG/PNG), CSV, or Excel — max 10 MB</p>
                    @endif
                </div>
                <x-input-error :messages="$errors->get('file')" class="mt-1" />
            </div>

            @if ($file && $supplierId)
                <div class="flex justify-end">
                    <button wire:click="processUpload" wire:loading.attr="disabled"
                            class="px-5 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition disabled:opacity-50">
                        <span wire:loading.remove wire:target="processUpload">Extract & Match →</span>
                        <span wire:loading wire:target="processUpload">Extracting with AI…</span>
                    </button>
                </div>
            @endif
        </div>

    {{-- ── STEP 2: Preview ─────────────────────────────────────── --}}
    @elseif ($step === 'preview')

        {{-- Summary --}}
        <div class="mb-4 px-4 py-3 bg-white rounded-xl shadow-sm border border-gray-100">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4 text-sm">
                    <span class="text-gray-500">Supplier: <strong class="text-gray-800">{{ $supplierName }}</strong></span>
                    <span class="text-gray-500">Items: <strong>{{ $totalItems }}</strong></span>
                    <span class="text-green-600">Matched: <strong>{{ $matchedCount }}</strong></span>
                    <span class="text-indigo-600">New: <strong>{{ $newCount }}</strong></span>
                </div>
            </div>
        </div>

        {{-- Legend --}}
        <div class="mb-4 flex flex-wrap gap-3 text-xs">
            <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-green-500"></span> Matched — will add supplier link</span>
            <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-indigo-500"></span> New — will create ingredient + link</span>
            <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-gray-400"></span> Already linked / Skip</span>
        </div>

        {{-- Items table --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden mb-6">
            <table class="min-w-full text-xs">
                <thead class="bg-gray-50 text-gray-500 uppercase text-[10px] tracking-wider">
                    <tr>
                        <th class="px-4 py-2 text-left w-8">#</th>
                        <th class="px-4 py-2 text-left">Item (from document)</th>
                        <th class="px-4 py-2 text-left">Matched Ingredient</th>
                        <th class="px-4 py-2 text-left w-20">SKU</th>
                        <th class="px-4 py-2 text-right w-24">Price</th>
                        <th class="px-4 py-2 text-left w-20">UOM</th>
                        <th class="px-4 py-2 text-center w-28">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @foreach ($items as $idx => $item)
                        @php
                            $bgClass = match($item['action']) {
                                'link'   => 'bg-green-50/50',
                                'create' => 'bg-indigo-50/50',
                                default  => 'bg-gray-50/30',
                            };
                        @endphp
                        <tr class="{{ $bgClass }}">
                            <td class="px-4 py-2.5 text-gray-400">{{ $idx + 1 }}</td>
                            <td class="px-4 py-2.5">
                                <div class="font-medium text-gray-800">{{ $item['name'] }}</div>
                                @if ($item['category'])
                                    <span class="text-[10px] text-gray-400">{{ $item['category'] }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-2.5">
                                @if ($item['ingredient_id'])
                                    <div class="flex items-center gap-1.5">
                                        <span class="w-2 h-2 rounded-full flex-shrink-0 {{ $item['already_linked'] ? 'bg-gray-400' : 'bg-green-500' }}"></span>
                                        <span class="{{ $item['already_linked'] ? 'text-gray-500' : 'text-green-700' }}">{{ $item['matched_name'] }}</span>
                                        @if ($item['confidence'] < 100)
                                            <span class="text-[10px] text-amber-500">{{ $item['confidence'] }}%</span>
                                        @endif
                                        @if ($item['already_linked'])
                                            <span class="text-[10px] text-gray-400 italic">already linked</span>
                                        @endif
                                    </div>
                                    <select wire:change="fixMatch({{ $idx }}, $event.target.value)"
                                            class="mt-1 text-[11px] border-gray-200 rounded py-0.5 px-1 max-w-[200px]">
                                        <option value="">Change match…</option>
                                        @foreach ($ingredients as $ing)
                                            <option value="{{ $ing->id }}">{{ $ing->name }}</option>
                                        @endforeach
                                    </select>
                                @else
                                    <div class="flex items-center gap-1.5">
                                        <span class="w-2 h-2 rounded-full bg-indigo-500 flex-shrink-0"></span>
                                        <span class="text-indigo-600 text-[10px] font-semibold">NEW INGREDIENT</span>
                                    </div>
                                    <select wire:change="fixMatch({{ $idx }}, $event.target.value)"
                                            class="mt-1 text-[11px] border-gray-200 rounded py-0.5 px-1 max-w-[200px]">
                                        <option value="">Match to existing…</option>
                                        @foreach ($ingredients as $ing)
                                            <option value="{{ $ing->id }}">{{ $ing->name }}</option>
                                        @endforeach
                                    </select>
                                @endif
                            </td>
                            <td class="px-4 py-2.5 text-gray-500 font-mono">{{ $item['code'] ?? '—' }}</td>
                            <td class="px-4 py-2.5 text-right tabular-nums font-medium text-gray-700">
                                @if ($item['price'] > 0)
                                    {{ number_format($item['price'], 2) }}
                                @else
                                    <span class="text-gray-300">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-2.5">
                                @if ($item['uom_id'])
                                    <span class="text-gray-600">{{ $item['uom_raw'] }}</span>
                                @else
                                    <select wire:change="fixUom({{ $idx }}, $event.target.value)"
                                            class="text-[11px] border-gray-200 rounded py-0.5 px-1">
                                        <option value="">{{ $item['uom_raw'] ?: '?' }}</option>
                                        @foreach ($uoms as $uom)
                                            <option value="{{ $uom->id }}">{{ $uom->abbreviation }}</option>
                                        @endforeach
                                    </select>
                                @endif
                            </td>
                            <td class="px-4 py-2.5 text-center">
                                <select wire:change="setAction({{ $idx }}, $event.target.value)"
                                        class="text-[11px] rounded border-gray-200 py-1 px-2 font-medium
                                        {{ $item['action'] === 'link' ? 'text-green-700 bg-green-50' : ($item['action'] === 'create' ? 'text-indigo-700 bg-indigo-50' : 'text-gray-500 bg-gray-50') }}">
                                    @if ($item['ingredient_id'])
                                        <option value="link" {{ $item['action'] === 'link' ? 'selected' : '' }}>Link</option>
                                    @endif
                                    <option value="create" {{ $item['action'] === 'create' ? 'selected' : '' }}>Create New</option>
                                    <option value="skip" {{ $item['action'] === 'skip' ? 'selected' : '' }}>Skip</option>
                                </select>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Action bar --}}
        <div class="flex items-center justify-between">
            <button wire:click="restart" class="px-4 py-2 text-sm text-gray-600 border border-gray-200 rounded-lg hover:bg-gray-50 transition">
                ← Start Over
            </button>
            <button wire:click="import" wire:loading.attr="disabled"
                    class="px-6 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition disabled:opacity-50">
                <span wire:loading.remove wire:target="import">
                    Import {{ collect($items)->where('action', '!=', 'skip')->count() }} Items
                </span>
                <span wire:loading wire:target="import">Importing…</span>
            </button>
        </div>

    {{-- ── STEP 3: Done ─────────────────────────────────────── --}}
    @elseif ($step === 'done')

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-8 text-center">
            <div class="inline-flex items-center justify-center w-16 h-16 bg-green-100 rounded-full mb-4">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                </svg>
            </div>
            <h3 class="text-lg font-bold text-gray-800 mb-2">Import Complete</h3>
            <p class="text-sm text-gray-500 mb-4">Supplier: <strong>{{ $supplierName }}</strong></p>
            <div class="flex items-center justify-center gap-6 text-sm mb-6">
                @if ($linkedCount > 0)
                    <div class="text-center">
                        <p class="text-2xl font-bold text-green-600">{{ $linkedCount }}</p>
                        <p class="text-xs text-gray-500">Supplier Links Added</p>
                    </div>
                @endif
                @if ($createdCount > 0)
                    <div class="text-center">
                        <p class="text-2xl font-bold text-indigo-600">{{ $createdCount }}</p>
                        <p class="text-xs text-gray-500">New Ingredients</p>
                    </div>
                @endif
                @if ($skippedCount > 0)
                    <div class="text-center">
                        <p class="text-2xl font-bold text-gray-400">{{ $skippedCount }}</p>
                        <p class="text-xs text-gray-500">Skipped</p>
                    </div>
                @endif
            </div>
            <div class="flex items-center justify-center gap-3">
                <a href="{{ route('ingredients.index') }}"
                   class="px-5 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
                    View Ingredients
                </a>
                <button wire:click="restart" class="px-5 py-2 text-sm text-gray-600 border border-gray-200 rounded-lg hover:bg-gray-50 transition">
                    Match Another Document
                </button>
            </div>
        </div>

    @endif
</div>
