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
            <p class="text-xs text-gray-400">HR / Employees</p>
            <h2 class="text-lg font-semibold text-gray-700 mt-1">Employees</h2>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <button wire:click="downloadTemplate"
                    title="Download CSV template"
                    class="px-2.5 md:px-3 py-2 text-sm font-medium text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50 transition flex items-center gap-1.5">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a2 2 0 002 2h12a2 2 0 002-2v-1M12 12v6m0 0l-3-3m3 3l3-3M12 4v4" />
                </svg>
                <span class="hidden sm:inline">Template</span>
            </button>
            <button wire:click="openImport"
                    title="Import CSV"
                    class="px-2.5 md:px-3 py-2 text-sm font-medium text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50 transition flex items-center gap-1.5">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 13h6m-3-3v6m5 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <span class="hidden sm:inline">Import CSV</span>
            </button>
            <button wire:click="openCreate"
                    class="px-3 md:px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
                <span class="sm:hidden">+ Add</span>
                <span class="hidden sm:inline">+ Add Employee</span>
            </button>
        </div>
    </div>

    {{-- Filter Bar --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 mb-4">
        <div class="flex flex-col sm:flex-row gap-3">
            <div class="flex-1">
                <input type="text" wire:model.live.debounce.300ms="search"
                       placeholder="Search name, staff ID, email, designation…"
                       class="w-full text-sm rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
            </div>
            <select wire:model.live="outletFilter" class="text-sm rounded-lg border-gray-300 shadow-sm">
                <option value="">All Outlets</option>
                @foreach ($outlets as $o)
                    <option value="{{ $o->id }}">{{ $o->name }}</option>
                @endforeach
            </select>
            <select wire:model.live="statusFilter" class="text-sm rounded-lg border-gray-300 shadow-sm">
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
                <option value="">All</option>
            </select>
        </div>
    </div>

    {{-- List --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">

        {{-- Mobile cards --}}
        <div class="md:hidden divide-y divide-gray-100">
            @forelse ($employees as $emp)
                <div class="p-3 space-y-1.5">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <div class="text-sm font-medium text-gray-800 truncate">{{ $emp->name }}</div>
                            <div class="text-xs text-gray-500 truncate">
                                {{ $emp->designation ?? '—' }}@if ($emp->department) · {{ $emp->department }}@endif
                            </div>
                        </div>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium flex-shrink-0 {{ $emp->is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                            {{ $emp->is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </div>
                    <div class="text-xs text-gray-500 truncate">{{ $emp->outlet?->name ?? '—' }}@if ($emp->staff_id) · {{ $emp->staff_id }}@endif</div>
                    @if ($emp->email || $emp->phone)
                        <div class="text-xs text-gray-500 truncate">{{ $emp->email ?? '' }}@if ($emp->email && $emp->phone) · @endif{{ $emp->phone ?? '' }}</div>
                    @endif
                    <div class="flex items-center gap-2 pt-1.5 border-t border-gray-100">
                        <button wire:click="openEdit({{ $emp->id }})"
                                class="flex-1 px-3 py-1.5 text-xs font-medium rounded-lg bg-indigo-50 text-indigo-700 hover:bg-indigo-100">Edit</button>
                        <button wire:click="toggleActive({{ $emp->id }})"
                                class="px-3 py-1.5 text-xs font-medium rounded-lg {{ $emp->is_active ? 'bg-amber-50 text-amber-700 hover:bg-amber-100' : 'bg-green-50 text-green-700 hover:bg-green-100' }}">
                            {{ $emp->is_active ? 'Deactivate' : 'Activate' }}
                        </button>
                        <button wire:click="delete({{ $emp->id }})" wire:confirm="Delete this employee?"
                                class="px-3 py-1.5 text-xs font-medium rounded-lg bg-red-50 text-red-700 hover:bg-red-100">Delete</button>
                    </div>
                </div>
            @empty
                <div class="p-8 text-center text-gray-400 text-sm font-medium">No employees yet. Add one or import from CSV.</div>
            @endforelse
        </div>

        {{-- Desktop table --}}
        <table class="hidden md:table min-w-full divide-y divide-gray-100 text-sm">
            <thead class="bg-gray-50 text-gray-500 uppercase text-xs tracking-wider">
                <tr>
                    <th class="px-4 py-3 text-left">Name</th>
                    <th class="px-4 py-3 text-left">Staff ID</th>
                    <th class="px-4 py-3 text-left">Designation</th>
                    <th class="px-4 py-3 text-left">Department</th>
                    <th class="px-4 py-3 text-left">Outlet</th>
                    <th class="px-4 py-3 text-left">Email</th>
                    <th class="px-4 py-3 text-left">Phone</th>
                    <th class="px-4 py-3 text-center">Status</th>
                    <th class="px-4 py-3 text-center">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse ($employees as $emp)
                    <tr class="hover:bg-gray-50 {{ ! $emp->is_active ? 'opacity-60' : '' }}">
                        <td class="px-4 py-3 font-medium text-gray-800">{{ $emp->name }}</td>
                        <td class="px-4 py-3 text-gray-600 font-mono text-xs">{{ $emp->staff_id ?? '—' }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ $emp->designation ?? '—' }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ $emp->department ?? '—' }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ $emp->outlet?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-gray-600 text-xs">{{ $emp->email ?? '—' }}</td>
                        <td class="px-4 py-3 text-gray-600 text-xs">{{ $emp->phone ?? '—' }}</td>
                        <td class="px-4 py-3 text-center">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $emp->is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                                {{ $emp->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-center gap-1">
                                <button wire:click="openEdit({{ $emp->id }})"
                                        title="Edit"
                                        class="text-indigo-500 hover:text-indigo-700 p-1">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                </button>
                                <button wire:click="toggleActive({{ $emp->id }})"
                                        title="{{ $emp->is_active ? 'Deactivate' : 'Activate' }}"
                                        class="text-amber-500 hover:text-amber-700 p-1">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                </button>
                                <button wire:click="delete({{ $emp->id }})" wire:confirm="Delete this employee?"
                                        title="Delete"
                                        class="text-red-400 hover:text-red-600 p-1">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M1 7h22M9 7V4a2 2 0 012-2h2a2 2 0 012 2v3"/></svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="px-4 py-8 text-center text-gray-400">No employees yet. Add one or import from CSV.</td></tr>
                @endforelse
            </tbody>
        </table>

        @if ($employees->hasPages())
            <div class="px-4 py-3 border-t border-gray-100">{{ $employees->links() }}</div>
        @endif
    </div>

    {{-- ── Add / Edit modal (teleported to body to escape sidebar transform) --}}
    <div x-data="{ open: @entangle('showForm') }">
    <template x-teleport="body">
    <div x-show="open" x-cloak
         @keydown.escape.window="open = false"
         class="fixed inset-0 z-[100] overflow-y-auto">
        <div class="fixed inset-0 bg-black/50" @click="open = false"></div>
        <div class="relative min-h-full flex items-start sm:items-center justify-center p-4">
            <div class="relative bg-white rounded-xl shadow-xl w-full max-w-lg" @click.stop>
                <div class="flex items-center justify-between px-5 py-3 border-b border-gray-100">
                    <h3 class="text-sm font-semibold text-gray-800">{{ $editingId ? 'Edit Employee' : 'Add Employee' }}</h3>
                    <button @click="open = false" class="text-gray-400 hover:text-gray-600 p-1">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <form wire:submit.prevent="save" class="p-5 space-y-3">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="text-xs font-semibold text-gray-600">Outlet <span class="text-red-500">*</span></label>
                            <select wire:model="f_outlet_id" class="mt-1 w-full text-sm rounded-lg border-gray-300">
                                <option value="">— Select —</option>
                                @foreach ($outlets as $o)
                                    <option value="{{ $o->id }}">{{ $o->name }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('f_outlet_id')" class="mt-1" />
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-gray-600">Staff ID</label>
                            <input type="text" wire:model="f_staff_id" class="mt-1 w-full text-sm rounded-lg border-gray-300" placeholder="e.g. EMP-001" />
                            <x-input-error :messages="$errors->get('f_staff_id')" class="mt-1" />
                        </div>
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-gray-600">Employee Name <span class="text-red-500">*</span></label>
                        <input type="text" wire:model="f_name" class="mt-1 w-full text-sm rounded-lg border-gray-300" />
                        <x-input-error :messages="$errors->get('f_name')" class="mt-1" />
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="text-xs font-semibold text-gray-600">Designation</label>
                            <input type="text" wire:model="f_designation" class="mt-1 w-full text-sm rounded-lg border-gray-300" placeholder="e.g. Kitchen Helper" />
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-gray-600">Department</label>
                            <input type="text" wire:model="f_department" class="mt-1 w-full text-sm rounded-lg border-gray-300" placeholder="e.g. Kitchen" />
                        </div>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="text-xs font-semibold text-gray-600">E-mail</label>
                            <input type="email" wire:model="f_email" class="mt-1 w-full text-sm rounded-lg border-gray-300" />
                            <x-input-error :messages="$errors->get('f_email')" class="mt-1" />
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-gray-600">Phone</label>
                            <input type="text" wire:model="f_phone" class="mt-1 w-full text-sm rounded-lg border-gray-300" />
                        </div>
                    </div>
                    <label class="inline-flex items-center gap-2">
                        <input type="checkbox" wire:model="f_is_active" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                        <span class="text-sm text-gray-700">Active</span>
                    </label>
                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-gray-100">
                        <button type="button" @click="open = false" class="px-4 py-2 text-sm text-gray-600 border border-gray-200 rounded-lg hover:bg-gray-50">Cancel</button>
                        <button type="submit" class="px-4 py-2 text-sm text-white bg-indigo-600 rounded-lg hover:bg-indigo-700">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    </template>
    </div>

    {{-- ── CSV import modal (teleported to body) ───────────────────────── --}}
    <div x-data="{ open: @entangle('showImport') }">
    <template x-teleport="body">
    <div x-show="open" x-cloak
         @keydown.escape.window="open = false"
         class="fixed inset-0 z-[100] overflow-y-auto">
        <div class="fixed inset-0 bg-black/50" @click="open = false"></div>
        <div class="relative min-h-full flex items-start sm:items-center justify-center p-4">
            <div class="relative bg-white rounded-xl shadow-xl w-full max-w-lg" @click.stop>
                <div class="flex items-center justify-between px-5 py-3 border-b border-gray-100">
                    <h3 class="text-sm font-semibold text-gray-800">Import Employees from CSV</h3>
                    <button @click="open = false" class="text-gray-400 hover:text-gray-600 p-1">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <div class="p-5 space-y-4">
                    <div class="px-3 py-2 bg-blue-50 border border-blue-200 text-blue-800 text-xs rounded-lg">
                        <p class="font-semibold mb-1">Expected columns</p>
                        <p>Outlet, Employee Name, Designation, Department, Staff ID, E-mail, Phone Number</p>
                        <p class="mt-1 text-blue-700">Existing employees are matched by Staff ID first, then E-mail, then (Outlet + Name). Matches update; new rows create.</p>
                    </div>

                    <div>
                        <label class="text-xs font-semibold text-gray-600">CSV File</label>
                        <input type="file" wire:model="csvFile" accept=".csv,text/csv" class="mt-1 w-full text-sm" />
                        <x-input-error :messages="$errors->get('csvFile')" class="mt-1" />
                        <button type="button" wire:click="downloadTemplate" class="mt-2 text-xs text-indigo-600 hover:text-indigo-800 underline">
                            Download template CSV
                        </button>
                    </div>

                    @if ($importResult)
                        <div class="px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg text-xs space-y-1">
                            <p class="font-semibold text-gray-700">Import complete</p>
                            <p class="text-gray-600">
                                Created: <strong class="text-green-600">{{ $importResult['created'] }}</strong> ·
                                Updated: <strong class="text-blue-600">{{ $importResult['updated'] }}</strong> ·
                                Skipped: <strong class="text-amber-600">{{ $importResult['skipped'] }}</strong>
                            </p>
                            @if (! empty($importResult['errors']))
                                <ul class="mt-2 list-disc list-inside text-red-600 space-y-0.5">
                                    @foreach ($importResult['errors'] as $err)
                                        <li>{{ $err }}</li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    @endif

                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-gray-100">
                        <button type="button" @click="open = false" class="px-4 py-2 text-sm text-gray-600 border border-gray-200 rounded-lg hover:bg-gray-50">Close</button>
                        <button type="button" wire:click="processImport"
                                wire:loading.attr="disabled"
                                class="px-4 py-2 text-sm text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 disabled:opacity-50">
                            <span wire:loading.remove wire:target="processImport">Import</span>
                            <span wire:loading wire:target="processImport">Importing…</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    </template>
    </div>
</div>
