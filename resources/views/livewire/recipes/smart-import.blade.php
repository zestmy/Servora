<div>
    @if (session()->has('error'))
        <div wire:key="flash-{{ microtime(true) }}" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)"
             class="mb-4 px-4 py-3 bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg">
            {{ session('error') }}
        </div>
    @endif

    {{-- Header --}}
    <div class="flex items-center gap-3 mb-6">
        <a href="{{ route('recipes.index', $isPrep ? ['tab' => 'prep-items'] : []) }}" class="text-gray-400 hover:text-gray-600 transition flex-shrink-0">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
            </svg>
        </a>
        <div class="flex-1">
            <p class="text-xs text-gray-400">
                <a href="{{ route('recipes.index', $isPrep ? ['tab' => 'prep-items'] : []) }}" class="hover:underline">{{ $isPrep ? 'Prep Items' : 'Recipes' }}</a> / Smart Import
            </p>
            <h2 class="text-lg font-semibold text-gray-800 mt-0.5">Smart Import {{ $isPrep ? 'Prep Items' : 'Recipes' }}</h2>
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

    {{-- ── STEP 1: Upload ───────────────────────────────────────── --}}
    @if ($step === 'upload')

        <div class="mb-6 px-4 py-4 bg-blue-50 border border-blue-200 text-blue-800 text-sm rounded-xl space-y-2">
            <p class="font-semibold">How it works</p>
            <ol class="list-decimal list-inside space-y-1 text-blue-700">
                <li>Upload a CSV, Excel, or PDF file containing recipes with their ingredients.</li>
                <li>AI automatically maps your columns and matches ingredients to your existing inventory.</li>
                <li>Review, fix any unmatched ingredients, then import.</li>
            </ol>
            <p class="text-xs text-blue-600 pt-1">
                For CSV/Excel: each row is one ingredient line. Rows sharing the same recipe name get grouped into a single recipe.
            </p>
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
            <h3 class="text-sm font-semibold text-gray-700 mb-3">Expected Columns</h3>
            <p class="text-xs text-gray-500 mb-3">Your file doesn't need these exact names — AI will auto-detect and map your columns.</p>
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
                        <tr><td class="px-3 py-2 font-mono font-medium">recipe_name</td><td class="px-3 py-2"><span class="text-red-500 font-semibold">Yes</span></td><td class="px-3 py-2">Recipe / menu item name</td><td class="px-3 py-2 font-mono">Nasi Lemak</td></tr>
                        <tr><td class="px-3 py-2 font-mono font-medium">recipe_code</td><td class="px-3 py-2 text-gray-400">No</td><td class="px-3 py-2">Internal code</td><td class="px-3 py-2 font-mono">NL-001</td></tr>
                        <tr><td class="px-3 py-2 font-mono font-medium">category</td><td class="px-3 py-2 text-gray-400">No</td><td class="px-3 py-2">Menu category</td><td class="px-3 py-2 font-mono">Food</td></tr>
                        <tr><td class="px-3 py-2 font-mono font-medium">yield_quantity</td><td class="px-3 py-2 text-gray-400">No</td><td class="px-3 py-2">Servings per batch (default 1)</td><td class="px-3 py-2 font-mono">1</td></tr>
                        <tr><td class="px-3 py-2 font-mono font-medium">yield_uom</td><td class="px-3 py-2 text-gray-400">No</td><td class="px-3 py-2">Yield unit (default: portion)</td><td class="px-3 py-2 font-mono">portion</td></tr>
                        <tr><td class="px-3 py-2 font-mono font-medium">selling_price</td><td class="px-3 py-2 text-gray-400">No</td><td class="px-3 py-2">Selling price per serving</td><td class="px-3 py-2 font-mono">12.90</td></tr>
                        <tr><td class="px-3 py-2 font-mono font-medium">ingredient_name</td><td class="px-3 py-2"><span class="text-red-500 font-semibold">Yes</span></td><td class="px-3 py-2">Ingredient name (must exist in system)</td><td class="px-3 py-2 font-mono">Rice</td></tr>
                        <tr><td class="px-3 py-2 font-mono font-medium">quantity</td><td class="px-3 py-2"><span class="text-red-500 font-semibold">Yes</span></td><td class="px-3 py-2">Amount used per batch</td><td class="px-3 py-2 font-mono">200</td></tr>
                        <tr><td class="px-3 py-2 font-mono font-medium">uom</td><td class="px-3 py-2"><span class="text-red-500 font-semibold">Yes</span></td><td class="px-3 py-2">Unit of measure</td><td class="px-3 py-2 font-mono">g</td></tr>
                        <tr><td class="px-3 py-2 font-mono font-medium">waste_percentage</td><td class="px-3 py-2 text-gray-400">No</td><td class="px-3 py-2">Waste % (default 0)</td><td class="px-3 py-2 font-mono">5</td></tr>
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

                <input type="file" x-ref="fileInput" wire:model="file" accept=".csv,.xlsx,.txt,.pdf" class="hidden" />

                <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 mx-auto text-gray-300 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 13h6m-3-3v6m5 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>

                @if ($file)
                    <p class="text-sm font-medium text-indigo-700">{{ $file->getClientOriginalName() }}</p>
                    <p class="text-xs text-gray-400 mt-0.5">{{ number_format($file->getSize() / 1024, 1) }} KB</p>
                @else
                    <p class="text-sm font-medium text-gray-600">Click to browse or drag & drop</p>
                    <p class="text-xs text-gray-400 mt-1">CSV, Excel (.xlsx), or PDF — max 10 MB</p>
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
                                Extracting recipes with AI…
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

        @if ($aiMapped)
            <div class="mb-4 px-4 py-3 bg-purple-50 border border-purple-200 text-purple-800 text-sm rounded-xl flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
                </svg>
                <span><strong>AI Smart Mapping</strong> — Columns were automatically mapped. Review and adjust if needed.</span>
            </div>
        @elseif ($aiError)
            <div class="mb-4 px-4 py-3 bg-amber-50 border border-amber-200 text-amber-800 text-sm rounded-xl">{{ $aiError }}</div>
        @endif

        @error('mapping')
            <div class="mb-4 px-4 py-3 bg-red-50 border border-red-200 text-red-700 text-sm rounded-xl">{{ $message }}</div>
        @enderror

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 mb-4">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-sm font-semibold text-gray-700">Column Mapping</h3>
                <span class="text-xs text-gray-400">{{ count($fileHeaders) }} columns · {{ count($fileDataRows) }} data rows</span>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                @foreach (\App\Livewire\Recipes\SmartImport::SYSTEM_FIELDS as $sysField => $info)
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
                                    class="w-full text-xs border-gray-200 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
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

            {{-- Sample data --}}
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

        <div class="flex items-center justify-between">
            <button wire:click="restart" class="px-4 py-2 text-sm text-gray-600 border border-gray-200 rounded-lg hover:bg-gray-50 transition">
                ← Upload Different File
            </button>
            <button wire:click="confirmMapping" wire:loading.attr="disabled"
                    class="px-5 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition disabled:opacity-50">
                <span wire:loading.remove wire:target="confirmMapping">Preview Recipes →</span>
                <span wire:loading wire:target="confirmMapping">Matching ingredients…</span>
            </button>
        </div>

    {{-- ── STEP 3: Preview ─────────────────────────────────────── --}}
    @elseif ($step === 'preview')

        {{-- Summary bar --}}
        <div class="mb-4 px-4 py-3 bg-white rounded-xl shadow-sm border border-gray-100 flex items-center justify-between">
            <div class="flex items-center gap-4 text-sm">
                <span class="text-gray-500">Total: <strong class="text-gray-800">{{ $totalRecipes }}</strong></span>
                <span class="text-green-600">Ready: <strong>{{ $validRecipes }}</strong></span>
                @if ($totalRecipes - $validRecipes > 0)
                    <span class="text-amber-600">Needs fix: <strong>{{ $totalRecipes - $validRecipes }}</strong></span>
                @endif
            </div>
        </div>

        {{-- Recipe cards --}}
        <div class="space-y-4 mb-6">
            @foreach ($recipes as $rIdx => $recipe)
                <div class="bg-white rounded-xl shadow-sm border {{ $recipe['skip'] ? 'border-amber-200' : 'border-gray-100' }} overflow-hidden"
                     x-data="{ open: {{ $recipe['skip'] ? 'true' : 'false' }} }">

                    {{-- Recipe header --}}
                    <div class="flex items-center gap-3 px-5 py-3 cursor-pointer hover:bg-gray-50 transition" @click="open = !open">
                        <button type="button" wire:click.stop="toggleSkip({{ $rIdx }})"
                                class="flex-shrink-0 w-5 h-5 rounded border-2 flex items-center justify-center transition
                                {{ $recipe['skip'] ? 'border-gray-300 bg-gray-100' : 'border-green-500 bg-green-500' }}">
                            @if (! $recipe['skip'])
                                <svg class="w-3 h-3 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                </svg>
                            @endif
                        </button>

                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2">
                                <span class="font-semibold text-sm text-gray-800 truncate">{{ $recipe['name'] }}</span>
                                @if ($recipe['code'])
                                    <span class="text-xs text-gray-400">{{ $recipe['code'] }}</span>
                                @endif
                                @if ($recipe['category'])
                                    <span class="px-2 py-0.5 bg-indigo-50 text-indigo-600 text-[10px] font-medium rounded-full">{{ $recipe['category'] }}</span>
                                @endif
                            </div>
                            <div class="text-xs text-gray-400 mt-0.5">
                                {{ count($recipe['lines']) }} ingredient{{ count($recipe['lines']) !== 1 ? 's' : '' }}
                                · Yield: {{ $recipe['yield_quantity'] }} {{ $recipe['yield_uom_label'] }}
                                @if ($recipe['selling_price'] > 0)
                                    · RM {{ number_format($recipe['selling_price'], 2) }}
                                @endif
                            </div>
                        </div>

                        @if (! empty($recipe['errors']) || collect($recipe['lines'])->contains(fn ($l) => ! $l['ingredient_id'] || ! $l['uom_id']))
                            <span class="px-2 py-0.5 bg-amber-100 text-amber-700 text-[10px] font-bold rounded-full">NEEDS FIX</span>
                        @else
                            <span class="px-2 py-0.5 bg-green-100 text-green-700 text-[10px] font-bold rounded-full">READY</span>
                        @endif

                        <svg :class="open && 'rotate-180'" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-400 transition-transform flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                        </svg>
                    </div>

                    {{-- Recipe errors --}}
                    @if (! empty($recipe['errors']))
                        <div class="px-5 py-2 bg-red-50 border-t border-red-100">
                            @foreach ($recipe['errors'] as $err)
                                <p class="text-xs text-red-600">{{ $err }}</p>
                            @endforeach
                            @if (! $recipe['yield_uom_id'])
                                <div class="mt-1 flex items-center gap-2">
                                    <span class="text-xs text-red-600">Fix yield UOM:</span>
                                    <select wire:change="fixYieldUom({{ $rIdx }}, $event.target.value)"
                                            class="text-xs border-gray-200 rounded-lg py-1 px-2">
                                        <option value="">Select UOM</option>
                                        @foreach ($uoms as $uom)
                                            <option value="{{ $uom->id }}">{{ $uom->name }} ({{ $uom->abbreviation }})</option>
                                        @endforeach
                                    </select>
                                </div>
                            @endif
                        </div>
                    @endif

                    {{-- Ingredient lines --}}
                    <div x-show="open" x-cloak class="border-t border-gray-100">
                        <table class="min-w-full text-xs">
                            <thead class="bg-gray-50 text-gray-500 uppercase text-[10px] tracking-wider">
                                <tr>
                                    <th class="px-4 py-2 text-left w-8">#</th>
                                    <th class="px-4 py-2 text-left">Ingredient (file)</th>
                                    <th class="px-4 py-2 text-left">Matched To</th>
                                    <th class="px-4 py-2 text-right w-20">Qty</th>
                                    <th class="px-4 py-2 text-left w-24">UOM</th>
                                    <th class="px-4 py-2 text-right w-16">Waste%</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">
                                @foreach ($recipe['lines'] as $lIdx => $line)
                                    <tr class="{{ ! $line['ingredient_id'] || ! $line['uom_id'] ? 'bg-amber-50' : '' }}">
                                        <td class="px-4 py-2 text-gray-400">{{ $lIdx + 1 }}</td>
                                        <td class="px-4 py-2 font-medium text-gray-700">{{ $line['ingredient_name'] }}</td>
                                        <td class="px-4 py-2">
                                            @if ($line['ingredient_id'])
                                                <span class="text-green-700">{{ $line['matched_name'] }}</span>
                                                @if ($line['confidence'] < 100)
                                                    <span class="text-[10px] text-amber-500 ml-1">{{ $line['confidence'] }}%</span>
                                                @endif
                                            @else
                                                <div class="flex items-center gap-1.5">
                                                    <span class="text-red-500 text-[10px] font-semibold">NOT FOUND</span>
                                                    <select wire:change="fixIngredient({{ $rIdx }}, {{ $lIdx }}, $event.target.value)"
                                                            class="text-[11px] border-gray-200 rounded py-0.5 px-1 max-w-[180px]">
                                                        <option value="">Select ingredient…</option>
                                                        @foreach ($ingredients as $ing)
                                                            <option value="{{ $ing->id }}">{{ $ing->name }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                            @endif
                                        </td>
                                        <td class="px-4 py-2 text-right tabular-nums">{{ $line['quantity'] }}</td>
                                        <td class="px-4 py-2">
                                            @if ($line['uom_id'])
                                                <span class="text-gray-600">{{ $line['uom_raw'] }}</span>
                                            @else
                                                <div class="flex items-center gap-1">
                                                    <span class="text-red-500 text-[10px]">{{ $line['uom_raw'] ?: '?' }}</span>
                                                    <select wire:change="fixLineUom({{ $rIdx }}, {{ $lIdx }}, $event.target.value)"
                                                            class="text-[11px] border-gray-200 rounded py-0.5 px-1">
                                                        <option value="">Fix UOM</option>
                                                        @foreach ($uoms as $uom)
                                                            <option value="{{ $uom->id }}">{{ $uom->abbreviation }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                            @endif
                                        </td>
                                        <td class="px-4 py-2 text-right tabular-nums text-gray-500">{{ $line['waste_percentage'] }}%</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Action bar --}}
        <div class="flex items-center justify-between">
            <button wire:click="restart" class="px-4 py-2 text-sm text-gray-600 border border-gray-200 rounded-lg hover:bg-gray-50 transition">
                ← Start Over
            </button>
            <button wire:click="import" wire:loading.attr="disabled"
                    {{ $validRecipes === 0 ? 'disabled' : '' }}
                    class="px-6 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition disabled:opacity-50">
                <span wire:loading.remove wire:target="import">Import {{ $validRecipes }} {{ $validRecipes === 1 ? 'Recipe' : 'Recipes' }}</span>
                <span wire:loading wire:target="import">Importing…</span>
            </button>
        </div>

    {{-- ── STEP 4: Done ─────────────────────────────────────── --}}
    @elseif ($step === 'done')

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-8 text-center">
            <div class="inline-flex items-center justify-center w-16 h-16 bg-green-100 rounded-full mb-4">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                </svg>
            </div>
            <h3 class="text-lg font-bold text-gray-800 mb-2">Import Complete</h3>
            <div class="flex items-center justify-center gap-6 text-sm mb-6">
                <div class="text-center">
                    <p class="text-2xl font-bold text-green-600">{{ $importedCount }}</p>
                    <p class="text-xs text-gray-500">{{ $isPrep ? 'Prep Items' : 'Recipes' }} Created</p>
                </div>
                <div class="text-center">
                    <p class="text-2xl font-bold text-indigo-600">{{ $linesImported }}</p>
                    <p class="text-xs text-gray-500">Ingredient Lines</p>
                </div>
                @if ($skippedCount > 0)
                    <div class="text-center">
                        <p class="text-2xl font-bold text-amber-600">{{ $skippedCount }}</p>
                        <p class="text-xs text-gray-500">Skipped</p>
                    </div>
                @endif
            </div>
            <div class="flex items-center justify-center gap-3">
                <a href="{{ route('recipes.index', $isPrep ? ['tab' => 'prep-items'] : []) }}"
                   class="px-5 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
                    View {{ $isPrep ? 'Prep Items' : 'Recipes' }}
                </a>
                <button wire:click="restart" class="px-5 py-2 text-sm text-gray-600 border border-gray-200 rounded-lg hover:bg-gray-50 transition">
                    Import More
                </button>
            </div>
        </div>

    @endif
</div>
