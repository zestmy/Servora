<div>
    @if (session()->has('success'))
        <div wire:key="flash-{{ microtime(true) }}" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)"
             class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-700 text-sm rounded-lg">
            {{ session('success') }}
        </div>
    @endif
    @if (session()->has('error'))
        <div class="mb-4 px-4 py-3 bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg">
            {{ session('error') }}
        </div>
    @endif

    {{-- Header --}}
    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        <div>
            <p class="text-xs text-gray-400">HR / Attendance Record</p>
            <h2 class="text-lg font-semibold text-gray-700 mt-1 flex items-center gap-2">
                Attendance Record
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-indigo-100 text-indigo-700">
                    {{ number_format($employees->count()) }}
                </span>
            </h2>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <x-download-link :href="route('hr.attendance.export-pdf', ['search' => $search, 'outlet' => $outletFilter, 'section' => $sectionFilter, 'employment_status' => $employmentStatusFilter, 'from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d')])"
                    title="Export PDF"
                    class="px-2.5 md:px-3 py-2 text-sm font-medium text-red-600 border border-red-200 rounded-lg hover:bg-red-50 transition flex items-center gap-1.5">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                </svg>
                <span class="hidden sm:inline">PDF</span>
            </x-download-link>
            <button wire:click="openCodeCreate"
                    class="px-2.5 md:px-3 py-2 text-sm font-medium text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50 transition flex items-center gap-1.5">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
                <span class="hidden sm:inline">Manage Codes</span>
            </button>
            <button wire:click="fillPresent"
                    wire:confirm="Mark every empty day in the visible grid as Present?"
                    class="px-3 md:px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
                Fill Empty with ✓
            </button>
        </div>
    </div>

    {{-- Filter / period bar --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 mb-4">
        <div class="flex flex-col lg:flex-row lg:items-center flex-wrap gap-3">
            <div class="flex-1 min-w-[170px]">
                <input type="text" wire:model.live.debounce.300ms="search"
                       placeholder="Search name, staff ID, position…"
                       class="w-full text-sm rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
            </div>
            <select wire:model.live="outletFilter" class="text-sm rounded-lg border-gray-300 shadow-sm">
                @if ($canViewAll)
                    <option value="">All Outlets</option>
                @endif
                @foreach ($outlets as $o)
                    <option value="{{ $o->id }}">{{ $o->name }}</option>
                @endforeach
            </select>
            <select wire:model.live="sectionFilter" class="text-sm rounded-lg border-gray-300 shadow-sm">
                <option value="">All Sections</option>
                @foreach ($sections as $s)
                    <option value="{{ $s->id }}">{{ $s->name }}</option>
                @endforeach
            </select>
            <select wire:model.live="employmentStatusFilter" class="text-sm rounded-lg border-gray-300 shadow-sm">
                <option value="">All Employment</option>
                @foreach (\App\Models\Employee::EMPLOYMENT_STATUSES as $esValue => $esLabel)
                    <option value="{{ $esValue }}">{{ $esLabel }}</option>
                @endforeach
                <option value="none">No Status</option>
            </select>

            {{-- Period picker --}}
            <div class="flex items-center gap-2 lg:ml-auto">
                <div class="inline-flex rounded-lg border border-gray-300 overflow-hidden text-sm">
                    <button wire:click="$set('periodMode', 'month')"
                            class="px-3 py-2 {{ $periodMode === 'month' ? 'bg-indigo-600 text-white font-medium' : 'bg-white text-gray-600 hover:bg-gray-50' }}">
                        Month
                    </button>
                    <button wire:click="$set('periodMode', 'range')"
                            class="px-3 py-2 border-l border-gray-300 {{ $periodMode === 'range' ? 'bg-indigo-600 text-white font-medium' : 'bg-white text-gray-600 hover:bg-gray-50' }}">
                        Custom
                    </button>
                </div>
                <button wire:click="previousPeriod" title="Previous period"
                        class="p-2 text-gray-500 border border-gray-300 rounded-lg hover:bg-gray-50">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                </button>
                @if ($periodMode === 'month')
                    <input type="month" wire:model.live="month"
                           class="text-sm rounded-lg border-gray-300 shadow-sm" />
                @else
                    <input type="date" wire:model.live="rangeFrom" class="text-sm rounded-lg border-gray-300 shadow-sm" />
                    <span class="text-gray-400 text-sm">–</span>
                    <input type="date" wire:model.live="rangeTo" class="text-sm rounded-lg border-gray-300 shadow-sm" />
                @endif
                <button wire:click="nextPeriod" title="Next period"
                        class="p-2 text-gray-500 border border-gray-300 rounded-lg hover:bg-gray-50">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                </button>
            </div>
        </div>
    </div>

    {{-- Paint palette: pick a code, then click day cells to apply it --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-3 mb-4">
        <div class="flex flex-wrap items-center gap-1.5">
            <span class="text-xs text-gray-400 uppercase tracking-wider mr-1.5">Mark as</span>
            @foreach ($activeCodes as $code)
                @php $meta = $code->colorMeta(); @endphp
                <button wire:key="palette-{{ $code->id }}"
                        wire:click="selectCode({{ $code->id }})"
                        title="{{ $code->label }}"
                        class="px-2.5 py-1 rounded-md text-xs font-bold transition {{ $meta['tw'] }} {{ $selectedCodeId === $code->id ? 'ring-2 ring-indigo-500 ring-offset-1' : 'opacity-80 hover:opacity-100' }}">
                    {{ $code->code }}
                </button>
            @endforeach
            <button wire:click="selectCode(null)"
                    title="Eraser — click a cell to clear it"
                    class="px-2.5 py-1 rounded-md text-xs font-bold border border-dashed transition {{ $selectedCodeId === null ? 'ring-2 ring-indigo-500 ring-offset-1 border-gray-400 text-gray-600' : 'border-gray-300 text-gray-400 hover:text-gray-600' }}">
                ⌫ Clear
            </button>
            <span class="text-xs text-gray-400 ml-auto hidden md:inline">
                {{ $from->format('d M Y') }} – {{ $to->format('d M Y') }} · click a cell to mark it
            </span>
        </div>
    </div>

    {{-- Grid --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden mb-4"
         wire:loading.class="opacity-60" wire:target="setCell, fillPresent, clearRange">
        <div class="overflow-x-auto">
            <table class="text-sm border-collapse w-full">
                <thead class="bg-gray-50 text-gray-500 uppercase text-[10px] tracking-wider">
                    <tr>
                        <th class="px-2 py-2 text-left w-8 border-b border-gray-200">#</th>
                        <th class="px-3 py-2 text-left min-w-[170px] border-b border-gray-200 sticky left-0 bg-gray-50 z-10">Name</th>
                        <th class="px-2 py-2 text-left min-w-[90px] border-b border-gray-200">Position</th>
                        <th class="px-2 py-2 text-left min-w-[70px] border-b border-gray-200">Emp ID</th>
                        <th class="px-2 py-2 text-left min-w-[90px] border-b border-gray-200">Outlet</th>
                        <th class="px-2 py-2 text-left min-w-[64px] border-b border-gray-200">Section</th>
                        <th class="px-2 py-2 text-left min-w-[76px] border-b border-gray-200">Date Join</th>
                        <th class="px-2 py-2 text-right min-w-[54px] border-b border-gray-200">Svc Pts</th>
                        @foreach ($dates as $d)
                            <th wire:key="dh-{{ $d->format('Ymd') }}"
                                class="px-0 py-1.5 text-center w-9 min-w-[34px] border-b border-l border-gray-200 {{ $d->isSunday() ? 'bg-red-50 text-red-500' : ($d->isSaturday() ? 'bg-amber-50/60 text-amber-600' : '') }} {{ $d->isToday() ? '!bg-indigo-50 !text-indigo-600' : '' }}">
                                <div class="text-[11px] font-bold leading-tight">{{ $d->day }}</div>
                                <div class="text-[9px] font-normal leading-tight">{{ $d->format('D') }}</div>
                            </th>
                        @endforeach
                        <th class="px-2 py-2 text-center min-w-[44px] border-b border-l-2 border-gray-200" title="Days marked Present">✓</th>
                        <th class="px-2 py-2 text-center min-w-[44px] border-b border-gray-200 text-red-500" title="Days marked Absent">ABS</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($employees as $emp)
                        <tr wire:key="row-{{ $emp->id }}" class="hover:bg-gray-50/70">
                            <td class="px-2 py-1.5 text-gray-400 text-xs">{{ $loop->iteration }}</td>
                            <td class="px-3 py-1.5 font-medium text-gray-800 whitespace-nowrap sticky left-0 bg-white z-10">{{ $emp->name }}</td>
                            <td class="px-2 py-1.5 text-gray-500 text-xs whitespace-nowrap">{{ $emp->designation ?? '—' }}</td>
                            <td class="px-2 py-1.5 text-gray-500 font-mono text-xs">{{ $emp->staff_id ?? '—' }}</td>
                            <td class="px-2 py-1.5 text-gray-500 text-xs whitespace-nowrap">{{ $emp->outlet?->name ?? '—' }}</td>
                            <td class="px-2 py-1.5 text-gray-500 text-xs">{{ $emp->section?->name ?? '—' }}</td>
                            <td class="px-2 py-1.5 text-gray-500 text-xs whitespace-nowrap">{{ $emp->join_date?->format('d M y') ?? '—' }}</td>
                            <td class="px-2 py-1.5 text-gray-500 text-xs text-right">{{ $emp->service_points_entitlement !== null ? number_format((float) $emp->service_points_entitlement, 2) : '—' }}</td>
                            @foreach ($dates as $d)
                                @php
                                    $key    = $emp->id . ':' . $d->format('Y-m-d');
                                    $codeId = $cellMap[$key] ?? null;
                                    $code   = $codeId ? ($codesById[$codeId] ?? null) : null;
                                    $meta   = $code?->colorMeta();
                                @endphp
                                <td wire:key="c-{{ $emp->id }}-{{ $d->format('Ymd') }}"
                                    class="p-0 border-l border-gray-100 text-center">
                                    <button wire:click="setCell({{ $emp->id }}, '{{ $d->format('Y-m-d') }}')"
                                            title="{{ $emp->name }} · {{ $d->format('D, d M Y') }}{{ $code ? ' · ' . $code->label : '' }}"
                                            class="w-full h-8 text-[11px] font-bold transition
                                                   {{ $code ? $meta['tw'] : ($d->isSunday() ? 'bg-red-50/40 hover:bg-indigo-50' : 'hover:bg-indigo-50') }}">
                                        {{ $code?->code }}
                                    </button>
                                </td>
                            @endforeach
                            <td class="px-2 py-1.5 text-center text-xs font-semibold text-green-700 border-l-2 border-gray-200">{{ $presentCounts[$emp->id] ?? 0 }}</td>
                            <td class="px-2 py-1.5 text-center text-xs font-semibold {{ ($absentCounts[$emp->id] ?? 0) > 0 ? 'text-red-600' : 'text-gray-300' }}">{{ $absentCounts[$emp->id] ?? 0 }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ 10 + count($dates) }}" class="px-4 py-10 text-center text-gray-400 text-sm">
                                No active employees match the selected filters.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Legend --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
        <div class="flex items-center justify-between mb-3">
            <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Legend</h3>
            <button wire:click="clearRange"
                    wire:confirm="Remove EVERY attendance mark in the visible grid ({{ $from->format('d M Y') }} – {{ $to->format('d M Y') }})? This cannot be undone."
                    class="text-xs text-red-500 hover:text-red-700 underline">
                Clear all marks in this period
            </button>
        </div>
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-x-4 gap-y-1.5">
            @foreach ($activeCodes as $code)
                @php $meta = $code->colorMeta(); @endphp
                <div wire:key="legend-{{ $code->id }}" class="flex items-center gap-2 text-xs text-gray-600">
                    <span class="inline-flex items-center justify-center min-w-[30px] px-1 py-0.5 rounded font-bold text-[10px] {{ $meta['tw'] }}">{{ $code->code }}</span>
                    <span class="truncate" title="{{ $code->label }}">{{ $code->label }}</span>
                </div>
            @endforeach
        </div>
    </div>

    {{-- ── Manage Codes modal (teleported to body to escape sidebar transform) --}}
    <div x-data="{ open: @entangle('showCodes') }">
    <template x-teleport="body">
    <div x-show="open" x-cloak
         @keydown.escape.window="open = false"
         class="fixed inset-0 z-[100] overflow-y-auto">
        <div class="fixed inset-0 bg-black/50" @click="open = false"></div>
        <div class="relative min-h-full flex items-start sm:items-center justify-center p-4">
            <div class="relative bg-white rounded-xl shadow-xl w-full max-w-2xl" @click.stop>
                <div class="flex items-center justify-between px-5 py-3 border-b border-gray-100">
                    <h3 class="text-sm font-semibold text-gray-800">Attendance Codes</h3>
                    <button @click="open = false" class="text-gray-400 hover:text-gray-600 p-1">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <div class="p-5">
                    {{-- Add / edit form --}}
                    <div class="bg-gray-50 rounded-lg p-4 mb-4">
                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">
                            {{ $editingCodeId ? 'Edit Code' : 'Add New Code' }}
                        </p>
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">Code</label>
                                <input type="text" wire:model="c_code" maxlength="10" placeholder="e.g. OT"
                                       class="w-full text-sm rounded-lg border-gray-300 shadow-sm" />
                                @error('c_code') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                            </div>
                            <div class="col-span-2 sm:col-span-1">
                                <label class="block text-xs text-gray-500 mb-1">Label</label>
                                <input type="text" wire:model="c_label" maxlength="100" placeholder="e.g. Overtime"
                                       class="w-full text-sm rounded-lg border-gray-300 shadow-sm" />
                                @error('c_label') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">Color</label>
                                <select wire:model="c_color" class="w-full text-sm rounded-lg border-gray-300 shadow-sm">
                                    @foreach (array_keys(\App\Models\AttendanceCode::COLORS) as $colorKey)
                                        <option value="{{ $colorKey }}">{{ ucfirst($colorKey) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">Sort</label>
                                <input type="number" wire:model="c_sort" min="0"
                                       class="w-full text-sm rounded-lg border-gray-300 shadow-sm" />
                            </div>
                        </div>
                        <div class="flex items-center justify-between mt-3">
                            <label class="flex items-center gap-2 text-sm text-gray-600">
                                <input type="checkbox" wire:model="c_is_active" class="rounded border-gray-300 text-indigo-600" />
                                Active
                            </label>
                            <div class="flex gap-2">
                                @if ($editingCodeId)
                                    <button wire:click="openCodeCreate"
                                            class="px-3 py-1.5 text-xs text-gray-500 border border-gray-300 rounded-lg hover:bg-gray-100">Cancel</button>
                                @endif
                                <button wire:click="saveCode"
                                        class="px-4 py-1.5 bg-indigo-600 text-white text-xs font-medium rounded-lg hover:bg-indigo-700">
                                    {{ $editingCodeId ? 'Update' : 'Add Code' }}
                                </button>
                            </div>
                        </div>
                    </div>

                    {{-- Codes list --}}
                    <div class="max-h-72 overflow-y-auto border border-gray-100 rounded-lg divide-y divide-gray-50">
                        @foreach ($codes as $code)
                            @php $meta = $code->colorMeta(); @endphp
                            <div wire:key="code-row-{{ $code->id }}"
                                 class="flex items-center gap-3 px-3 py-2 {{ ! $code->is_active ? 'opacity-50' : '' }}">
                                <span class="inline-flex items-center justify-center min-w-[36px] px-1.5 py-1 rounded font-bold text-[11px] {{ $meta['tw'] }}">{{ $code->code }}</span>
                                <span class="flex-1 text-sm text-gray-700 truncate">
                                    {{ $code->label }}
                                    @if ($code->system_key)
                                        <span class="ml-1 text-[10px] uppercase tracking-wider text-gray-400">built-in</span>
                                    @endif
                                </span>
                                <button wire:click="openCodeEdit({{ $code->id }})"
                                        class="text-xs text-indigo-600 hover:text-indigo-800">Edit</button>
                                @unless ($code->system_key)
                                    <button wire:click="toggleCodeActive({{ $code->id }})"
                                            class="text-xs {{ $code->is_active ? 'text-amber-600 hover:text-amber-800' : 'text-green-600 hover:text-green-800' }}">
                                        {{ $code->is_active ? 'Deactivate' : 'Activate' }}
                                    </button>
                                    <button wire:click="deleteCode({{ $code->id }})"
                                            wire:confirm="Delete code {{ $code->code }} ({{ $code->label }})?"
                                            class="text-xs text-red-500 hover:text-red-700">Delete</button>
                                @endunless
                            </div>
                        @endforeach
                    </div>
                    <p class="text-[11px] text-gray-400 mt-2">
                        Built-in codes (Present ✓, Day Off X, Absent ABS) can be relabelled and recoloured but not deleted.
                        Codes already used in records can only be deactivated.
                    </p>
                </div>
            </div>
        </div>
    </div>
    </template>
    </div>
</div>
