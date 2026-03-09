<div>
    @if (session()->has('success'))
        <div wire:key="flash-{{ microtime(true) }}" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)"
             class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-700 text-sm rounded-lg">
            {{ session('success') }}
        </div>
    @endif

    {{-- Header --}}
    <div class="flex items-center gap-3 mb-6">
        <a href="{{ route('sales.index') }}" class="text-gray-400 hover:text-gray-600 transition flex-shrink-0">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
            </svg>
        </a>
        <div class="flex-1">
            <p class="text-xs text-gray-400">
                <a href="{{ route('sales.index') }}" class="hover:underline">Sales</a> / Import
            </p>
            <h2 class="text-lg font-semibold text-gray-800 mt-0.5">Import Sales from CSV</h2>
        </div>
    </div>

    {{-- Step indicator --}}
    <div class="mb-6 flex items-center gap-2 text-xs text-gray-400">
        <span class="{{ $step === 'upload' ? 'text-indigo-600 font-semibold' : ($step !== 'upload' ? 'text-green-600' : '') }}">1. Upload</span>
        <span>&rarr;</span>
        <span class="{{ $step === 'mapping' ? 'text-indigo-600 font-semibold' : (in_array($step, ['preview', 'done']) ? 'text-green-600' : '') }}">2. Map Columns</span>
        <span>&rarr;</span>
        <span class="{{ $step === 'preview' ? 'text-indigo-600 font-semibold' : ($step === 'done' ? 'text-green-600' : '') }}">3. Preview</span>
        <span>&rarr;</span>
        <span class="{{ $step === 'done' ? 'text-indigo-600 font-semibold' : '' }}">4. Import</span>
    </div>

    {{-- STEP 1: Upload --}}
    @if ($step === 'upload')

        {{-- Info banner --}}
        <div class="mb-6 px-4 py-4 bg-blue-50 border border-blue-200 text-blue-800 text-sm rounded-xl space-y-2">
            <p class="font-semibold">How to import</p>
            <ol class="list-decimal list-inside space-y-1 text-blue-700">
                <li>Download the sample template below and fill in your sales data.</li>
                <li>Each row = one sales entry (one date + meal period).</li>
                <li>Upload a CSV or Excel (.xlsx) file — you'll map columns in the next step.</li>
                <li>Review the preview and confirm the import.</li>
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
                        <span wire:loading.remove wire:target="processUpload">Map Columns &rarr;</span>
                        <span wire:loading wire:target="processUpload">Parsing file...</span>
                    </button>
                </div>
            @endif
        </div>

    {{-- STEP 2: Column Mapping --}}
    @elseif ($step === 'mapping')

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden mb-4">
            <div class="px-5 py-3 border-b border-gray-100 bg-gray-50">
                <h3 class="text-sm font-semibold text-gray-700">Map File Columns</h3>
                <p class="text-xs text-gray-400 mt-0.5">Choose what each column in your file represents. Columns set to "Ignore" will be skipped.</p>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full text-xs">
                    <thead class="bg-gray-50 text-gray-500 uppercase tracking-wider border-b border-gray-100">
                        <tr>
                            <th class="px-4 py-2 text-left w-48">File Column</th>
                            <th class="px-4 py-2 text-left w-56">Map To</th>
                            <th class="px-4 py-2 text-left">Sample Data (first 3 rows)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach ($fileHeaders as $idx => $header)
                            <tr class="hover:bg-gray-50 transition">
                                <td class="px-4 py-3">
                                    <span class="font-mono font-medium text-gray-800">{{ $header }}</span>
                                </td>
                                <td class="px-4 py-3">
                                    <select wire:model="columnMap.{{ $header }}"
                                            class="w-full text-xs border-gray-200 rounded-lg shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50
                                                   {{ ($columnMap[$header] ?? 'ignore') === 'ignore' ? 'text-gray-400' : 'text-gray-800 font-medium' }}">
                                        @foreach ($this->mappingOptions as $value => $label)
                                            <option value="{{ $value }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="px-4 py-3 text-gray-500 font-mono">
                                    @php
                                        $samples = array_slice($rawRows, 0, 3);
                                    @endphp
                                    @foreach ($samples as $si => $sampleRow)
                                        <span class="inline-block mr-3">{{ \Illuminate\Support\Str::limit($sampleRow[$header] ?? '—', 30) }}</span>
                                        @if ($si < count($samples) - 1)
                                            <span class="text-gray-300">|</span>
                                        @endif
                                    @endforeach
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Mapping info --}}
        <div class="mb-4 px-4 py-3 bg-amber-50 border border-amber-200 text-amber-800 text-xs rounded-lg">
            <strong>Tip:</strong> You must map at least one column to "Date" and one to a sales category. If no "Meal Period" column is mapped, all rows default to "All Day".
        </div>

        <x-input-error :messages="$errors->get('mapping')" class="mb-4" />

        {{-- Action bar --}}
        <div class="flex items-center justify-between">
            <button wire:click="restart"
                    class="px-4 py-2 text-sm text-gray-600 border border-gray-200 rounded-lg hover:bg-gray-50 transition">
                &larr; Upload Different File
            </button>

            <button wire:click="applyMapping" wire:loading.attr="disabled"
                    class="px-5 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition disabled:opacity-50">
                <span wire:loading.remove wire:target="applyMapping">Preview Import &rarr;</span>
                <span wire:loading wire:target="applyMapping">Processing...</span>
            </button>
        </div>

    {{-- STEP 3: Preview --}}
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
                    <span class="text-red-600">Rows with errors:</span>
                    <span class="font-semibold text-red-700">{{ $totalRows - $validRows }}</span>
                </div>
            @endif
        </div>

        {{-- Preview table --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden mb-4">
            <div class="px-5 py-3 border-b border-gray-100 bg-gray-50 flex items-center justify-between">
                <h3 class="text-sm font-semibold text-gray-700">Preview ({{ $totalRows }} rows)</h3>
                <p class="text-xs text-gray-400">Red rows have errors and will be skipped.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-xs">
                    <thead class="bg-gray-50 text-gray-500 uppercase tracking-wider border-b border-gray-100">
                        <tr>
                            <th class="px-3 py-2 text-left w-10">#</th>
                            <th class="px-3 py-2 text-left w-28">Date</th>
                            <th class="px-3 py-2 text-left w-24">Reference</th>
                            <th class="px-3 py-2 text-left w-20">Period</th>
                            <th class="px-3 py-2 text-right w-14">Pax</th>
                            @foreach ($categoryNames as $catName)
                                <th class="px-3 py-2 text-right w-28">{{ $catName }}</th>
                            @endforeach
                            <th class="px-3 py-2 text-right w-28 font-bold">Total</th>
                            <th class="px-3 py-2 text-left">Issues</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach ($rows as $row)
                            <tr class="{{ $row['skip'] ? 'bg-red-50' : 'hover:bg-gray-50' }} transition">
                                <td class="px-3 py-2 text-gray-400">{{ $row['row'] }}</td>
                                <td class="px-3 py-2 font-medium {{ $row['skip'] ? 'text-red-700' : 'text-gray-800' }}">
                                    {{ $row['date'] }}
                                </td>
                                <td class="px-3 py-2 text-gray-500 font-mono">{{ $row['reference'] ?: '—' }}</td>
                                <td class="px-3 py-2 text-gray-600">{{ ucfirst(str_replace('_', ' ', $row['meal_period'])) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums text-gray-700">{{ $row['pax'] }}</td>
                                @foreach ($row['category_revenues'] as $catRev)
                                    <td class="px-3 py-2 text-right tabular-nums text-gray-700">
                                        {{ number_format($catRev['revenue'], 2) }}
                                    </td>
                                @endforeach
                                @if (empty($row['category_revenues']))
                                    @foreach ($categoryNames as $_)
                                        <td class="px-3 py-2 text-right text-gray-300">—</td>
                                    @endforeach
                                @endif
                                <td class="px-3 py-2 text-right tabular-nums font-semibold text-gray-800">
                                    {{ number_format($row['total_revenue'], 2) }}
                                </td>
                                <td class="px-3 py-2">
                                    @if ($row['skip'])
                                        <ul class="space-y-0.5">
                                            @foreach ($row['errors'] as $err)
                                                <li class="text-red-600 flex items-start gap-1">
                                                    <span class="mt-0.5">!</span>
                                                    <span>{{ $err }}</span>
                                                </li>
                                            @endforeach
                                        </ul>
                                    @else
                                        <span class="text-green-500">OK</span>
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
                &larr; Back to Mapping
            </button>

            @if ($validRows > 0)
                <button wire:click="import" wire:loading.attr="disabled"
                        wire:confirm="Import {{ $validRows }} sales record(s)? Rows with errors will be skipped."
                        class="px-6 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition disabled:opacity-50">
                    <span wire:loading.remove wire:target="import">Import {{ $validRows }} Record{{ $validRows !== 1 ? 's' : '' }}</span>
                    <span wire:loading wire:target="import">Importing...</span>
                </button>
            @else
                <p class="text-sm text-red-600 font-medium">No valid rows to import. Go back and adjust mappings.</p>
            @endif
        </div>

    {{-- STEP 4: Done --}}
    @elseif ($step === 'done')

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-10 text-center">
            <div class="w-16 h-16 mx-auto mb-4 bg-green-100 rounded-full flex items-center justify-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                </svg>
            </div>
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
                <a href="{{ route('sales.index') }}"
                   class="px-5 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
                    View Sales
                </a>
                <button wire:click="restart"
                        class="px-4 py-2 text-sm text-gray-600 border border-gray-200 rounded-lg hover:bg-gray-50 transition">
                    Import Another File
                </button>
            </div>
        </div>

    @endif
</div>
