<div>
    @if (session()->has('success'))
        <div wire:key="flash-{{ microtime(true) }}" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)"
             class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-700 text-sm rounded-lg">
            {{ session('success') }}
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
                <a href="{{ route('ingredients.index') }}" class="hover:underline">Ingredients</a> / Import
            </p>
            <h2 class="text-lg font-semibold text-gray-800 mt-0.5">Bulk Import Ingredients</h2>
        </div>
    </div>

    {{-- Step indicator --}}
    <div class="mb-6 flex items-center gap-2 text-xs text-gray-400">
        <span class="{{ in_array($step, ['upload','mapping','preview','done']) ? 'text-indigo-600 font-semibold' : '' }}">1. Upload</span>
        <span>→</span>
        <span class="{{ in_array($step, ['mapping','preview','done']) ? 'text-indigo-600 font-semibold' : '' }}">2. Map Columns</span>
        <span>→</span>
        <span class="{{ in_array($step, ['preview','done']) ? 'text-indigo-600 font-semibold' : '' }}">3. Preview</span>
        <span>→</span>
        <span class="{{ $step === 'done' ? 'text-indigo-600 font-semibold' : '' }}">4. Import</span>
    </div>

    {{-- ── STEP 1: Upload ─────────────────────────────────────── --}}
    @if ($step === 'upload')

        {{-- Info banner --}}
        <div class="mb-6 px-4 py-4 bg-blue-50 border border-blue-200 text-blue-800 text-sm rounded-xl space-y-2">
            <p class="font-semibold">How to import</p>
            <ol class="list-decimal list-inside space-y-1 text-blue-700">
                <li>Download the sample template below and fill in your ingredient data.</li>
                <li>Upload any CSV, Excel (.xlsx), or PDF file — AI will automatically extract and map your data.</li>
                <li>AI will also detect prep items (sauces, stocks, marinades, etc.) and create them as placeholders.</li>
                <li>Review the column mapping, adjust if needed, then preview and confirm.</li>
            </ol>
            <div class="pt-1">
                <button wire:click="downloadTemplate"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-blue-700 border border-blue-300 rounded-lg hover:bg-blue-100 transition">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a2 2 0 002 2h12a2 2 0 002-2v-1M12 12v6m0 0l-3-3m3 3l3-3M12 4v4" />
                    </svg>
                    Download CSV Template
                </button>
            </div>
        </div>

        {{-- Column reference --}}
        <div class="mb-6 bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <h3 class="text-sm font-semibold text-gray-700 mb-3">Column Reference</h3>
            <p class="text-xs text-gray-500 mb-3">Your file doesn't need to use these exact column names — AI will automatically detect and map your columns.</p>
            <div class="overflow-x-auto">
                <table class="min-w-full text-xs text-left">
                    <thead class="bg-gray-50 text-gray-500 uppercase tracking-wider">
                        <tr>
                            <th class="px-3 py-2">Field</th>
                            <th class="px-3 py-2">Required</th>
                            <th class="px-3 py-2">Description</th>
                            <th class="px-3 py-2">Example</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50 text-gray-600">
                        <tr><td class="px-3 py-2 font-mono font-medium">name</td><td class="px-3 py-2"><span class="text-red-500 font-semibold">Yes</span></td><td class="px-3 py-2">Ingredient name</td><td class="px-3 py-2 font-mono">Chicken Breast</td></tr>
                        <tr><td class="px-3 py-2 font-mono font-medium">code</td><td class="px-3 py-2 text-gray-400">No</td><td class="px-3 py-2">Internal code / SKU</td><td class="px-3 py-2 font-mono">CHK-001</td></tr>
                        <tr><td class="px-3 py-2 font-mono font-medium">category</td><td class="px-3 py-2 text-gray-400">No</td><td class="px-3 py-2">Main category name (must exist)</td><td class="px-3 py-2 font-mono">Food</td></tr>
                        <tr><td class="px-3 py-2 font-mono font-medium">base_uom</td><td class="px-3 py-2"><span class="text-red-500 font-semibold">Yes</span></td><td class="px-3 py-2">Purchasing unit (abbreviation or name)</td><td class="px-3 py-2 font-mono">kg</td></tr>
                        <tr><td class="px-3 py-2 font-mono font-medium">recipe_uom</td><td class="px-3 py-2 text-gray-400">No</td><td class="px-3 py-2">Recipe unit (defaults to base UOM)</td><td class="px-3 py-2 font-mono">g</td></tr>
                        <tr><td class="px-3 py-2 font-mono font-medium">purchase_price</td><td class="px-3 py-2 text-gray-400">No</td><td class="px-3 py-2">Price per pack (default: 0)</td><td class="px-3 py-2 font-mono">42.69</td></tr>
                        <tr><td class="px-3 py-2 font-mono font-medium">pack_size</td><td class="px-3 py-2 text-gray-400">No</td><td class="px-3 py-2">Pack size in base UOM (default: 1)</td><td class="px-3 py-2 font-mono">1.2</td></tr>
                        <tr><td class="px-3 py-2 font-mono font-medium">yield_percent</td><td class="px-3 py-2 text-gray-400">No</td><td class="px-3 py-2">Yield % from 0.01–100 (default: 100)</td><td class="px-3 py-2 font-mono">80</td></tr>
                        <tr><td class="px-3 py-2 font-mono font-medium">is_active</td><td class="px-3 py-2 text-gray-400">No</td><td class="px-3 py-2">yes / no (default: yes)</td><td class="px-3 py-2 font-mono">yes</td></tr>
                        <tr><td class="px-3 py-2 font-mono font-medium">supplier</td><td class="px-3 py-2 text-gray-400">No</td><td class="px-3 py-2">Default supplier name (auto-created if not found)</td><td class="px-3 py-2 font-mono">ABC Foods Sdn Bhd</td></tr>
                        <tr><td class="px-3 py-2 font-mono font-medium">type</td><td class="px-3 py-2 text-gray-400">No</td><td class="px-3 py-2">"ingredient" or "prep" (auto-detected by AI if omitted)</td><td class="px-3 py-2 font-mono">prep</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Upload dropzone --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <h3 class="text-sm font-semibold text-gray-700 mb-4">Upload File</h3>

            <div x-data="{ dragging: false }"
                 @dragover.prevent="dragging = true"
                 @dragleave.prevent="dragging = false"
                 @drop.prevent="dragging = false; $refs.fileInput.files = $event.dataTransfer.files; $refs.fileInput.dispatchEvent(new Event('change'))"
                 :class="dragging ? 'border-indigo-400 bg-indigo-50' : 'border-gray-300 bg-gray-50'"
                 class="border-2 border-dashed rounded-xl p-10 text-center transition cursor-pointer"
                 @click="$refs.fileInput.click()">

                <input type="file" x-ref="fileInput"
                       wire:model="file"
                       accept=".csv,.xlsx,.txt,.pdf"
                       class="hidden" />

                <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 mx-auto text-gray-300 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 13h6m-3-3v6m5 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>

                @if ($file)
                    <p class="text-sm font-medium text-indigo-700">{{ $file->getClientOriginalName() }}</p>
                    <p class="text-xs text-gray-400 mt-0.5">{{ number_format($file->getSize() / 1024, 1) }} KB · Click or drop to change</p>
                @else
                    <p class="text-sm font-medium text-gray-600">Click to browse or drag & drop</p>
                    <p class="text-xs text-gray-400 mt-1">CSV, Excel (.xlsx), or PDF · max 10 MB</p>
                @endif
            </div>

            <x-input-error :messages="$errors->get('file')" class="mt-2" />

            @if ($file)
                <div class="mt-4 flex justify-end">
                    <button wire:click="processUpload" wire:loading.attr="disabled"
                            class="px-5 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition disabled:opacity-50">
                        <span wire:loading.remove wire:target="processUpload">Upload & Map Columns →</span>
                        <span wire:loading wire:target="processUpload">
                            @if ($file && strtolower($file->getClientOriginalExtension()) === 'pdf')
                                Extracting with AI…
                            @else
                                Parsing file…
                            @endif
                        </span>
                    </button>
                </div>
            @endif
        </div>

    {{-- ── STEP 2: Column Mapping ─────────────────────────────────── --}}
    @elseif ($step === 'mapping')

        {{-- AI status banner --}}
        @if ($aiMapped)
            <div class="mb-4 px-4 py-3 bg-purple-50 border border-purple-200 text-purple-800 text-sm rounded-xl flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
                </svg>
                <span><strong>AI Smart Mapping</strong> — Columns were automatically mapped using AI. Review and adjust if needed.</span>
            </div>
        @elseif ($aiError)
            <div class="mb-4 px-4 py-3 bg-amber-50 border border-amber-200 text-amber-800 text-sm rounded-xl">
                {{ $aiError }}
            </div>
        @else
            <div class="mb-4 px-4 py-3 bg-blue-50 border border-blue-200 text-blue-800 text-sm rounded-xl">
                Columns matched by header name. Adjust the mapping below if needed.
            </div>
        @endif

        @error('mapping')
            <div class="mb-4 px-4 py-3 bg-red-50 border border-red-200 text-red-700 text-sm rounded-xl">{{ $message }}</div>
        @enderror

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 mb-4">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-sm font-semibold text-gray-700">Column Mapping</h3>
                <span class="text-xs text-gray-400">{{ count($fileHeaders) }} columns detected · {{ count($fileDataRows) }} data rows</span>
            </div>

            <p class="text-xs text-gray-500 mb-4">Map each system field to a column from your file. Fields marked with <span class="text-red-500 font-semibold">*</span> are required.</p>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                @foreach (\App\Livewire\Ingredients\Import::SYSTEM_FIELDS as $sysField => $info)
                    <div class="flex items-center gap-3 p-3 rounded-lg border {{ ($info['required'] && empty($columnMapping[$sysField])) ? 'border-red-200 bg-red-50' : 'border-gray-100 bg-gray-50' }}">
                        <div class="w-36 flex-shrink-0">
                            <span class="text-xs font-semibold text-gray-700">
                                {{ $info['label'] }}
                                @if ($info['required'])<span class="text-red-500">*</span>@endif
                            </span>
                            <p class="text-[10px] text-gray-400 mt-0.5">{{ $info['description'] }}</p>
                        </div>
                        <div class="flex-1">
                            <select wire:model.live="columnMapping.{{ $sysField }}"
                                    class="w-full text-xs border-gray-200 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500 {{ !empty($columnMapping[$sysField]) ? 'text-gray-800 bg-white' : 'text-gray-400 bg-white' }}">
                                <option value="">— Not mapped —</option>
                                @foreach ($fileHeaders as $header)
                                    <option value="{{ $header }}">{{ $header }}</option>
                                @endforeach
                            </select>
                        </div>
                        @if (!empty($columnMapping[$sysField]))
                            <span class="text-green-500 flex-shrink-0">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                </svg>
                            </span>
                        @endif
                    </div>
                @endforeach
            </div>

            {{-- Prep detection note --}}
            <div class="mt-4 px-3 py-2 bg-orange-50 border border-orange-200 rounded-lg text-xs text-orange-700">
                <strong>Prep Item Detection:</strong>
                @if (!empty($columnMapping['type']))
                    Using your "{{ $columnMapping['type'] }}" column to identify prep items.
                @else
                    No "type" column mapped — AI will automatically detect prep items (sauces, stocks, marinades, etc.) in the next step.
                @endif
            </div>

            {{-- Sample data preview --}}
            @if (count($fileDataRows) > 0)
                <div class="mt-5 pt-4 border-t border-gray-100">
                    <h4 class="text-xs font-semibold text-gray-600 mb-2">Sample Data (first {{ min(3, count($fileDataRows)) }} rows)</h4>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-[11px]">
                            <thead class="bg-gray-50 text-gray-500 uppercase">
                                <tr>
                                    @foreach ($fileHeaders as $header)
                                        <th class="px-2 py-1.5 text-left whitespace-nowrap">{{ $header }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">
                                @foreach (array_slice($fileDataRows, 0, 3) as $row)
                                    <tr>
                                        @foreach ($fileHeaders as $header)
                                            <td class="px-2 py-1.5 text-gray-600 whitespace-nowrap max-w-[150px] truncate">{{ $row[$header] ?? '' }}</td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>

        {{-- Action bar --}}
        <div class="flex items-center justify-between">
            <button wire:click="restart"
                    class="px-4 py-2 text-sm text-gray-600 border border-gray-200 rounded-lg hover:bg-gray-50 transition">
                ← Upload Different File
            </button>
            <button wire:click="confirmMapping" wire:loading.attr="disabled"
                    class="px-5 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition disabled:opacity-50">
                <span wire:loading.remove wire:target="confirmMapping">Preview Import →</span>
                <span wire:loading wire:target="confirmMapping">Analyzing items…</span>
            </button>
        </div>

    {{-- ── STEP 3: Preview ─────────────────────────────────────── --}}
    @elseif ($step === 'preview')

        @php
            $prepCount = collect($rows)->where('is_prep', true)->where('skip', false)->count();
            $ingredientCount = $validRows - $prepCount;
            $uomFixCount = collect($rows)->filter(fn($r) => !empty($r['base_uom_needsfix']) || !empty($r['recipe_uom_needsfix']))->count();
            $uoms = \App\Models\UnitOfMeasure::orderBy('name')->get();
        @endphp

        {{-- Summary bar --}}
        <div class="mb-4 flex items-center gap-4 flex-wrap">
            <div class="flex items-center gap-2 px-4 py-2 bg-white rounded-lg border border-gray-100 shadow-sm text-sm">
                <span class="text-gray-500">Total rows:</span>
                <span class="font-semibold text-gray-800">{{ $totalRows }}</span>
            </div>
            <div class="flex items-center gap-2 px-4 py-2 bg-green-50 rounded-lg border border-green-200 text-sm">
                <span class="text-green-600">Ingredients:</span>
                <span class="font-semibold text-green-700">{{ $ingredientCount }}</span>
            </div>
            @if ($prepCount > 0)
                <div class="flex items-center gap-2 px-4 py-2 bg-orange-50 rounded-lg border border-orange-200 text-sm">
                    <span class="text-orange-600">Prep Items (placeholder):</span>
                    <span class="font-semibold text-orange-700">{{ $prepCount }}</span>
                </div>
            @endif
            @if ($uomFixCount > 0)
                <div class="flex items-center gap-2 px-4 py-2 bg-amber-50 rounded-lg border border-amber-200 text-sm">
                    <span class="text-amber-600">UOM to fix:</span>
                    <span class="font-semibold text-amber-700">{{ $uomFixCount }}</span>
                </div>
            @endif
            @if ($totalRows - $validRows - $uomFixCount > 0)
                <div class="flex items-center gap-2 px-4 py-2 bg-red-50 rounded-lg border border-red-200 text-sm">
                    <span class="text-red-600">Errors (skipped):</span>
                    <span class="font-semibold text-red-700">{{ $totalRows - $validRows - $uomFixCount }}</span>
                </div>
            @endif
        </div>

        {{-- UOM fix banner --}}
        @if ($uomFixCount > 0)
            <div class="mb-4 px-4 py-3 bg-amber-50 border border-amber-200 text-amber-800 text-sm rounded-xl">
                <strong>{{ $uomFixCount }} row{{ $uomFixCount > 1 ? 's have' : ' has' }} unrecognized UOM{{ $uomFixCount > 1 ? 's' : '' }}</strong> — select the correct unit from the dropdown below to include {{ $uomFixCount > 1 ? 'them' : 'it' }} in the import.
            </div>
        @endif

        {{-- Prep items info --}}
        @if ($prepCount > 0)
            <div class="mb-4 px-4 py-3 bg-orange-50 border border-orange-200 text-orange-800 text-sm rounded-xl">
                <strong>{{ $prepCount }} prep item{{ $prepCount > 1 ? 's' : '' }} detected</strong> — these will be created as placeholders. You can update them later in Inventory > Prep Items to link actual ingredients for live costing. Click the type badge on any row to toggle between Ingredient and Prep.
            </div>
        @endif

        {{-- Preview table --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden mb-4">
            <div class="px-5 py-3 border-b border-gray-100 bg-gray-50 flex items-center justify-between">
                <h3 class="text-sm font-semibold text-gray-700">Preview ({{ $totalRows }} rows)</h3>
                <div class="flex items-center gap-4 text-xs text-gray-400">
                    <span>Supplier: <span class="text-green-700">matched</span> · <span class="text-blue-700">new</span></span>
                    <span>Click type badge to toggle</span>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-xs">
                    <thead class="bg-gray-50 text-gray-500 uppercase tracking-wider border-b border-gray-100">
                        <tr>
                            <th class="px-3 py-2 text-left w-10">#</th>
                            <th class="px-3 py-2 text-center w-20">Type</th>
                            <th class="px-3 py-2 text-left">Name</th>
                            <th class="px-3 py-2 text-left w-24">Code</th>
                            <th class="px-3 py-2 text-left w-28">Category</th>
                            <th class="px-3 py-2 text-left w-32">Base UOM</th>
                            <th class="px-3 py-2 text-left w-32">Recipe UOM</th>
                            <th class="px-3 py-2 text-right w-20">Price</th>
                            <th class="px-3 py-2 text-right w-16">Qty</th>
                            <th class="px-3 py-2 text-left w-36">Packaging</th>
                            <th class="px-3 py-2 text-left w-36">Supplier</th>
                            <th class="px-3 py-2 text-left">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach ($rows as $idx => $row)
                            @php $hasOnlyUomIssue = !empty($row['base_uom_needsfix']) || !empty($row['recipe_uom_needsfix']); @endphp
                            <tr class="{{ $row['skip'] && !$hasOnlyUomIssue ? 'bg-red-50' : ($hasOnlyUomIssue ? 'bg-amber-50' : ($row['is_prep'] ? 'bg-orange-50/50' : 'hover:bg-gray-50')) }} transition">
                                <td class="px-3 py-2 text-gray-400">{{ $row['row'] }}</td>
                                <td class="px-3 py-2 text-center">
                                    @if (empty($row['errors']))
                                        <button wire:click="togglePrep({{ $idx }})"
                                                class="inline-flex items-center px-2 py-0.5 text-[10px] font-semibold rounded cursor-pointer transition
                                                    {{ $row['is_prep']
                                                        ? 'bg-orange-100 text-orange-700 hover:bg-orange-200'
                                                        : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }}"
                                                title="Click to toggle between Ingredient and Prep Item">
                                            {{ $row['is_prep'] ? 'PREP' : 'INGREDIENT' }}
                                        </button>
                                    @else
                                        <span class="text-gray-300 text-[10px]">—</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2 font-medium {{ !empty($row['errors']) ? 'text-red-700' : 'text-gray-800' }}">
                                    {{ $row['name'] ?: '—' }}
                                </td>
                                <td class="px-3 py-2 text-gray-500 font-mono">{{ $row['code'] ?? '—' }}</td>
                                <td class="px-3 py-2 text-gray-600">
                                    @if ($row['category_label'])
                                        @if ($row['ingredient_category_id'])
                                            {{ $row['category_label'] }}
                                        @elseif (!empty($row['category_is_new']))
                                            <span class="text-blue-700">{{ $row['category_label'] }}</span>
                                            <span class="ml-1 inline-flex items-center px-1.5 py-0.5 text-[10px] font-semibold bg-blue-100 text-blue-700 rounded">NEW</span>
                                        @endif
                                    @else
                                        <span class="text-gray-300">—</span>
                                    @endif
                                </td>
                                {{-- Base UOM --}}
                                <td class="px-3 py-2">
                                    @if (!empty($row['base_uom_needsfix']))
                                        <div>
                                            <span class="text-amber-600 text-[10px] font-medium block mb-1">"{{ $row['base_uom_label'] }}" not found</span>
                                            <select wire:change="fixBaseUom({{ $idx }}, $event.target.value)"
                                                    class="w-full text-[11px] border-amber-300 bg-amber-50 rounded px-1.5 py-1 focus:ring-indigo-500 focus:border-indigo-500">
                                                <option value="">Select UOM...</option>
                                                @foreach ($uoms as $uom)
                                                    <option value="{{ $uom->id }}">{{ $uom->abbreviation }} ({{ $uom->name }})</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    @else
                                        <span class="text-gray-600 font-mono">{{ $row['base_uom_label'] ?: '—' }}</span>
                                    @endif
                                </td>
                                {{-- Recipe UOM --}}
                                <td class="px-3 py-2">
                                    @if (!empty($row['recipe_uom_needsfix']))
                                        <div>
                                            <span class="text-amber-600 text-[10px] font-medium block mb-1">"{{ $row['recipe_uom_label'] }}" not found</span>
                                            <select wire:change="fixRecipeUom({{ $idx }}, $event.target.value)"
                                                    class="w-full text-[11px] border-amber-300 bg-amber-50 rounded px-1.5 py-1 focus:ring-indigo-500 focus:border-indigo-500">
                                                <option value="">Select UOM...</option>
                                                @foreach ($uoms as $uom)
                                                    <option value="{{ $uom->id }}">{{ $uom->abbreviation }} ({{ $uom->name }})</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    @else
                                        <span class="text-gray-600 font-mono">{{ $row['recipe_uom_label'] ?: '—' }}</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-right tabular-nums text-gray-700">
                                    @if ($row['is_prep'])
                                        <span class="text-gray-300">—</span>
                                    @else
                                        {{ number_format($row['purchase_price'], 2) }}
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-right tabular-nums">
                                    @if ($row['is_prep'])
                                        <span class="text-gray-300">—</span>
                                    @elseif ($row['pack_size'] > 0)
                                        <span class="text-gray-700">{{ rtrim(rtrim(number_format($row['pack_size'], 4, '.', ''), '0'), '.') }}</span>
                                    @else
                                        <span class="text-amber-500 text-[10px]">TBD</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-left text-gray-500">
                                    @if ($row['is_prep'])
                                        <span class="text-gray-300">—</span>
                                    @elseif (!empty($row['remark']))
                                        <span class="text-[10px]">{{ $row['remark'] }}</span>
                                    @else
                                        <span class="text-gray-300">—</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-gray-600">
                                    @if ($row['is_prep'])
                                        <span class="text-gray-300">—</span>
                                    @elseif ($row['supplier_label'])
                                        @if ($row['supplier_id'])
                                            <span class="text-green-700">{{ $row['supplier_label'] }}</span>
                                        @elseif (!empty($row['supplier_is_new']))
                                            <span class="text-blue-700">{{ $row['supplier_label'] }}</span>
                                            <span class="ml-1 inline-flex items-center px-1.5 py-0.5 text-[10px] font-semibold bg-blue-100 text-blue-700 rounded">NEW</span>
                                        @else
                                            <span class="text-gray-500">{{ $row['supplier_label'] }}</span>
                                        @endif
                                    @else
                                        <span class="text-gray-300">—</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2">
                                    @if (!empty($row['errors']))
                                        <ul class="space-y-0.5">
                                            @foreach ($row['errors'] as $err)
                                                <li class="text-red-600 flex items-start gap-1">
                                                    <span class="mt-0.5 flex-shrink-0">&#9888;</span>
                                                    <span>{{ $err }}</span>
                                                </li>
                                            @endforeach
                                        </ul>
                                    @elseif ($hasOnlyUomIssue)
                                        <span class="text-amber-600">&#9998; Select UOM to include</span>
                                    @elseif ($row['is_prep'])
                                        <span class="text-orange-500">Placeholder — link ingredients later</span>
                                    @else
                                        <span class="text-green-500">&#10003; OK</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Action bar --}}
        <div class="flex items-center justify-between">
            <button wire:click="backToMapping"
                    class="px-4 py-2 text-sm text-gray-600 border border-gray-200 rounded-lg hover:bg-gray-50 transition">
                ← Adjust Mapping
            </button>

            @if ($validRows > 0)
                <button wire:click="import" wire:loading.attr="disabled"
                        wire:confirm="Import {{ $validRows }} item(s) ({{ $ingredientCount }} ingredient{{ $ingredientCount !== 1 ? 's' : '' }}{{ $prepCount > 0 ? ', ' . $prepCount . ' prep placeholder' . ($prepCount !== 1 ? 's' : '') : '' }})? Rows with errors will be skipped."
                        class="px-6 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition disabled:opacity-50">
                    <span wire:loading.remove wire:target="import">Import {{ $validRows }} Item{{ $validRows !== 1 ? 's' : '' }}</span>
                    <span wire:loading wire:target="import">Importing…</span>
                </button>
            @else
                <p class="text-sm text-red-600 font-medium">No valid rows to import. Fix the errors in your file and re-upload.</p>
            @endif
        </div>

    {{-- ── STEP 4: Done ─────────────────────────────────────── --}}
    @elseif ($step === 'done')

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-10 text-center">
            <div class="text-5xl mb-4">&#127881;</div>
            <h3 class="text-xl font-semibold text-gray-800 mb-2">Import Complete</h3>

            <div class="flex items-center justify-center gap-6 mt-4 mb-6">
                <div class="text-center">
                    <p class="text-3xl font-bold text-green-600">{{ $importedCount }}</p>
                    <p class="text-sm text-gray-500 mt-0.5">Imported</p>
                </div>
                @if ($prepCreatedCount > 0)
                    <div class="text-center">
                        <p class="text-3xl font-bold text-orange-500">{{ $prepCreatedCount }}</p>
                        <p class="text-sm text-gray-500 mt-0.5">Prep Placeholders</p>
                    </div>
                @endif
                @if ($skippedCount > 0)
                    <div class="text-center">
                        <p class="text-3xl font-bold text-red-500">{{ $skippedCount }}</p>
                        <p class="text-sm text-gray-500 mt-0.5">Skipped (errors)</p>
                    </div>
                @endif
            </div>

            @if ($prepCreatedCount > 0)
                <p class="text-sm text-orange-600 mb-4">
                    {{ $prepCreatedCount }} prep item{{ $prepCreatedCount > 1 ? 's were' : ' was' }} created as placeholder{{ $prepCreatedCount > 1 ? 's' : '' }}.
                    Go to <strong>Inventory > Prep Items</strong> to link actual ingredients for live costing.
                </p>
            @endif

            <div class="flex items-center justify-center gap-3">
                <a href="{{ route('ingredients.index') }}"
                   class="px-5 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
                    View Ingredients
                </a>
                @if ($prepCreatedCount > 0)
                    <a href="{{ route('inventory.index') }}"
                       class="px-4 py-2 text-sm text-orange-600 border border-orange-200 rounded-lg hover:bg-orange-50 transition">
                        Go to Prep Items
                    </a>
                @endif
                <button wire:click="restart"
                        class="px-4 py-2 text-sm text-gray-600 border border-gray-200 rounded-lg hover:bg-gray-50 transition">
                    Import Another File
                </button>
            </div>
        </div>

    @endif
</div>
