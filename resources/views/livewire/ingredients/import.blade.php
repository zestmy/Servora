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
                <li>Upload any CSV or Excel (.xlsx) file — AI will automatically map your columns.</li>
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
                        <tr><td class="px-3 py-2 font-mono font-medium">supplier</td><td class="px-3 py-2 text-gray-400">No</td><td class="px-3 py-2">Default supplier name (must exist)</td><td class="px-3 py-2 font-mono">ABC Foods Sdn Bhd</td></tr>
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
                       accept=".csv,.xlsx,.txt"
                       class="hidden" />

                <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 mx-auto text-gray-300 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 13h6m-3-3v6m5 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>

                @if ($file)
                    <p class="text-sm font-medium text-indigo-700">{{ $file->getClientOriginalName() }}</p>
                    <p class="text-xs text-gray-400 mt-0.5">{{ number_format($file->getSize() / 1024, 1) }} KB · Click or drop to change</p>
                @else
                    <p class="text-sm font-medium text-gray-600">Click to browse or drag & drop</p>
                    <p class="text-xs text-gray-400 mt-1">CSV or Excel (.xlsx) · max 10 MB</p>
                @endif
            </div>

            <x-input-error :messages="$errors->get('file')" class="mt-2" />

            @if ($file)
                <div class="mt-4 flex justify-end">
                    <button wire:click="processUpload" wire:loading.attr="disabled"
                            class="px-5 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition disabled:opacity-50">
                        <span wire:loading.remove wire:target="processUpload">Upload & Map Columns →</span>
                        <span wire:loading wire:target="processUpload">Parsing file…</span>
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
                <span wire:loading wire:target="confirmMapping">Building preview…</span>
            </button>
        </div>

    {{-- ── STEP 3: Preview ─────────────────────────────────────── --}}
    @elseif ($step === 'preview')

        {{-- Summary bar --}}
        <div class="mb-4 flex items-center gap-4 flex-wrap">
            <div class="flex items-center gap-2 px-4 py-2 bg-white rounded-lg border border-gray-100 shadow-sm text-sm">
                <span class="text-gray-500">Total rows:</span>
                <span class="font-semibold text-gray-800">{{ $totalRows }}</span>
            </div>
            <div class="flex items-center gap-2 px-4 py-2 bg-green-50 rounded-lg border border-green-200 text-sm">
                <span class="text-green-600">Ready to import:</span>
                <span class="font-semibold text-green-700">{{ $validRows }}</span>
            </div>
            @if ($totalRows - $validRows > 0)
                <div class="flex items-center gap-2 px-4 py-2 bg-red-50 rounded-lg border border-red-200 text-sm">
                    <span class="text-red-600">Rows with errors (will be skipped):</span>
                    <span class="font-semibold text-red-700">{{ $totalRows - $validRows }}</span>
                </div>
            @endif
        </div>

        {{-- Preview table --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden mb-4">
            <div class="px-5 py-3 border-b border-gray-100 bg-gray-50 flex items-center justify-between">
                <h3 class="text-sm font-semibold text-gray-700">Preview ({{ $totalRows }} rows)</h3>
                <p class="text-xs text-gray-400">Rows highlighted in red have errors and will be skipped.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-xs">
                    <thead class="bg-gray-50 text-gray-500 uppercase tracking-wider border-b border-gray-100">
                        <tr>
                            <th class="px-3 py-2 text-left w-10">#</th>
                            <th class="px-3 py-2 text-left">Name</th>
                            <th class="px-3 py-2 text-left w-24">Code</th>
                            <th class="px-3 py-2 text-left w-28">Category</th>
                            <th class="px-3 py-2 text-left w-20">Base UOM</th>
                            <th class="px-3 py-2 text-left w-20">Recipe UOM</th>
                            <th class="px-3 py-2 text-right w-24">Price (RM)</th>
                            <th class="px-3 py-2 text-right w-20">Pack Size</th>
                            <th class="px-3 py-2 text-right w-20">Yield %</th>
                            <th class="px-3 py-2 text-center w-16">Active</th>
                            <th class="px-3 py-2 text-left w-36">Supplier</th>
                            <th class="px-3 py-2 text-left">Issues</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach ($rows as $row)
                            <tr class="{{ $row['skip'] ? 'bg-red-50' : 'hover:bg-gray-50' }} transition">
                                <td class="px-3 py-2 text-gray-400">{{ $row['row'] }}</td>
                                <td class="px-3 py-2 font-medium {{ $row['skip'] ? 'text-red-700' : 'text-gray-800' }}">
                                    {{ $row['name'] ?: '—' }}
                                </td>
                                <td class="px-3 py-2 text-gray-500 font-mono">{{ $row['code'] ?? '—' }}</td>
                                <td class="px-3 py-2 text-gray-600">{{ $row['category_label'] ?: '—' }}</td>
                                <td class="px-3 py-2 text-gray-600 font-mono">{{ $row['base_uom_label'] ?: '—' }}</td>
                                <td class="px-3 py-2 text-gray-600 font-mono">{{ $row['recipe_uom_label'] ?: '—' }}</td>
                                <td class="px-3 py-2 text-right tabular-nums text-gray-700">
                                    {{ number_format($row['purchase_price'], 4) }}
                                </td>
                                <td class="px-3 py-2 text-right tabular-nums text-gray-700">
                                    {{ $row['pack_size'] != 1 ? rtrim(rtrim(number_format($row['pack_size'], 4, '.', ''), '0'), '.') : '1' }}
                                </td>
                                <td class="px-3 py-2 text-right tabular-nums text-gray-700">
                                    {{ $row['yield_percent'] }}%
                                </td>
                                <td class="px-3 py-2 text-center">
                                    @if ($row['is_active'])
                                        <span class="text-green-600">Yes</span>
                                    @else
                                        <span class="text-gray-400">No</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-gray-600">
                                    @if ($row['supplier_label'])
                                        @if ($row['supplier_id'])
                                            <span class="text-green-700">{{ $row['supplier_label'] }}</span>
                                        @else
                                            <span class="text-red-500">{{ $row['supplier_label'] }}</span>
                                        @endif
                                    @else
                                        <span class="text-gray-300">—</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2">
                                    @if ($row['skip'])
                                        <ul class="space-y-0.5">
                                            @foreach ($row['errors'] as $err)
                                                <li class="text-red-600 flex items-start gap-1">
                                                    <span class="mt-0.5 flex-shrink-0">&#9888;</span>
                                                    <span>{{ $err }}</span>
                                                </li>
                                            @endforeach
                                        </ul>
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
                        wire:confirm="Import {{ $validRows }} ingredient(s)? Rows with errors will be skipped."
                        class="px-6 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition disabled:opacity-50">
                    <span wire:loading.remove wire:target="import">Import {{ $validRows }} Ingredient{{ $validRows !== 1 ? 's' : '' }}</span>
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
                @if ($skippedCount > 0)
                    <div class="text-center">
                        <p class="text-3xl font-bold text-red-500">{{ $skippedCount }}</p>
                        <p class="text-sm text-gray-500 mt-0.5">Skipped (errors)</p>
                    </div>
                @endif
            </div>

            <div class="flex items-center justify-center gap-3">
                <a href="{{ route('ingredients.index') }}"
                   class="px-5 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
                    View Ingredients
                </a>
                <button wire:click="restart"
                        class="px-4 py-2 text-sm text-gray-600 border border-gray-200 rounded-lg hover:bg-gray-50 transition">
                    Import Another File
                </button>
            </div>
        </div>

    @endif
</div>
