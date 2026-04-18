<div x-data="{ preview: null, previewType: null, previewName: null, attachments: [], showAttachments: false }">
    {{-- Z-Report Import Component --}}
    @livewire('sales.z-report-import')

    {{-- Flash --}}
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

    {{-- Header --}}
    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        <h2 class="text-lg font-semibold text-gray-700">Sales</h2>
        <div class="flex flex-wrap items-center gap-2">
            <button wire:click="exportPdf" wire:loading.attr="disabled"
                    title="Export PDF"
                    class="px-2.5 md:px-4 py-2 bg-white border border-gray-300 text-gray-600 text-sm font-medium rounded-lg hover:bg-gray-50 transition flex items-center gap-2 disabled:opacity-50">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                <span wire:loading.remove wire:target="exportPdf" class="hidden sm:inline">Export PDF</span>
                <span wire:loading.remove wire:target="exportPdf" class="sm:hidden">PDF</span>
                <span wire:loading wire:target="exportPdf">…</span>
            </button>
            <button wire:click="exportCsv"
                    title="Export CSV"
                    class="px-2.5 md:px-4 py-2 bg-white border border-gray-300 text-gray-600 text-sm font-medium rounded-lg hover:bg-gray-50 transition flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                <span class="hidden sm:inline">Export CSV</span>
                <span class="sm:hidden">CSV</span>
            </button>
            <a href="{{ route('sales.import') }}"
               title="Import CSV"
               class="px-2.5 md:px-4 py-2 bg-white border border-gray-300 text-gray-600 text-sm font-medium rounded-lg hover:bg-gray-50 transition flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a2 2 0 002 2h12a2 2 0 002-2v-1M12 12v6m0 0l-3-3m3 3l3-3M12 4v4" />
                </svg>
                <span class="hidden sm:inline">Import CSV</span>
                <span class="sm:hidden">Import</span>
            </a>
            <button wire:click="$dispatch('open-z-import')"
                    title="Import Z-Report"
                    class="px-2.5 md:px-4 py-2 bg-white border border-gray-300 text-gray-600 text-sm font-medium rounded-lg hover:bg-gray-50 transition flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                </svg>
                <span class="hidden sm:inline">Import Z-Report</span>
                <span class="sm:hidden">Z-Report</span>
            </button>
            <a href="{{ route('sales.create') }}"
               class="px-3 md:px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
                <span class="sm:hidden">+ New</span>
                <span class="hidden sm:inline">+ New Entry</span>
            </a>
        </div>
    </div>

    {{-- Stats --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <p class="text-xs text-gray-400 uppercase tracking-wider">Revenue</p>
            <p class="text-2xl font-bold text-gray-800 mt-1 tabular-nums">RM {{ number_format($filteredRevenue, 2) }}</p>
            <p class="text-xs text-gray-400 mt-1">{{ $periodLabel }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <p class="text-xs text-gray-400 uppercase tracking-wider">Pax</p>
            <p class="text-2xl font-bold text-gray-800 mt-1 tabular-nums">{{ $filteredPax > 0 ? number_format($filteredPax) : '—' }}</p>
            <p class="text-xs text-gray-400 mt-1">{{ $periodLabel }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <p class="text-xs text-gray-400 uppercase tracking-wider">Avg Check</p>
            @if ($filteredAvgCheck !== null)
                <p class="text-2xl font-bold text-indigo-600 mt-1 tabular-nums">RM {{ number_format($filteredAvgCheck, 2) }}</p>
            @else
                <p class="text-2xl font-bold text-gray-300 mt-1">—</p>
            @endif
            <p class="text-xs text-gray-400 mt-1">per person</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <p class="text-xs text-gray-400 uppercase tracking-wider">Records</p>
            <p class="text-2xl font-bold text-gray-800 mt-1 tabular-nums">{{ number_format($filteredCount) }}</p>
            <p class="text-xs text-gray-400 mt-1">{{ $periodLabel }}</p>
        </div>
    </div>

    {{-- Quick Range --}}
    <div class="flex items-center gap-1.5 mb-3 flex-wrap">
        @foreach ([
            'today'      => 'Today',
            'yesterday'  => 'Yesterday',
            'last_7'     => 'Last 7 Days',
            'this_week'  => 'This Week',
            'this_month' => 'This Month',
            'last_month' => 'Last Month',
            'this_year'  => 'This Year',
            'last_year'  => 'Last Year',
            'all'        => 'All',
        ] as $rangeKey => $rangeLabel)
            <button wire:click="setQuickRange('{{ $rangeKey }}')"
                    class="px-3 py-1.5 text-xs font-medium rounded-lg border transition
                           {{ $quickRange === $rangeKey
                               ? 'bg-indigo-600 text-white border-indigo-600'
                               : 'bg-white text-gray-600 border-gray-200 hover:bg-gray-50' }}">
                {{ $rangeLabel }}
            </button>
        @endforeach
    </div>

    {{-- Filter Bar --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 mb-4">
        <div class="flex flex-col sm:flex-row gap-3">
            <div class="flex-1">
                <input type="text" wire:model.live.debounce.300ms="search"
                       placeholder="Search reference number…"
                       class="w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
            </div>
            <div>
                <select wire:model.live="mealPeriodFilter"
                        class="rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">All Periods</option>
                    @foreach ($mealPeriodOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-center gap-1">
                <input type="date" wire:model.live="dateFrom"
                       class="rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                <span class="text-gray-400 text-xs">to</span>
                <input type="date" wire:model.live="dateTo"
                       class="rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
            </div>
        </div>
    </div>

    {{-- Category Breakdown + Events + Missing Dates --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-4">
        {{-- Sales by Category --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <h3 class="text-xs text-gray-400 uppercase tracking-wider mb-3">Sales by Category</h3>
            @if (!empty($categoryRevenues))
                <div class="space-y-2">
                    @foreach ($categoryRevenues as $catRev)
                        <div>
                            <div class="flex items-center justify-between text-sm mb-1">
                                <span class="text-gray-700 font-medium">{{ $catRev['name'] }}</span>
                                <span class="tabular-nums text-gray-600">RM {{ number_format($catRev['revenue'], 2) }} <span class="text-xs text-gray-400">({{ $catRev['pct'] }}%)</span></span>
                            </div>
                            <div class="w-full bg-gray-100 rounded-full h-2">
                                <div class="h-2 rounded-full" style="width: {{ $catRev['pct'] }}%; background-color: {{ $catRev['color'] }};"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="text-sm text-gray-300">No category data</p>
            @endif
        </div>

        {{-- Calendar Events --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <h3 class="text-xs text-gray-400 uppercase tracking-wider mb-3">Events in Period</h3>
            @if (!empty($events))
                <div class="space-y-2 max-h-48 overflow-y-auto">
                    @foreach ($events as $event)
                        @php
                            $impactColors = [
                                'positive' => 'border-green-300 bg-green-50',
                                'negative' => 'border-red-300 bg-red-50',
                                'neutral'  => 'border-gray-200 bg-gray-50',
                            ];
                            $impactDot = [
                                'positive' => 'bg-green-500',
                                'negative' => 'bg-red-500',
                                'neutral'  => 'bg-gray-400',
                            ];
                        @endphp
                        <div class="px-3 py-2 rounded-lg border text-xs {{ $impactColors[$event['impact']] ?? 'border-gray-200 bg-gray-50' }}">
                            <div class="flex items-start gap-2">
                                <span class="w-2 h-2 rounded-full mt-1 flex-shrink-0 {{ $impactDot[$event['impact']] ?? 'bg-gray-400' }}"></span>
                                <div class="min-w-0">
                                    <p class="font-medium text-gray-800 truncate">{{ $event['title'] }}</p>
                                    <p class="text-gray-500">{{ $event['date'] }}{{ $event['end_date'] ? ' — ' . $event['end_date'] : '' }} · {{ $event['category'] }}</p>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="text-sm text-gray-300">No events in this period</p>
            @endif
        </div>

        {{-- Missing Dates --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <h3 class="text-xs text-gray-400 uppercase tracking-wider mb-3">Missing Sales Dates</h3>
            @if (!empty($missingDatesData))
                <div class="space-y-1.5 max-h-48 overflow-y-auto">
                    @foreach ($missingDatesData as $md)
                        <div class="flex items-start gap-2 text-xs group">
                            <span class="w-1.5 h-1.5 rounded-full mt-1.5 flex-shrink-0 {{ $md['reason'] ? 'bg-blue-400' : 'bg-amber-400' }}"></span>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-1.5">
                                    <span class="text-gray-600">{{ $md['label'] }}</span>
                                    <button wire:click="openClosureModal('{{ $md['date'] }}')"
                                            class="opacity-0 group-hover:opacity-100 text-indigo-400 hover:text-indigo-600 transition"
                                            title="{{ $md['reason'] ? 'Edit reason' : 'Add reason' }}">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                    </button>
                                </div>
                                @if ($md['reason'])
                                    <span class="text-blue-600 font-medium">{{ $md['reason'] }}</span>
                                    @if ($md['notes'])
                                        <span class="text-gray-400"> — {{ $md['notes'] }}</span>
                                    @endif
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
                @php
                    $untagged = collect($missingDatesData)->whereNull('reason')->count();
                    $tagged   = collect($missingDatesData)->whereNotNull('reason')->count();
                @endphp
                <div class="flex items-center gap-3 mt-2 text-xs">
                    @if ($untagged > 0)
                        <span class="text-amber-600 font-medium">{{ $untagged }} untagged</span>
                    @endif
                    @if ($tagged > 0)
                        <span class="text-blue-600 font-medium">{{ $tagged }} tagged</span>
                    @endif
                    <span class="text-gray-400">{{ count($missingDatesData) }} total missing</span>
                </div>
            @else
                @if ($dateFrom && $dateTo)
                    <div class="flex items-center gap-2 text-sm text-green-600">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                        All dates have sales data
                    </div>
                @else
                    <p class="text-sm text-gray-300">Select a date range to check</p>
                @endif
            @endif
        </div>
    </div>

    {{-- Target Progress + AI Predictive --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-4">
        {{-- Sales Target Progress --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <h3 class="text-xs text-gray-400 uppercase tracking-wider mb-3">Sales Target</h3>
            @if (!empty($targetData))
                <div class="space-y-3">
                    {{-- Revenue Target --}}
                    <div>
                        <div class="flex items-center justify-between text-sm mb-1">
                            <span class="text-gray-600">Revenue</span>
                            <span class="tabular-nums font-medium">
                                <span class="{{ $targetData['revenue_pct'] >= 100 ? 'text-green-600' : 'text-gray-800' }}">RM {{ number_format($targetData['actual'], 2) }}</span>
                                <span class="text-gray-400"> / RM {{ number_format($targetData['revenue'], 2) }}</span>
                            </span>
                        </div>
                        <div class="w-full bg-gray-100 rounded-full h-3">
                            <div class="h-3 rounded-full transition-all duration-500 {{ $targetData['revenue_pct'] >= 100 ? 'bg-green-500' : ($targetData['revenue_pct'] >= 75 ? 'bg-indigo-500' : ($targetData['revenue_pct'] >= 50 ? 'bg-amber-500' : 'bg-red-400')) }}"
                                 style="width: {{ min($targetData['revenue_pct'], 100) }}%"></div>
                        </div>
                        <p class="text-xs mt-1 {{ $targetData['revenue_pct'] >= 100 ? 'text-green-600 font-medium' : 'text-gray-400' }}">
                            {{ $targetData['revenue_pct'] }}% achieved
                            @if ($targetData['revenue_pct'] < 100)
                                · RM {{ number_format($targetData['revenue'] - $targetData['actual'], 2) }} remaining
                            @endif
                        </p>
                    </div>

                    {{-- Pax Target --}}
                    @if ($targetData['pax_pct'] !== null)
                        <div>
                            <div class="flex items-center justify-between text-sm mb-1">
                                <span class="text-gray-600">Pax</span>
                                <span class="tabular-nums font-medium">
                                    <span class="{{ $targetData['pax_pct'] >= 100 ? 'text-green-600' : 'text-gray-800' }}">{{ number_format($targetData['actual_pax']) }}</span>
                                    <span class="text-gray-400"> / {{ number_format($targetData['pax']) }}</span>
                                </span>
                            </div>
                            <div class="w-full bg-gray-100 rounded-full h-2">
                                <div class="h-2 rounded-full transition-all duration-500 {{ $targetData['pax_pct'] >= 100 ? 'bg-green-500' : 'bg-indigo-400' }}"
                                     style="width: {{ min($targetData['pax_pct'], 100) }}%"></div>
                            </div>
                            <p class="text-xs mt-1 text-gray-400">{{ $targetData['pax_pct'] }}% achieved</p>
                        </div>
                    @endif

                    @if ($targetData['notes'])
                        <p class="text-xs text-gray-400 italic">{{ $targetData['notes'] }}</p>
                    @endif
                </div>
            @else
                <div class="text-sm text-gray-300">
                    <p>No target set for this period</p>
                    <a href="{{ route('settings.sales-targets') }}" class="text-xs text-indigo-400 hover:text-indigo-600 underline mt-1 inline-block">Set sales target</a>
                </div>
            @endif
        </div>

        {{-- AI Predictive Sales --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-xs text-gray-400 uppercase tracking-wider">Predictive Sales</h3>
                <button wire:click="generatePrediction" wire:loading.attr="disabled" wire:target="generatePrediction"
                        class="px-2.5 py-1 text-xs font-medium rounded-lg border transition
                               {{ $prediction ? 'bg-white text-gray-500 border-gray-200 hover:bg-gray-50' : 'bg-indigo-600 text-white border-indigo-600 hover:bg-indigo-700' }}
                               disabled:opacity-50">
                    <span wire:loading.remove wire:target="generatePrediction">{{ $prediction ? 'Refresh' : 'Generate' }}</span>
                    <span wire:loading wire:target="generatePrediction">Analyzing...</span>
                </button>
            </div>

            @if ($loadingPrediction)
                <div class="flex items-center gap-2 text-sm text-gray-400 py-4">
                    <svg class="animate-spin h-4 w-4 text-indigo-500" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    AI is analyzing historical data...
                </div>
            @elseif ($predictionError)
                <div class="text-sm text-red-500 bg-red-50 rounded-lg px-3 py-2">{{ $predictionError }}</div>
            @elseif ($prediction)
                <div class="prose prose-sm prose-gray max-w-none text-xs leading-relaxed max-h-64 overflow-y-auto">
                    {!! \Illuminate\Support\Str::markdown($prediction['response']) !!}
                </div>
                <div class="flex items-center gap-3 mt-2 pt-2 border-t border-gray-100 text-xs text-gray-400">
                    <span>Powered by Servora AI</span>
                    @if ($prediction['cached'])
                        <span class="text-amber-500">Cached</span>
                    @endif
                    <span>{{ $prediction['created_at'] }}</span>
                </div>
            @else
                <div class="text-sm text-gray-300 py-4">
                    <p>Click <span class="font-medium">Generate</span> to get AI-powered sales predictions based on your historical data</p>
                </div>
            @endif
        </div>
    </div>

    {{-- Bulk Delete Bar --}}
    @if ($canDelete && count($selected) > 0)
        <div class="mb-3 px-4 py-3 bg-red-50 border border-red-200 rounded-xl flex items-center justify-between">
            <span class="text-sm text-red-700 font-medium">{{ count($selected) }} record{{ count($selected) !== 1 ? 's' : '' }} selected</span>
            <div class="flex items-center gap-2">
                <button wire:click="$set('selected', [])"
                        class="px-3 py-1.5 text-xs font-medium text-gray-600 border border-gray-200 rounded-lg hover:bg-white transition">
                    Clear Selection
                </button>
                <button wire:click="bulkDelete"
                        wire:confirm="Delete {{ count($selected) }} selected sales record(s)? This cannot be undone."
                        class="px-3 py-1.5 text-xs font-medium text-white bg-red-600 rounded-lg hover:bg-red-700 transition">
                    Delete Selected
                </button>
            </div>
        </div>
    @endif

    {{-- Table — horizontally scrollable on mobile so all columns are reachable. --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
      <div class="overflow-x-auto">
        <table class="min-w-[960px] divide-y divide-gray-100 text-sm">
            <thead class="bg-gray-50 text-gray-500 uppercase text-xs tracking-wider">
                <tr>
                    @if ($canDelete)
                        <th class="px-3 py-3 w-10">
                            <input type="checkbox"
                                   wire:model.live="selectAll"
                                   x-on:change="
                                       const checked = $event.target.checked;
                                       document.querySelectorAll('input[name=row-select]').forEach(cb => {
                                           cb.checked = checked;
                                           cb.dispatchEvent(new Event('change'));
                                       });
                                   "
                                   class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                        </th>
                    @endif
                    <th class="px-4 py-3 text-left">Date</th>
                    <th class="px-4 py-3 text-left">Meal Period</th>
                    <th class="px-4 py-3 text-center">Pax</th>
                    <th class="px-4 py-3 text-left">Categories</th>
                    <th class="px-4 py-3 text-right">Total Revenue</th>
                    <th class="px-4 py-3 text-right">Avg Check</th>
                    <th class="px-4 py-3 text-center">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse ($records as $record)
                    @php
                        $avgCheck = ($record->pax > 0 && floatval($record->total_revenue) > 0)
                            ? floatval($record->total_revenue) / $record->pax
                            : null;

                        $periodColors = [
                            'all_day'   => 'bg-gray-100 text-gray-600',
                            'breakfast' => 'bg-yellow-100 text-yellow-700',
                            'lunch'     => 'bg-orange-100 text-orange-700',
                            'tea_time'  => 'bg-teal-100 text-teal-700',
                            'dinner'    => 'bg-indigo-100 text-indigo-700',
                            'supper'    => 'bg-purple-100 text-purple-700',
                        ];
                        $periodColor = $periodColors[$record->meal_period ?? 'all_day'] ?? 'bg-gray-100 text-gray-600';

                        // Group lines by sales category
                        $catGroups = $record->lines
                            ->filter(fn ($l) => $l->sales_category_id !== null)
                            ->groupBy('sales_category_id');
                    @endphp
                    <tr class="hover:bg-gray-50 transition {{ in_array($record->id, $selected) ? 'bg-indigo-50' : '' }}">
                        @if ($canDelete)
                            <td class="px-3 py-3">
                                <input type="checkbox" name="row-select"
                                       value="{{ $record->id }}"
                                       wire:model.live="selected"
                                       class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                            </td>
                        @endif
                        <td class="px-4 py-3 text-gray-700 font-medium">
                            <div class="flex items-center gap-1.5">
                                {{ $record->sale_date->format('d M Y') }}
                                @if ($record->sale_date->isToday())
                                    <span class="text-xs text-indigo-400">Today</span>
                                @endif
                                @if ($record->attachments_count > 0)
                                    <button type="button" title="{{ $record->attachments_count }} attachment(s) — click to preview"
                                            class="text-gray-400 hover:text-indigo-500 transition"
                                            @click="attachments = {{ Js::from($record->attachments->map(fn ($a) => ['name' => $a->file_name, 'url' => $a->url(), 'is_image' => $a->isImage(), 'size' => $a->humanSize()])) }}; showAttachments = true">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                                    </button>
                                @endif
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            <span class="px-2 py-0.5 rounded-full text-xs font-medium {{ $periodColor }}">
                                {{ $record->mealPeriodLabel() }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-center text-gray-700 font-medium">
                            {{ $record->pax ?? 1 }}
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex flex-wrap gap-1">
                                @forelse ($catGroups as $catId => $catLines)
                                    @php
                                        $cat    = $catLines->first()->salesCategory;
                                        $catRev = $catLines->sum(fn ($l) => floatval($l->total_revenue));
                                    @endphp
                                    @if ($cat)
                                        <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-xs bg-gray-50 border border-gray-200"
                                              title="{{ $cat->name }}: RM {{ number_format($catRev, 2) }}">
                                            <span class="w-2 h-2 rounded-full inline-block flex-shrink-0"
                                                  style="background-color: {{ $cat->color ?? '#9ca3af' }}"></span>
                                            <span class="text-gray-600">{{ $cat->name }}</span>
                                            <span class="tabular-nums text-gray-500">{{ number_format($catRev, 0) }}</span>
                                        </span>
                                    @endif
                                @empty
                                    <span class="text-xs text-gray-400">—</span>
                                @endforelse
                            </div>
                        </td>
                        <td class="px-4 py-3 text-right tabular-nums font-semibold text-gray-800">
                            RM {{ number_format($record->total_revenue, 2) }}
                        </td>
                        <td class="px-4 py-3 text-right tabular-nums text-indigo-600 font-medium">
                            @if ($avgCheck !== null)
                                RM {{ number_format($avgCheck, 2) }}
                            @else
                                <span class="text-gray-300">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-center gap-2">
                                <a href="{{ route('sales.edit', $record->id) }}" title="Edit"
                                   class="text-indigo-500 hover:text-indigo-700 transition">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                    </svg>
                                </a>
                                @if ($canDelete)
                                    <button wire:click="delete({{ $record->id }})"
                                            wire:confirm="Delete this sales record for {{ $record->sale_date->format('d M Y') }}? This cannot be undone."
                                            title="Delete"
                                            class="text-red-400 hover:text-red-600 transition">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $canDelete ? 8 : 7 }}" class="px-4 py-12 text-center text-gray-400">
                            <div class="text-3xl mb-2">💰</div>
                            <p class="font-medium">No sales records yet</p>
                            <p class="text-xs mt-1">
                                <a href="{{ route('sales.create') }}" class="text-indigo-500 underline">Record today's sales</a>
                            </p>
                        </td>
                    </tr>
                @endforelse
            </tbody>

            @if ($records->count() > 0)
                @php
                    $sumRevenue = $records->sum('total_revenue');
                    $sumPax     = $records->sum('pax');
                    $sumAvg     = ($sumPax > 0 && $sumRevenue > 0) ? $sumRevenue / $sumPax : null;
                @endphp
                <tfoot class="bg-gray-50 border-t-2 border-gray-200 text-sm font-semibold text-gray-700">
                    <tr>
                        @if ($canDelete)
                            <td class="px-3 py-3"></td>
                        @endif
                        <td colspan="3" class="px-4 py-3 text-right text-xs text-gray-500 font-normal">
                            Page total ({{ $records->count() }} records · {{ number_format($sumPax) }} pax)
                        </td>
                        <td class="px-4 py-3"></td>
                        <td class="px-4 py-3 text-right tabular-nums">RM {{ number_format($sumRevenue, 2) }}</td>
                        <td class="px-4 py-3 text-right tabular-nums text-indigo-600">
                            @if ($sumAvg !== null)
                                RM {{ number_format($sumAvg, 2) }}
                            @endif
                        </td>
                        <td></td>
                    </tr>
                </tfoot>
            @endif
        </table>
      </div>

        @if ($records->hasPages())
            <div class="px-4 py-3 border-t border-gray-100">
                {{ $records->links() }}
            </div>
        @endif
    </div>

    {{-- Closure Reason Modal --}}
    @teleport('body')
    <div x-data="{}" x-show="$wire.showClosureModal" x-cloak class="fixed inset-0 z-50">
        <div class="fixed inset-0 bg-gray-900/50" @click="$wire.showClosureModal = false"></div>
        <div class="fixed inset-0 overflow-y-auto">
            <div class="flex min-h-full items-center justify-center p-4">
                <div class="relative bg-white rounded-xl shadow-xl w-full max-w-md z-10" @click.stop>
                    <div class="px-6 py-4 border-b border-gray-100">
                        <h3 class="text-sm font-semibold text-gray-800">
                            {{ $editingClosureId ? 'Edit Closure Reason' : 'Tag Missing Date' }}
                        </h3>
                        <p class="text-xs text-gray-400 mt-0.5">
                            @if ($closureDate)
                                {{ \Carbon\Carbon::parse($closureDate)->format('d M Y (l)') }}
                            @endif
                        </p>
                    </div>
                    <div class="px-6 py-4 space-y-4">
                        {{-- Common reasons --}}
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-2">Reason</label>
                            <div class="flex flex-wrap gap-1.5 mb-2">
                                @foreach (\App\Models\SalesClosure::commonReasons() as $reason)
                                    <button type="button"
                                            wire:click="$set('closureReason', '{{ $reason }}')"
                                            class="px-2.5 py-1 text-xs rounded-lg border transition
                                                   {{ $closureReason === $reason
                                                       ? 'bg-indigo-600 text-white border-indigo-600'
                                                       : 'bg-white text-gray-600 border-gray-200 hover:bg-gray-50' }}">
                                        {{ $reason }}
                                    </button>
                                @endforeach
                                <button type="button"
                                        wire:click="$set('closureReason', 'custom')"
                                        class="px-2.5 py-1 text-xs rounded-lg border transition
                                               {{ $closureReason === 'custom'
                                                   ? 'bg-indigo-600 text-white border-indigo-600'
                                                   : 'bg-white text-gray-600 border-gray-200 hover:bg-gray-50' }}">
                                    Custom...
                                </button>
                            </div>

                            @if ($closureReason === 'custom')
                                <input type="text" wire:model="closureCustom"
                                       placeholder="Enter custom reason..."
                                       class="w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                            @endif
                            @error('closureReason')
                                <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Notes --}}
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Notes (optional)</label>
                            <textarea wire:model="closureNotes" rows="2"
                                      placeholder="Additional context..."
                                      class="w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                        </div>
                    </div>
                    <div class="px-6 py-3 bg-gray-50 rounded-b-xl flex items-center justify-between">
                        <div>
                            @if ($editingClosureId)
                                <button wire:click="removeClosure({{ $editingClosureId }})" wire:confirm="Remove this closure reason?"
                                        class="text-xs text-red-500 hover:text-red-700 transition">
                                    Remove Reason
                                </button>
                            @endif
                        </div>
                        <div class="flex items-center gap-2">
                            <button wire:click="$set('showClosureModal', false)"
                                    class="px-4 py-2 text-xs text-gray-600 border border-gray-200 rounded-lg hover:bg-white transition">
                                Cancel
                            </button>
                            <button wire:click="saveClosure"
                                    class="px-4 py-2 text-xs font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 transition">
                                Save Reason
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endteleport

    {{-- Attachments Slide-over --}}
    <div x-show="showAttachments" x-cloak x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-50 flex items-center justify-center p-4" @keydown.escape.window="showAttachments = false; preview = null">
        <div class="fixed inset-0 bg-gray-900/60" @click="showAttachments = false; preview = null"></div>

        <div class="relative z-10 bg-white rounded-xl shadow-2xl w-full max-w-md max-h-[80vh] flex flex-col overflow-hidden" x-show="!preview">
            <div class="flex items-center justify-between px-5 py-3 border-b border-gray-100">
                <h3 class="text-sm font-semibold text-gray-700">Attachments</h3>
                <button type="button" @click="showAttachments = false" class="text-gray-400 hover:text-gray-600 transition p-1">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="flex-1 overflow-auto p-4 space-y-2">
                <template x-for="(att, i) in attachments" :key="i">
                    <div class="flex items-center gap-3 bg-gray-50 rounded-lg px-3 py-2 border border-gray-100 cursor-pointer hover:bg-indigo-50 hover:border-indigo-200 transition"
                         @click="preview = att.url; previewType = att.is_image ? 'image' : 'pdf'; previewName = att.name">
                        <template x-if="att.is_image">
                            <img :src="att.url" :alt="att.name" class="w-10 h-10 object-cover rounded" />
                        </template>
                        <template x-if="!att.is_image">
                            <div class="w-10 h-10 bg-red-50 rounded flex items-center justify-center flex-shrink-0">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                            </div>
                        </template>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm text-gray-700 truncate" x-text="att.name"></p>
                            <p class="text-xs text-gray-400" x-text="att.size"></p>
                        </div>
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                    </div>
                </template>
            </div>
        </div>

        {{-- Full preview (from attachment list) --}}
        <div class="relative z-10 bg-white rounded-xl shadow-2xl max-w-4xl w-full max-h-[90vh] flex flex-col overflow-hidden" x-show="preview" x-transition>
            <div class="flex items-center justify-between px-5 py-3 border-b border-gray-100 flex-shrink-0">
                <div class="flex items-center gap-3 min-w-0">
                    <button type="button" @click="preview = null" class="text-gray-400 hover:text-gray-600 transition p-1 flex-shrink-0">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                    </button>
                    <p class="text-sm font-medium text-gray-700 truncate" x-text="previewName"></p>
                </div>
                <div class="flex items-center gap-2">
                    <a :href="preview" target="_blank" class="text-gray-400 hover:text-indigo-600 transition p-1" title="Open in new tab">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                    </a>
                    <button type="button" @click="preview = null; showAttachments = false" class="text-gray-400 hover:text-gray-600 transition p-1">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
            </div>
            <div class="flex-1 overflow-auto p-4 flex items-center justify-center bg-gray-50">
                <template x-if="previewType === 'image'">
                    <img :src="preview" :alt="previewName" class="max-w-full max-h-[75vh] object-contain rounded" />
                </template>
                <template x-if="previewType === 'pdf'">
                    <iframe :src="preview" class="w-full h-[75vh] rounded border border-gray-200"></iframe>
                </template>
            </div>
        </div>
    </div>
</div>
