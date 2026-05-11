{{-- Zeoniq Excel Import Modal --}}
<div>
    @if ($showModal)
    @teleport('body')
    <div class="fixed inset-0 z-50">
        <div class="fixed inset-0 bg-gray-900/60" wire:click="close"></div>
        <div class="fixed inset-0 overflow-y-auto">
        <div class="flex min-h-full items-center justify-center p-4">

        <div class="relative bg-white rounded-xl shadow-xl w-full max-w-4xl z-10 max-h-[92vh] flex flex-col">

            {{-- Header --}}
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 flex-shrink-0">
                <div>
                    <h3 class="text-base font-semibold text-gray-800">Import Zeoniq Excel</h3>
                    <p class="text-xs text-gray-400 mt-0.5">
                        @if ($step === 'upload')
                            Upload Session Sales Listing or Daily Summary from Zeoniq Cloud Dashboard
                        @elseif ($step === 'mapping')
                            Map department names to Sales Categories
                        @else
                            Review {{ count($parsedRecords) }} date(s) found — select records to import
                        @endif
                    </p>
                </div>
                <button wire:click="close" class="text-gray-400 hover:text-gray-600 transition">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <div class="flex-1 overflow-y-auto px-6 py-5 space-y-5">

                {{-- ── STEP: UPLOAD ─────────────────────────────────────────── --}}
                @if ($step === 'upload')

                    <div>
                        <label class="block">
                            <span class="text-sm font-medium text-gray-700">Zeoniq Export File (Excel/CSV)</span>
                            <div class="mt-2 flex justify-center px-6 pt-5 pb-6 border-2 border-dashed border-gray-300 rounded-xl hover:border-indigo-400 transition cursor-pointer">
                                <div class="space-y-1 text-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="mx-auto h-10 w-10 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                    <p class="text-sm text-gray-500">
                                        <span class="font-medium text-indigo-600">Click to browse</span> or drag & drop
                                    </p>
                                    <p class="text-xs text-gray-400">XLSX, XLS, CSV up to 20 MB</p>
                                </div>
                            </div>
                            <input type="file" wire:model="importFile" accept=".xlsx,.xls,.csv" class="sr-only" />
                        </label>
                        <x-input-error :messages="$errors->get('importFile')" class="mt-1" />

                        @if ($importFile)
                            <div class="mt-2 flex items-center gap-2 text-sm text-green-600">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                File selected — ready to process
                            </div>
                        @endif

                        @if ($importError)
                            <div class="mt-3 px-4 py-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">
                                {{ $importError }}
                            </div>
                        @endif
                    </div>

                    <div class="rounded-lg bg-blue-50 border border-blue-100 px-4 py-3 text-xs text-blue-700 space-y-1">
                        <p class="font-medium">Supported Zeoniq Reports:</p>
                        <ul class="list-disc list-inside space-y-0.5 text-blue-600">
                            <li><strong>Session Sales Listing</strong> — imports with meal period breakdown (Breakfast, Lunch, Tea Time, Dinner)</li>
                            <li><strong>Daily Sales Summary</strong> — imports as All Day records</li>
                            <li>Duplicate dates are automatically skipped</li>
                            <li>Multi-outlet support with automatic outlet matching</li>
                        </ul>
                    </div>

                @endif

                {{-- ── STEP: MAPPING ────────────────────────────────────────── --}}
                @if ($step === 'mapping')

                    <div class="space-y-4">

                        {{-- Header Info --}}
                        <div class="rounded-lg bg-blue-50 border border-blue-200 px-4 py-3">
                            <h4 class="text-sm font-semibold text-blue-800">Department Mapping Required</h4>
                            <p class="text-xs text-blue-600 mt-1">
                                {{ count($departmentNames) }} department(s) detected in the Excel file.
                                Map each Zeoniq department to a Servora Sales Category.
                            </p>
                        </div>

                        {{-- AI Suggestions Panel (if available) --}}
                        @if ($aiSuggestionsLoaded && !empty($aiSuggestions))
                        <div class="border border-green-200 rounded-xl overflow-hidden">
                            <div class="px-4 py-3 bg-green-50 border-b border-green-200 flex items-center justify-between">
                                <div>
                                    <h4 class="text-sm font-semibold text-green-800 flex items-center gap-2">
                                        <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-3a1 1 0 00-.867.5 1 1 0 11-1.731-1A3 3 0 0113 8a3.001 3.001 0 01-2 2.83V11a1 1 0 11-2 0v-1a1 1 0 011-1 1 1 0 100-2zm0 8a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
                                        </svg>
                                        AI Suggested Mappings
                                    </h4>
                                    <p class="text-xs text-green-600 mt-0.5">Claude analyzed your departments and suggested these matches</p>
                                </div>
                                <button wire:click="applyAllAiSuggestions"
                                        class="text-xs px-3 py-1 bg-green-600 text-white rounded-md hover:bg-green-700 transition">
                                    Apply High Confidence Matches
                                </button>
                            </div>
                            <div class="p-4 space-y-2">
                                @foreach ($aiSuggestions as $suggestion)
                                <div class="flex items-center gap-3 p-2 rounded-lg
                                    {{ $suggestion['confidence'] === 'high' ? 'bg-green-50' :
                                       ($suggestion['confidence'] === 'medium' ? 'bg-yellow-50' : 'bg-gray-50') }}">
                                    <span class="text-sm font-mono text-gray-700 min-w-[120px]">
                                        {{ $suggestion['zeoniq_department'] }}
                                    </span>
                                    <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                                    </svg>
                                    <span class="text-sm text-gray-600 flex-1">
                                        {{ $suggestion['category_name'] ?? 'No match' }}
                                    </span>
                                    <span class="text-xs px-2 py-1 rounded-full
                                        {{ $suggestion['confidence'] === 'high' ? 'bg-green-100 text-green-700' :
                                           ($suggestion['confidence'] === 'medium' ? 'bg-yellow-100 text-yellow-700' : 'bg-gray-100 text-gray-600') }}">
                                        {{ ucfirst($suggestion['confidence']) }}
                                    </span>
                                    @if ($suggestion['suggested_category_id'])
                                    <button wire:click="applyAiSuggestion('{{ $suggestion['zeoniq_department'] }}')"
                                            class="text-xs text-indigo-600 hover:text-indigo-800">
                                        Apply
                                    </button>
                                    @endif
                                </div>
                                @if (!empty($suggestion['reasoning']))
                                <p class="text-xs text-gray-500 ml-[132px]">{{ $suggestion['reasoning'] }}</p>
                                @endif
                                @endforeach
                            </div>
                        </div>
                        @endif

                        {{-- AI Error Message --}}
                        @if ($aiSuggestionsError)
                        <div class="px-4 py-3 bg-amber-50 border border-amber-200 rounded-lg text-sm text-amber-700">
                            {{ $aiErrorMessage }} — You can still map departments manually below.
                        </div>
                        @endif

                        {{-- Manual Mapping Table --}}
                        <div class="border border-gray-200 rounded-xl overflow-hidden">
                            <div class="px-4 py-3 bg-gray-50 border-b border-gray-200">
                                <h4 class="text-sm font-semibold text-gray-700">Department → Sales Category Mapping</h4>
                                <p class="text-xs text-gray-500 mt-0.5">Select the Sales Category for each department</p>
                            </div>
                            <div class="p-4 space-y-3">
                                @foreach ($departmentNames as $dept)
                                <div class="flex items-center gap-3">
                                    <span class="text-sm font-mono text-gray-700 bg-gray-100 px-3 py-2 rounded min-w-[150px]">
                                        {{ $dept }}
                                    </span>
                                    <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                                    </svg>
                                    <select wire:model.live="departmentMapping.{{ $dept }}"
                                            class="flex-1 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm
                                            {{ !($departmentMapping[$dept] ?? null) ? 'border-red-300 bg-red-50' : '' }}">
                                        <option value="">— Select Sales Category —</option>
                                        @foreach ($salesCategories as $cat)
                                            <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                @endforeach
                            </div>
                        </div>

                        {{-- Validation Errors --}}
                        @if ($errors->has('mapping'))
                        <div class="px-4 py-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">
                            {{ $errors->first('mapping') }}
                        </div>
                        @endif

                        {{-- Info Box --}}
                        <div class="rounded-lg bg-indigo-50 border border-indigo-100 px-4 py-3 text-xs text-indigo-700">
                            <p class="font-medium">About Department Mappings:</p>
                            <ul class="list-disc list-inside space-y-0.5 mt-1 text-indigo-600">
                                <li>Mappings are saved and reused for future imports</li>
                                <li>Each department will create a separate line item in the sales record</li>
                                <li>You can update mappings anytime from Settings (future feature)</li>
                            </ul>
                        </div>

                    </div>

                @endif

                {{-- ── STEP: REVIEW ─────────────────────────────────────────── --}}
                @if ($step === 'review')

                    {{-- Report Type Badge --}}
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            @if ($reportType === 'session_sales')
                                <span class="px-3 py-1 bg-green-100 text-green-700 text-xs font-medium rounded-full">
                                    Session Sales Listing
                                </span>
                                <span class="text-xs text-gray-500">Records will be created per meal period</span>
                            @else
                                <span class="px-3 py-1 bg-indigo-100 text-indigo-700 text-xs font-medium rounded-full">
                                    Daily Summary
                                </span>
                                <span class="text-xs text-gray-500">Records will be created as All Day</span>
                            @endif
                        </div>
                        <div class="text-xs text-gray-500">
                            {{ $this->totalRecordsToCreate }} record(s) to create
                        </div>
                    </div>

                    {{-- Outlet Mapping (if multiple outlets detected) --}}
                    @if (count($outletMapping) > 1 || count($outlets) > 1)
                        <div class="border border-gray-200 rounded-xl overflow-hidden">
                            <div class="px-4 py-3 bg-gray-50 border-b border-gray-200">
                                <h4 class="text-sm font-semibold text-gray-700">Outlet Mapping</h4>
                                <p class="text-xs text-gray-500 mt-0.5">Map Zeoniq outlet codes to your Servora outlets</p>
                            </div>
                            <div class="p-4 space-y-3">
                                @foreach ($outletMapping as $code => $outletId)
                                    <div class="flex items-center gap-3">
                                        <span class="text-sm font-mono text-gray-600 bg-gray-100 px-2 py-1 rounded min-w-[120px]">{{ $code }}</span>
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                                        </svg>
                                        <select wire:model.live="outletMapping.{{ $code }}"
                                                class="flex-1 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                            @foreach ($outlets as $outlet)
                                                <option value="{{ $outlet->id }}">{{ $outlet->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    {{-- Import Error --}}
                    @if ($errors->has('import'))
                        <div class="px-4 py-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">
                            {{ $errors->first('import') }}
                        </div>
                    @endif

                    {{-- Records List --}}
                    <div class="border border-gray-200 rounded-xl overflow-hidden">
                        <div class="px-4 py-3 bg-gray-50 border-b border-gray-200 flex items-center justify-between">
                            <h4 class="text-sm font-semibold text-gray-700">Records to Import</h4>
                            <div class="flex items-center gap-2">
                                <button type="button" wire:click="$set('includeRecords', {{ json_encode(array_fill(0, count($parsedRecords), true)) }})"
                                        class="text-xs text-indigo-600 hover:text-indigo-800">Select All</button>
                                <span class="text-gray-300">|</span>
                                <button type="button" wire:click="$set('includeRecords', {{ json_encode(array_fill(0, count($parsedRecords), false)) }})"
                                        class="text-xs text-gray-500 hover:text-gray-700">Clear All</button>
                            </div>
                        </div>

                        <div class="max-h-96 overflow-y-auto">
                            <table class="min-w-full text-sm">
                                <thead class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wider sticky top-0">
                                    <tr>
                                        <th class="px-4 py-2 text-left w-12"></th>
                                        <th class="px-4 py-2 text-left">Date</th>
                                        <th class="px-4 py-2 text-left">Outlet</th>
                                        @if ($reportType === 'session_sales')
                                            <th class="px-4 py-2 text-left">Sessions</th>
                                        @else
                                            <th class="px-4 py-2 text-right">Gross</th>
                                            <th class="px-4 py-2 text-right">Discount</th>
                                        @endif
                                        <th class="px-4 py-2 text-right">Net Sales</th>
                                        <th class="px-4 py-2 text-right">Total Sales</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @foreach ($parsedRecords as $idx => $record)
                                        @php
                                            $isIncluded = $includeRecords[$idx] ?? false;
                                        @endphp
                                        <tr class="{{ $isIncluded ? '' : 'opacity-50 bg-gray-50' }}">
                                            <td class="px-4 py-3">
                                                <input type="checkbox" wire:model.live="includeRecords.{{ $idx }}"
                                                       class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                                            </td>
                                            <td class="px-4 py-3 font-medium text-gray-800">
                                                {{ \Carbon\Carbon::parse($record['date'])->format('d M Y') }}
                                            </td>
                                            <td class="px-4 py-3 text-gray-600">
                                                <span class="font-mono text-xs bg-gray-100 px-1.5 py-0.5 rounded">{{ $record['outlet_code'] }}</span>
                                            </td>
                                            @if ($reportType === 'session_sales')
                                                <td class="px-4 py-3">
                                                    <div class="flex flex-wrap gap-1">
                                                        @foreach ($record['sessions'] as $session)
                                                            <span class="text-xs px-2 py-0.5 rounded-full
                                                                {{ $session['meal_period'] === 'breakfast' ? 'bg-yellow-100 text-yellow-700' : '' }}
                                                                {{ $session['meal_period'] === 'lunch' ? 'bg-orange-100 text-orange-700' : '' }}
                                                                {{ $session['meal_period'] === 'tea_time' ? 'bg-pink-100 text-pink-700' : '' }}
                                                                {{ $session['meal_period'] === 'dinner' ? 'bg-purple-100 text-purple-700' : '' }}
                                                                {{ $session['meal_period'] === 'supper' ? 'bg-indigo-100 text-indigo-700' : '' }}">
                                                                {{ $mealPeriodOptions[$session['meal_period']] ?? $session['meal_period'] }}
                                                            </span>
                                                        @endforeach
                                                    </div>
                                                </td>
                                                @php
                                                    $totalNet = collect($record['sessions'])->sum('net_sales');
                                                    $totalSales = collect($record['sessions'])->sum('total_sales');
                                                @endphp
                                                <td class="px-4 py-3 text-right tabular-nums">
                                                    RM {{ number_format($totalNet, 2) }}
                                                </td>
                                                <td class="px-4 py-3 text-right tabular-nums font-medium">
                                                    RM {{ number_format($totalSales, 2) }}
                                                </td>
                                            @else
                                                <td class="px-4 py-3 text-right tabular-nums">
                                                    RM {{ number_format($record['gross_revenue'] ?? 0, 2) }}
                                                </td>
                                                <td class="px-4 py-3 text-right tabular-nums text-red-600">
                                                    -RM {{ number_format($record['discount_amount'] ?? 0, 2) }}
                                                </td>
                                                <td class="px-4 py-3 text-right tabular-nums">
                                                    RM {{ number_format($record['net_sales'] ?? 0, 2) }}
                                                </td>
                                                <td class="px-4 py-3 text-right tabular-nums font-medium">
                                                    RM {{ number_format($record['total_sales'] ?? 0, 2) }}
                                                </td>
                                            @endif
                                        </tr>

                                        {{-- Session Details (expandable) --}}
                                        @if ($reportType === 'session_sales' && $isIncluded)
                                            <tr class="bg-gray-50">
                                                <td></td>
                                                <td colspan="5" class="px-4 py-2">
                                                    <div class="text-xs text-gray-500 space-y-1">
                                                        @foreach ($record['sessions'] as $session)
                                                            <div class="flex items-center justify-between">
                                                                <span class="font-medium">{{ $mealPeriodOptions[$session['meal_period']] ?? $session['meal_period'] }}</span>
                                                                <div class="flex items-center gap-4">
                                                                    <span>{{ $session['transactions'] ?? 0 }} trans</span>
                                                                    <span class="text-indigo-600">{{ $session['pax'] ?? $session['transactions'] ?? 0 }} pax</span>
                                                                    <span>Gross: RM {{ number_format($session['gross_revenue'] ?? 0, 2) }}</span>
                                                                    <span>Disc: RM {{ number_format($session['discount_amount'] ?? 0, 2) }}</span>
                                                                    <span>Net: RM {{ number_format($session['net_sales'] ?? 0, 2) }}</span>
                                                                    <span class="font-medium">Total: RM {{ number_format($session['total_sales'] ?? 0, 2) }}</span>
                                                                </div>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                </td>
                                            </tr>
                                        @endif
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        {{-- Summary Footer --}}
                        @php
                            $selectedRecords = collect($parsedRecords)->filter(fn($r, $i) => $includeRecords[$i] ?? false);
                            if ($reportType === 'session_sales') {
                                $grandTotalNet = $selectedRecords->sum(fn($r) => collect($r['sessions'])->sum('net_sales'));
                                $grandTotalSales = $selectedRecords->sum(fn($r) => collect($r['sessions'])->sum('total_sales'));
                            } else {
                                $grandTotalNet = $selectedRecords->sum('net_sales');
                                $grandTotalSales = $selectedRecords->sum('total_sales');
                            }
                        @endphp
                        <div class="px-4 py-3 bg-gray-50 border-t-2 border-gray-200 flex items-center justify-between text-sm">
                            <span class="text-gray-600">
                                {{ $this->recordCount }} date(s) selected ({{ $this->totalRecordsToCreate }} records)
                            </span>
                            <div class="flex items-center gap-6 tabular-nums">
                                <div class="text-right">
                                    <span class="text-xs text-gray-400 block">Net Sales</span>
                                    <span class="font-semibold text-gray-700">RM {{ number_format($grandTotalNet, 2) }}</span>
                                </div>
                                <div class="text-right">
                                    <span class="text-xs text-gray-400 block">Total Sales</span>
                                    <span class="font-bold text-green-700">RM {{ number_format($grandTotalSales, 2) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Duplicate Warning --}}
                    <div class="rounded-lg bg-amber-50 border border-amber-200 px-4 py-3 text-xs text-amber-700 flex items-start gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <div>
                            <span class="font-medium">Duplicate Protection:</span> Records for dates that already exist will be automatically skipped to prevent duplicates.
                        </div>
                    </div>

                @endif

            </div>

            {{-- Footer --}}
            <div class="flex items-center justify-between gap-3 px-6 py-4 border-t border-gray-100 bg-gray-50 rounded-b-xl flex-shrink-0">
                <button type="button" wire:click="close"
                        class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800 transition">
                    Cancel
                </button>

                @if ($step === 'upload')
                    <button type="button" wire:click="processFile"
                            wire:loading.attr="disabled"
                            wire:target="processFile,importFile"
                            class="px-5 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2">
                        <span wire:loading.remove wire:target="processFile">Process File</span>
                        <span wire:loading wire:target="processFile" class="flex items-center gap-1.5">
                            <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 12 0 12 0v4a8 8 0 00-8 8H0z"></path>
                            </svg>
                            Processing...
                        </span>
                    </button>
                @elseif ($step === 'mapping')
                    <div class="flex items-center gap-2">
                        <button type="button" wire:click="$set('step', 'upload')"
                                class="px-4 py-2 text-sm text-gray-500 hover:text-gray-700 border border-gray-300 rounded-lg transition">
                            &larr; Re-upload
                        </button>
                        <button type="button" wire:click="proceedToReview"
                                class="px-5 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
                            Continue to Review &rarr;
                        </button>
                    </div>
                @else
                    <div class="flex items-center gap-2">
                        <button type="button" wire:click="$set('step', 'upload')"
                                class="px-4 py-2 text-sm text-gray-500 hover:text-gray-700 border border-gray-300 rounded-lg transition">
                            &larr; Re-upload
                        </button>
                        <button type="button" wire:click="saveAll"
                                wire:loading.attr="disabled"
                                class="px-5 py-2 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700 transition disabled:opacity-50 flex items-center gap-2">
                            <span wire:loading.remove wire:target="saveAll">Import {{ $this->totalRecordsToCreate }} Record(s)</span>
                            <span wire:loading wire:target="saveAll" class="flex items-center gap-1.5">
                                <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 12 0 12 0v4a8 8 0 00-8 8H0z"></path>
                                </svg>
                                Importing...
                            </span>
                        </button>
                    </div>
                @endif
            </div>

        </div>
        </div>
        </div>
    </div>
    @endteleport
    @endif
</div>
