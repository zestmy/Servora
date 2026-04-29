{{-- Z-Report Import Modal --}}
<div>
    @if ($showModal)
    @teleport('body')
    <div class="fixed inset-0 z-50">
        <div class="fixed inset-0 bg-gray-900/60" wire:click="close"></div>
        <div class="fixed inset-0 overflow-y-auto">
        <div class="flex min-h-full items-center justify-center p-4">

        <div class="relative bg-white rounded-xl shadow-xl w-full max-w-3xl z-10 max-h-[92vh] flex flex-col">

            {{-- Header --}}
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 flex-shrink-0">
                <div>
                    <h3 class="text-base font-semibold text-gray-800">Import Z-Report</h3>
                    <p class="text-xs text-gray-400 mt-0.5">
                        @if ($step === 'upload')
                            Upload your daily Z-report — AI will extract dept breakdown and session totals
                        @else
                            Review extracted data, edit if needed, then save all records
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
                            <span class="text-sm font-medium text-gray-700">Z-Report Image or PDF</span>
                            <div class="mt-2 flex justify-center px-6 pt-5 pb-6 border-2 border-dashed border-gray-300 rounded-xl hover:border-indigo-400 transition cursor-pointer">
                                <div class="space-y-1 text-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="mx-auto h-10 w-10 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                    <p class="text-sm text-gray-500">
                                        <span class="font-medium text-indigo-600">Click to browse</span> or drag & drop
                                    </p>
                                    <p class="text-xs text-gray-400">JPG, PNG, PDF up to 20 MB</p>
                                </div>
                            </div>
                            <input type="file" wire:model="importFile" accept="image/*,.pdf" class="sr-only" />
                        </label>
                        <x-input-error :messages="$errors->get('importFile')" class="mt-1" />

                        @if ($importFile)
                            <div class="mt-2 flex items-center gap-2 text-sm text-green-600">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                File selected — ready to extract
                            </div>
                        @endif

                        @if ($importError)
                            <div class="mt-3 px-4 py-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">
                                {{ $importError }}
                            </div>
                        @endif
                    </div>

                    <div class="rounded-lg bg-blue-50 border border-blue-100 px-4 py-3 text-xs text-blue-700 space-y-1">
                        <p class="font-medium">What gets extracted:</p>
                        <ul class="list-disc list-inside space-y-0.5 text-blue-600">
                            <li><strong>All Day entry</strong> — dept breakdown from Department Sales Z-Read (Food / Beverage / Dessert etc.)</li>
                            <li><strong>Session entries</strong> — Breakfast / Lunch / Tea Time totals from Session Report</li>
                            <li>You can review and edit everything before saving</li>
                        </ul>
                    </div>

                @endif

                {{-- ── STEP: REVIEW ─────────────────────────────────────────── --}}
                @if ($step === 'review')

                    {{-- Date row --}}
                    <div class="flex items-center gap-4">
                        <div class="flex-1">
                            <x-input-label for="imp_date" value="Z-Report Date *" />
                            <x-text-input id="imp_date" wire:model="importDate" type="date" class="mt-1 block w-full" />
                            <x-input-error :messages="$errors->get('importDate')" class="mt-1" />
                        </div>
                        <div class="flex-1">
                            <x-input-label for="imp_ref" value="Reference (optional)" />
                            <x-text-input id="imp_ref" wire:model="allDayReference" type="text"
                                          class="mt-1 block w-full" placeholder="e.g. Z-read #42" />
                        </div>
                    </div>

                    {{-- ── SESSION SUPPRESSED BANNER ── --}}
                    @if ($this->hasSessionEntries())
                        <div class="flex items-start gap-3 px-4 py-3 bg-amber-50 border border-amber-200 rounded-xl text-sm text-amber-800">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-amber-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <div>
                                <span class="font-semibold">Session records detected</span> — All Day entry is suppressed to avoid double-counting revenue for this day.
                                Only the session records below will be saved.
                            </div>
                        </div>
                    @endif

                    {{-- ── ALL DAY ENTRY ── --}}
                    <div class="border rounded-xl overflow-hidden {{ $this->hasSessionEntries() ? 'border-gray-100 opacity-40 pointer-events-none' : 'border-gray-200' }}">
                        <div class="flex items-center justify-between px-4 py-3 bg-indigo-50 border-b border-indigo-100">
                            <div class="flex items-center gap-3">
                                <input type="checkbox" wire:model.live="includeAllDay" id="inc_allday"
                                       class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                                       {{ $this->hasSessionEntries() ? 'disabled' : '' }} />
                                <label for="inc_allday" class="text-sm font-semibold text-indigo-800 cursor-pointer">
                                    All Day Entry — Dept Breakdown
                                </label>
                                <span class="text-xs text-indigo-500 bg-indigo-100 px-2 py-0.5 rounded-full">For food cost% tracking</span>
                                @if ($this->hasSessionEntries())
                                    <span class="text-xs text-amber-600 bg-amber-100 px-2 py-0.5 rounded-full">Suppressed</span>
                                @endif
                            </div>
                            <div class="flex items-center gap-3">
                                <div>
                                    <label class="text-xs text-indigo-600">Total Bills / Pax</label>
                                    <input type="number" wire:model="allDayPax" min="1" step="1"
                                           class="ml-2 w-16 text-center rounded border-indigo-300 text-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                </div>
                            </div>
                        </div>

                        @if ($includeAllDay)
                            <table class="min-w-full text-sm">
                                <thead class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wider">
                                    <tr>
                                        <th class="px-4 py-2 text-left">Dept (from Z-Report)</th>
                                        <th class="px-4 py-2 text-left w-48">Map to Category</th>
                                        <th class="px-4 py-2 text-right w-40">Net Revenue (RM)</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-50">
                                    @foreach ($allDayLines as $idx => $line)
                                        <tr class="{{ $line['unmatched'] ? 'bg-amber-50' : '' }}">
                                            <td class="px-4 py-2">
                                                <div class="flex items-center gap-2">
                                                    @if (!$line['unmatched'])
                                                        <div class="w-2.5 h-2.5 rounded-full flex-shrink-0"
                                                             style="background-color: {{ $line['category_color'] }}"></div>
                                                        <span class="font-medium text-gray-800">{{ $line['category_name'] }}</span>
                                                    @else
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-amber-500 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                                        </svg>
                                                        <span class="font-medium text-amber-700">{{ $line['category_name'] }}</span>
                                                        <span class="text-xs text-amber-500">unmatched</span>
                                                    @endif
                                                </div>
                                            </td>
                                            <td class="px-4 py-2">
                                                <select wire:model.live="allDayLines.{{ $idx }}.ingredient_category_id"
                                                        class="w-full rounded border-gray-300 text-xs focus:border-indigo-500 focus:ring-indigo-500 py-1
                                                               {{ $line['unmatched'] ? 'border-amber-300 bg-amber-50' : '' }}">
                                                    <option value="">— Skip / Uncategorised —</option>
                                                    @foreach ($categories as $cat)
                                                        <option value="{{ $cat->id }}"
                                                            {{ (string)($line['ingredient_category_id'] ?? '') === (string)$cat->id ? 'selected' : '' }}>
                                                            {{ $cat->name }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </td>
                                            <td class="px-4 py-2">
                                                <input type="number" step="0.01" min="0"
                                                       wire:model.live.debounce.400ms="allDayLines.{{ $idx }}.revenue"
                                                       class="w-full text-right rounded border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                                <x-input-error :messages="$errors->get('allDayLines.'.$idx.'.revenue')" class="mt-0.5" />
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot class="bg-gray-50 border-t-2 border-gray-200">
                                    <tr>
                                        <td colspan="2" class="px-4 py-2 text-sm font-semibold text-gray-700">Total Net Sales</td>
                                        <td class="px-4 py-2 text-right font-bold text-gray-900 tabular-nums">
                                            RM {{ number_format(collect($allDayLines)->sum(fn($l) => floatval($l['revenue'])), 2) }}
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        @endif
                    </div>

                    {{-- ── SESSION ENTRIES ── --}}
                    @if (count($sessionEntries) > 0)
                        @php
                            $summaryGross = floatval($summary['gross_amount'] ?? 0);
                            $summaryNet   = floatval($summary['net_sales']    ?? 0);
                            $netRatio     = ($summaryGross > 0 && $summaryNet > 0) ? $summaryNet / $summaryGross : 1.0;
                        @endphp
                        <div class="border border-green-200 rounded-xl overflow-hidden">
                            <div class="px-4 py-3 bg-green-50 border-b border-green-100 flex items-center justify-between">
                                <div>
                                    <h4 class="text-sm font-semibold text-green-800">Session Entries</h4>
                                    <p class="text-xs text-green-600 mt-0.5">
                                        Gross amounts are inclusive of tax &amp; charges.
                                        Net revenue is back-calculated using the day's net-to-gross ratio
                                        @if ($summaryGross > 0)
                                            ({{ number_format($netRatio * 100, 1) }}%).
                                        @else
                                            .
                                        @endif
                                    </p>
                                </div>
                                <div class="text-right text-xs text-green-700 bg-green-100 px-3 py-1.5 rounded-lg">
                                    <span class="font-semibold">{{ collect($sessionEntries)->where('include', true)->count() }}</span> session(s) included
                                </div>
                            </div>

                            <div class="divide-y divide-gray-100">
                                @foreach ($sessionEntries as $idx => $entry)
                                    @php
                                        $sessGross = floatval($entry['gross_amount'] ?? 0);
                                        $sessNet   = round($sessGross * $netRatio, 2);
                                        $sessPax   = max((int)($entry['pax'] ?? 1), 1);
                                        $sessAvg   = $sessNet > 0 ? round($sessNet / $sessPax, 2) : null;
                                    @endphp
                                    <div class="px-4 py-4 {{ empty($entry['include']) ? 'opacity-50' : '' }}">
                                        <div class="flex items-start gap-3">
                                            <input type="checkbox" wire:model.live="sessionEntries.{{ $idx }}.include"
                                                   class="rounded border-gray-300 text-green-600 shadow-sm focus:ring-green-500 flex-shrink-0 mt-1" />

                                            <div class="flex-1 grid grid-cols-2 sm:grid-cols-4 gap-3">
                                                {{-- Meal Period --}}
                                                <div>
                                                    <label class="text-xs text-gray-500 block mb-0.5">Meal Period *</label>
                                                    <select wire:model="sessionEntries.{{ $idx }}.meal_period"
                                                            class="w-full rounded border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500 py-1
                                                                   {{ empty($entry['meal_period']) ? 'border-red-300 bg-red-50' : '' }}">
                                                        <option value="">— Select —</option>
                                                        @foreach ($mealPeriodOptions as $val => $label)
                                                            @if ($val !== 'all_day')
                                                                <option value="{{ $val }}" {{ $entry['meal_period'] === $val ? 'selected' : '' }}>{{ $label }}</option>
                                                            @endif
                                                        @endforeach
                                                    </select>
                                                    @if (empty($entry['meal_period']))
                                                        <p class="text-xs text-red-500 mt-0.5">Required</p>
                                                    @endif
                                                    <x-input-error :messages="$errors->get('sessionEntries.'.$idx.'.meal_period')" class="mt-0.5" />
                                                </div>

                                                {{-- Gross Amount --}}
                                                <div>
                                                    <label class="text-xs text-gray-500 block mb-0.5">
                                                        Gross Amount (RM)
                                                        <span class="text-gray-400">incl. tax &amp; charges</span>
                                                    </label>
                                                    <input type="number" step="0.01" min="0"
                                                           wire:model.live.debounce.300ms="sessionEntries.{{ $idx }}.gross_amount"
                                                           class="w-full rounded border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                                    <x-input-error :messages="$errors->get('sessionEntries.'.$idx.'.gross_amount')" class="mt-0.5" />
                                                </div>

                                                {{-- Pax + Transactions --}}
                                                <div>
                                                    <label class="text-xs text-gray-500 block mb-0.5">Pax</label>
                                                    <input type="number" step="1" min="1"
                                                           wire:model="sessionEntries.{{ $idx }}.pax"
                                                           class="w-full rounded border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                                    <x-input-error :messages="$errors->get('sessionEntries.'.$idx.'.pax')" class="mt-0.5" />
                                                </div>

                                                <div>
                                                    <label class="text-xs text-gray-500 block mb-0.5">Transactions</label>
                                                    <input type="number" step="1" min="1"
                                                           wire:model="sessionEntries.{{ $idx }}.transactions"
                                                           class="w-full rounded border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                                    <x-input-error :messages="$errors->get('sessionEntries.'.$idx.'.transactions')" class="mt-0.5" />
                                                </div>
                                            </div>
                                        </div>

                                        {{-- Net preview row --}}
                                        @if ($sessGross > 0)
                                            <div class="mt-2 ml-7 flex items-center gap-4 text-xs">
                                                <div class="flex items-center gap-1.5 text-gray-500">
                                                    <span>Net saved to DB:</span>
                                                    <span class="font-semibold text-green-700">RM {{ number_format($sessNet, 2) }}</span>
                                                </div>
                                                @if ($sessAvg)
                                                    <div class="flex items-center gap-1.5 text-gray-500">
                                                        <span>Avg/pax:</span>
                                                        <span class="font-semibold text-indigo-600">RM {{ number_format($sessAvg, 2) }}</span>
                                                    </div>
                                                @endif
                                                <div class="text-gray-400 italic">
                                                    {{ $entry['label'] ?? '' }}
                                                </div>
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>

                            {{-- Session totals footer --}}
                            @php
                                $totalSessionGross = collect($sessionEntries)->where('include', true)->sum(fn($e) => floatval($e['gross_amount'] ?? 0));
                                $totalSessionNet   = round($totalSessionGross * $netRatio, 2);
                            @endphp
                            <div class="px-4 py-3 bg-gray-50 border-t-2 border-gray-200 flex items-center justify-between text-sm">
                                <span class="text-gray-600">Included sessions total</span>
                                <div class="flex items-center gap-6 tabular-nums">
                                    <div class="text-right">
                                        <span class="text-xs text-gray-400 block">Gross</span>
                                        <span class="font-semibold text-gray-700">RM {{ number_format($totalSessionGross, 2) }}</span>
                                    </div>
                                    <div class="text-right">
                                        <span class="text-xs text-gray-400 block">Net (saved)</span>
                                        <span class="font-bold text-green-700">RM {{ number_format($totalSessionNet, 2) }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif

                    {{-- Summary --}}
                    @php
                        $hasSessions  = $this->hasSessionEntries();
                        $totalRecords = $hasSessions
                            ? collect($sessionEntries)->where('include', true)->count()
                            : ($includeAllDay ? 1 : 0);
                    @endphp
                    <div class="rounded-lg bg-gray-50 border border-gray-200 px-4 py-3 text-sm text-gray-600 flex items-center justify-between">
                        <div>
                            <span class="font-medium text-gray-800">{{ $totalRecords }}</span> record(s) will be created for
                            <span class="font-medium text-gray-800">{{ \Carbon\Carbon::parse($importDate)->format('d M Y') }}</span>
                        </div>
                        @if ($hasSessions)
                            <span class="text-xs text-green-600 bg-green-50 border border-green-200 px-2 py-1 rounded-full">Session priority active</span>
                        @endif
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
                    <button type="button" wire:click="processZReport"
                            wire:loading.attr="disabled"
                            wire:target="processZReport,importFile"
                            class="px-5 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2">
                        <span wire:loading.remove wire:target="processZReport">Extract Data</span>
                        <span wire:loading wire:target="processZReport" class="flex items-center gap-1.5">
                            <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 12 0 12 0v4a8 8 0 00-8 8H0z"></path>
                            </svg>
                            Extracting…
                        </span>
                    </button>
                @else
                    <div class="flex items-center gap-2">
                        <button type="button" wire:click="$set('step', 'upload')"
                                class="px-4 py-2 text-sm text-gray-500 hover:text-gray-700 border border-gray-300 rounded-lg transition">
                            ← Re-upload
                        </button>
                        <button type="button" wire:click="saveAll"
                                wire:loading.attr="disabled"
                                class="px-5 py-2 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700 transition disabled:opacity-50 flex items-center gap-2">
                            @php
                                $footerRecords = $this->hasSessionEntries()
                                    ? collect($sessionEntries)->where('include', true)->count()
                                    : ($includeAllDay ? 1 : 0);
                            @endphp
                            <span wire:loading.remove wire:target="saveAll">Save {{ $footerRecords }} Record(s)</span>
                            <span wire:loading wire:target="saveAll" class="flex items-center gap-1.5">
                                <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 12 0 12 0v4a8 8 0 00-8 8H0z"></path>
                                </svg>
                                Saving…
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
