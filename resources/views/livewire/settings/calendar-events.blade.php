<div>
    <div class="flex items-center gap-3 mb-6">
        <a href="{{ route('settings.index') }}" class="text-gray-400 hover:text-gray-600 transition">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
            </svg>
        </a>
        <div class="flex-1">
            <p class="text-xs text-gray-400"><a href="{{ route('settings.index') }}" class="hover:underline">Settings</a> / Calendar Events</p>
        </div>
        <div class="flex items-center gap-2">
            <button wire:click="openHolidays"
                    class="px-4 py-2 bg-purple-600 text-white text-sm font-medium rounded-lg hover:bg-purple-700 transition flex items-center gap-1.5">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/></svg>
                AI Public Holidays
            </button>
            <button wire:click="openImport"
                    class="px-4 py-2 bg-white text-gray-700 text-sm font-medium rounded-lg border border-gray-300 hover:bg-gray-50 transition flex items-center gap-1.5">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                Import
            </button>
            <button wire:click="openCreate"
                    class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
                + Add Event
            </button>
        </div>
    </div>

    @if (session()->has('success'))
        <div wire:key="flash-{{ microtime(true) }}" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)"
             class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-700 text-sm rounded-lg">
            {{ session('success') }}
        </div>
    @endif

    {{-- Filters --}}
    <div class="flex flex-col sm:flex-row gap-3 mb-4">
        <input wire:model.live.debounce.300ms="search" type="text" placeholder="Search events..."
               class="flex-1 rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500" />
        <select wire:model.live="categoryFilter"
                class="rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
            <option value="all">All Categories</option>
            @foreach ($categoryOptions as $value => $label)
                <option value="{{ $value }}">{{ $label }}</option>
            @endforeach
        </select>
    </div>

    {{-- Events Table --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="overflow-x-auto"><table class="min-w-[720px] text-sm">
            <thead class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wider">
                <tr>
                    <th class="px-4 py-3 text-left">Date</th>
                    <th class="px-4 py-3 text-left">Event</th>
                    <th class="px-4 py-3 text-left">Category</th>
                    <th class="px-4 py-3 text-left">Impact</th>
                    <th class="px-4 py-3 text-left">Outlet</th>
                    <th class="px-4 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($events as $event)
                    <tr class="hover:bg-gray-50 transition">
                        <td class="px-4 py-3 whitespace-nowrap">
                            {{ $event->event_date->format('d M Y') }}
                            @if ($event->end_date)
                                <span class="text-gray-400">— {{ $event->end_date->format('d M Y') }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 font-medium text-gray-800">
                            {{ $event->title }}
                            @if ($event->description)
                                <p class="text-xs text-gray-400 mt-0.5 truncate max-w-xs">{{ $event->description }}</p>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium
                                @switch($event->category)
                                    @case('holiday') bg-red-50 text-red-700 @break
                                    @case('promotion') bg-green-50 text-green-700 @break
                                    @case('operational') bg-yellow-50 text-yellow-700 @break
                                    @case('menu_change') bg-blue-50 text-blue-700 @break
                                    @case('external') bg-purple-50 text-purple-700 @break
                                    @default bg-gray-50 text-gray-700
                                @endswitch
                            ">
                                {{ $event->categoryLabel() }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center gap-1 text-xs font-medium
                                {{ $event->impact === 'positive' ? 'text-green-600' : ($event->impact === 'negative' ? 'text-red-600' : 'text-gray-500') }}">
                                @if ($event->impact === 'positive')
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 10l7-7m0 0l7 7m-7-7v18"/></svg>
                                @elseif ($event->impact === 'negative')
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 14l-7 7m0 0l-7-7m7 7V3"/></svg>
                                @else
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M20 12H4"/></svg>
                                @endif
                                {{ ucfirst($event->impact ?? 'neutral') }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-gray-500">
                            {{ $event->outlet?->name ?? 'All Outlets' }}
                        </td>
                        <td class="px-4 py-3 text-right space-x-2">
                            <button wire:click="openEdit({{ $event->id }})" class="text-indigo-600 hover:text-indigo-800 text-xs font-medium">Edit</button>
                            <button wire:click="delete({{ $event->id }})" wire:confirm="Delete this event?" class="text-red-500 hover:text-red-700 text-xs font-medium">Delete</button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-gray-400">No events found. Add your first event to start tracking.</td>
                    </tr>
                @endforelse
            </tbody>
        </table></div>
    </div>

    <div class="mt-4">{{ $events->links() }}</div>

    {{-- Modal --}}
    @if ($showModal)
        @teleport('body')
        <div class="fixed inset-0 z-50" wire:keydown.escape="closeModal">
            <div class="fixed inset-0 bg-black/40" @click="$wire.closeModal()"></div>
            <div class="fixed inset-0 overflow-y-auto">
            <div class="flex min-h-full items-center justify-center p-4">
            <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg z-10" @click.away="$wire.closeModal()">
                <div class="px-6 py-4 border-b border-gray-100">
                    <h3 class="text-lg font-semibold text-gray-800">{{ $editingId ? 'Edit Event' : 'Add Event' }}</h3>
                </div>

                <div class="px-6 py-4 space-y-4">
                    {{-- Title --}}
                    <div>
                        <x-input-label for="title" value="Event Title" />
                        <input wire:model="title" id="title" type="text" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="e.g. Chinese New Year" />
                        <x-input-error :messages="$errors->get('title')" class="mt-1" />
                    </div>

                    {{-- Dates --}}
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="event_date" value="Start Date" />
                            <input wire:model="event_date" id="event_date" type="date" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500" />
                            <x-input-error :messages="$errors->get('event_date')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="end_date" value="End Date (optional)" />
                            <input wire:model="end_date" id="end_date" type="date" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500" />
                            <x-input-error :messages="$errors->get('end_date')" class="mt-1" />
                        </div>
                    </div>

                    {{-- Category & Impact --}}
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="category" value="Category" />
                            <select wire:model="category" id="category" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                @foreach ($categoryOptions as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('category')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="impact" value="Expected Impact" />
                            <select wire:model="impact" id="impact" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                @foreach ($impactOptions as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('impact')" class="mt-1" />
                        </div>
                    </div>

                    {{-- Outlet --}}
                    <div>
                        <x-input-label for="outlet_id" value="Outlet (leave blank for all)" />
                        <select wire:model="outlet_id" id="outlet_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">All Outlets</option>
                            @foreach ($outlets as $outlet)
                                <option value="{{ $outlet->id }}">{{ $outlet->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Description --}}
                    <div>
                        <x-input-label for="description" value="Description (optional)" />
                        <textarea wire:model="description" id="description" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="Additional notes about this event..."></textarea>
                        <x-input-error :messages="$errors->get('description')" class="mt-1" />
                    </div>
                </div>

                <div class="px-6 py-4 border-t border-gray-100 flex justify-end gap-3">
                    <button wire:click="closeModal" class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition">Cancel</button>
                    <button wire:click="save" class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 transition">
                        {{ $editingId ? 'Update' : 'Create' }}
                    </button>
                </div>
            </div>
            </div>
            </div>
        </div>
        @endteleport
    @endif

    {{-- Import Modal --}}
    @if ($showImportModal)
        @teleport('body')
        <div class="fixed inset-0 z-50" wire:keydown.escape="closeImportModal">
            <div class="fixed inset-0 bg-black/40" @click="$wire.closeImportModal()"></div>
            <div class="fixed inset-0 overflow-y-auto">
            <div class="flex min-h-full items-center justify-center p-4">
            <div class="bg-white rounded-2xl shadow-xl w-full max-w-2xl max-h-[90vh] flex flex-col z-10" @click.away="$wire.closeImportModal()">
                <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-gray-800">Import Calendar Events</h3>
                    <button wire:click="closeImportModal" class="text-gray-400 hover:text-gray-600">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <div class="px-6 py-4 overflow-y-auto flex-1 space-y-4">
                    {{-- File Upload --}}
                    <div>
                        <div class="flex items-center justify-between">
                            <x-input-label value="Excel or CSV File" />
                            <button type="button" wire:click="downloadTemplate"
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-indigo-700 border border-indigo-300 rounded-lg hover:bg-indigo-50 transition">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a2 2 0 002 2h12a2 2 0 002-2v-1M12 12v6m0 0l-3-3m3 3l3-3M12 4v4" />
                                </svg>
                                Download Excel Template
                            </button>
                        </div>
                        <div class="mt-1">
                            <input type="file" wire:model="importFile" accept=".csv,.txt,.xlsx,.xls"
                                   class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100" />
                        </div>
                        <x-input-error :messages="$errors->get('importFile')" class="mt-1" />

                        <div wire:loading wire:target="importFile" class="mt-2 text-xs text-gray-500 flex items-center gap-1.5">
                            <svg class="animate-spin h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                            Reading file...
                        </div>
                    </div>

                    {{-- Format Guide --}}
                    @if (empty($importPreview))
                        <div class="bg-gray-50 rounded-lg p-4 text-xs text-gray-500 space-y-2">
                            <p class="font-semibold text-gray-700">File Format</p>
                            <p>Upload an Excel (.xlsx) or CSV file — download the template above for the exact columns.</p>
                            <p>Required columns: <span class="font-medium text-gray-700">title</span>, <span class="font-medium text-gray-700">event_date</span></p>
                            <p>Optional columns: <span class="font-medium text-gray-700">end_date</span>, <span class="font-medium text-gray-700">category</span>, <span class="font-medium text-gray-700">impact</span>, <span class="font-medium text-gray-700">description</span></p>
                            <div class="mt-2">
                                <p class="font-medium text-gray-600 mb-1">Category values:</p>
                                <p>holiday, promotion, operational, menu_change, external, other</p>
                            </div>
                            <div class="mt-1">
                                <p class="font-medium text-gray-600 mb-1">Impact values:</p>
                                <p>positive, negative, neutral</p>
                            </div>
                            <div class="mt-3 bg-white rounded border border-gray-200 p-3 font-mono text-[11px] leading-relaxed">
                                title,event_date,end_date,category,impact,description<br>
                                Chinese New Year,2026-01-29,2026-01-31,holiday,positive,Public holiday<br>
                                Ramadan Starts,2026-02-18,,external,negative,Fasting month begins<br>
                                Lunch Promo,2026-03-01,2026-03-15,promotion,positive,20% off lunch set
                            </div>
                        </div>
                    @endif

                    {{-- Errors --}}
                    @if ($importErrors)
                        <div class="bg-red-50 border border-red-200 rounded-lg p-3">
                            @foreach ($importErrors as $err)
                                <p class="text-sm text-red-700">{{ $err }}</p>
                            @endforeach
                        </div>
                    @endif

                    {{-- Preview Table --}}
                    @if ($importPreview)
                        @php
                            $validCount = collect($importPreview)->where('valid', true)->count();
                            $errorCount = collect($importPreview)->where('valid', false)->count();
                        @endphp
                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <p class="text-sm font-medium text-gray-700">
                                    Preview
                                    <span class="text-xs text-gray-400 ml-1">({{ $validCount }} valid{{ $errorCount ? ", {$errorCount} with errors" : '' }})</span>
                                </p>
                            </div>
                            <div class="border border-gray-200 rounded-lg overflow-x-auto max-h-64 overflow-y-auto">
                                <table class="w-full text-xs">
                                    <thead class="bg-gray-50 text-gray-500 uppercase tracking-wider sticky top-0">
                                        <tr>
                                            <th class="px-3 py-2 text-left">Row</th>
                                            <th class="px-3 py-2 text-left">Title</th>
                                            <th class="px-3 py-2 text-left">Date</th>
                                            <th class="px-3 py-2 text-left">End Date</th>
                                            <th class="px-3 py-2 text-left">Category</th>
                                            <th class="px-3 py-2 text-left">Impact</th>
                                            <th class="px-3 py-2 text-left">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                        @foreach ($importPreview as $entry)
                                            <tr class="{{ $entry['valid'] ? '' : 'bg-red-50' }}">
                                                <td class="px-3 py-2 text-gray-400">{{ $entry['row'] }}</td>
                                                <td class="px-3 py-2 font-medium text-gray-800">{{ $entry['title'] }}</td>
                                                <td class="px-3 py-2">{{ $entry['event_date'] }}</td>
                                                <td class="px-3 py-2 text-gray-400">{{ $entry['end_date'] ?: '—' }}</td>
                                                <td class="px-3 py-2">{{ $entry['category'] }}</td>
                                                <td class="px-3 py-2">{{ $entry['impact'] }}</td>
                                                <td class="px-3 py-2">
                                                    @if ($entry['valid'])
                                                        <span class="text-green-600 font-medium">OK</span>
                                                    @else
                                                        <span class="text-red-600" title="{{ implode(', ', $entry['errors']) }}">{{ implode(', ', $entry['errors']) }}</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif
                </div>

                <div class="px-6 py-4 border-t border-gray-100 flex justify-end gap-3">
                    <button wire:click="closeImportModal" class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition">Cancel</button>
                    @if ($importPreview && collect($importPreview)->where('valid', true)->count() > 0)
                        <button wire:click="confirmImport"
                                class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 transition flex items-center gap-1.5">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                            Import {{ collect($importPreview)->where('valid', true)->count() }} Event(s)
                        </button>
                    @endif
                </div>
            </div>
            </div>
            </div>
        </div>
        @endteleport
    @endif

    {{-- AI Public Holidays Modal --}}
    @if ($showHolidayModal)
        @teleport('body')
        <div class="fixed inset-0 z-50" wire:keydown.escape="closeHolidayModal">
            <div class="fixed inset-0 bg-black/40" @click="$wire.closeHolidayModal()"></div>
            <div class="fixed inset-0 overflow-y-auto">
            <div class="flex min-h-full items-center justify-center p-4">
            <div class="bg-white rounded-2xl shadow-xl w-full max-w-2xl max-h-[90vh] flex flex-col z-10" @click.away="$wire.closeHolidayModal()">
                <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-800">AI Public Holidays</h3>
                        <p class="text-xs text-gray-400 mt-0.5">Generate location-based public holidays as analytics factors.</p>
                    </div>
                    <button wire:click="closeHolidayModal" class="text-gray-400 hover:text-gray-600">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <div class="px-6 py-4 overflow-y-auto flex-1 space-y-4">
                    {{-- Controls --}}
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="h_outlet" value="Branch" />
                            <select wire:model="holidayOutletId" id="h_outlet"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">All active branches</option>
                                @foreach ($outlets as $outlet)
                                    <option value="{{ $outlet->id }}">{{ $outlet->name }}{{ $outlet->state || $outlet->country ? ' — ' . trim(collect([$outlet->state, $outlet->country])->filter()->implode(', ')) : '' }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <x-input-label for="h_year" value="Year" />
                            <select wire:model="holidayYear" id="h_year"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                @for ($y = (int) now()->year - 1; $y <= (int) now()->year + 2; $y++)
                                    <option value="{{ $y }}">{{ $y }}</option>
                                @endfor
                            </select>
                        </div>
                    </div>

                    <div>
                        <button wire:click="generateHolidays" wire:loading.attr="disabled" wire:target="generateHolidays"
                                class="px-4 py-2 bg-purple-600 text-white text-sm font-medium rounded-lg hover:bg-purple-700 transition flex items-center gap-1.5 disabled:opacity-60">
                            <span wire:loading.remove wire:target="generateHolidays" class="flex items-center gap-1.5">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/></svg>
                                Generate
                            </span>
                            <span wire:loading wire:target="generateHolidays" class="flex items-center gap-1.5">
                                <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                </svg>
                                Generating…
                            </span>
                        </button>
                        <p wire:loading wire:target="generateHolidays" class="mt-2 text-xs text-gray-500">Asking the AI for public holidays — this may take a moment.</p>
                    </div>

                    {{-- Notice (skipped outlets) --}}
                    @if ($holidayNotice)
                        <div class="bg-amber-50 border border-amber-200 rounded-lg p-3 text-sm text-amber-700">{{ $holidayNotice }}</div>
                    @endif

                    {{-- Errors --}}
                    @if ($holidayErrors)
                        <div class="bg-red-50 border border-red-200 rounded-lg p-3">
                            @foreach ($holidayErrors as $err)
                                <p class="text-sm text-red-700">{{ $err }}</p>
                            @endforeach
                        </div>
                    @endif

                    {{-- Guide (before generation) --}}
                    @if (empty($holidayPreview) && empty($holidayErrors))
                        <div class="bg-gray-50 rounded-lg p-4 text-xs text-gray-500 space-y-1.5">
                            <p>Holidays are generated from each branch's <span class="font-medium text-gray-700">Country</span> and <span class="font-medium text-gray-700">State/Region</span> (set in Settings &gt; Branches), so state-specific holidays are included.</p>
                            <p>Each holiday is added as a <span class="font-medium text-gray-700">Public Holiday</span> calendar event tagged to its branch, with an AI-estimated sales impact — and becomes a factor in AI Analytics.</p>
                            <p>Events that already exist are detected and skipped automatically.</p>
                        </div>
                    @endif

                    {{-- Preview --}}
                    @if ($holidayPreview)
                        @php
                            $newCount = collect($holidayPreview)->where('exists', false)->count();
                            $selCount = collect($holidayPreview)->where('exists', false)->where('selected', true)->count();
                            $existCount = collect($holidayPreview)->where('exists', true)->count();
                        @endphp
                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <p class="text-sm font-medium text-gray-700">
                                    Preview
                                    <span class="text-xs text-gray-400 ml-1">({{ $newCount }} new{{ $existCount ? ", {$existCount} already exist" : '' }})</span>
                                </p>
                            </div>
                            <div class="border border-gray-200 rounded-lg overflow-x-auto max-h-72 overflow-y-auto">
                                <table class="w-full text-xs">
                                    <thead class="bg-gray-50 text-gray-500 uppercase tracking-wider sticky top-0">
                                        <tr>
                                            <th class="px-3 py-2 text-center w-8"></th>
                                            <th class="px-3 py-2 text-left">Branch</th>
                                            <th class="px-3 py-2 text-left">Date</th>
                                            <th class="px-3 py-2 text-left">Holiday</th>
                                            <th class="px-3 py-2 text-left">Impact</th>
                                            <th class="px-3 py-2 text-left">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                        @foreach ($holidayPreview as $i => $entry)
                                            <tr class="{{ $entry['exists'] ? 'bg-gray-50 text-gray-400' : '' }}">
                                                <td class="px-3 py-2 text-center">
                                                    <input type="checkbox" wire:model.live="holidayPreview.{{ $i }}.selected"
                                                           @disabled($entry['exists'])
                                                           class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 disabled:opacity-40">
                                                </td>
                                                <td class="px-3 py-2">{{ $entry['outlet_name'] }}</td>
                                                <td class="px-3 py-2 whitespace-nowrap">{{ \Carbon\Carbon::parse($entry['date'])->format('d M Y') }}</td>
                                                <td class="px-3 py-2 font-medium {{ $entry['exists'] ? '' : 'text-gray-800' }}">{{ $entry['name'] }}</td>
                                                <td class="px-3 py-2">
                                                    <span class="{{ $entry['impact'] === 'positive' ? 'text-green-600' : ($entry['impact'] === 'negative' ? 'text-red-600' : 'text-gray-500') }}">
                                                        {{ ucfirst($entry['impact']) }}
                                                    </span>
                                                </td>
                                                <td class="px-3 py-2">
                                                    @if ($entry['exists'])
                                                        <span class="text-gray-400">Exists</span>
                                                    @else
                                                        <span class="text-green-600 font-medium">New</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif
                </div>

                <div class="px-6 py-4 border-t border-gray-100 flex justify-end gap-3">
                    <button wire:click="closeHolidayModal" class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition">Cancel</button>
                    @if ($holidayPreview)
                        @php $selCount = collect($holidayPreview)->where('exists', false)->where('selected', true)->count(); @endphp
                        <button wire:click="confirmHolidays" @disabled($selCount === 0)
                                class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 transition flex items-center gap-1.5 disabled:opacity-50">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                            Add {{ $selCount }} Holiday{{ $selCount === 1 ? '' : 's' }}
                        </button>
                    @endif
                </div>
            </div>
            </div>
            </div>
        </div>
        @endteleport
    @endif
</div>
