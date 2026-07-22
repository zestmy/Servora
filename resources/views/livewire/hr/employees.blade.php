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
            <h2 class="text-lg font-semibold text-gray-700 mt-1 flex items-center gap-2">
                Employees
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-indigo-100 text-indigo-700">
                    {{ number_format($employees->total()) }}
                </span>
            </h2>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <x-download-link :href="route('hr.employees.export-pdf', ['search' => $search, 'outlet' => $outletFilter, 'section' => $sectionFilter, 'status' => $statusFilter, 'employment_status' => $employmentStatusFilter])"
                    title="Export PDF"
                    class="px-2.5 md:px-3 py-2 text-sm font-medium text-red-600 border border-red-200 rounded-lg hover:bg-red-50 transition flex items-center gap-1.5">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                </svg>
                <span class="hidden sm:inline">PDF</span>
            </x-download-link>
            <x-download-link :href="route('hr.employees.export-excel', ['search' => $search, 'outlet' => $outletFilter, 'section' => $sectionFilter, 'status' => $statusFilter, 'employment_status' => $employmentStatusFilter])"
                    title="Export Excel"
                    class="px-2.5 md:px-3 py-2 text-sm font-medium text-green-700 border border-green-200 rounded-lg hover:bg-green-50 transition flex items-center gap-1.5">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M3 14h18m-9-4v8m-8 0h16a2 2 0 002-2V8a2 2 0 00-2-2H4a2 2 0 00-2 2v8a2 2 0 002 2z" />
                </svg>
                <span class="hidden sm:inline">Excel</span>
            </x-download-link>
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
        <div class="flex flex-col sm:flex-row flex-wrap gap-3">
            <div class="flex-1 min-w-[180px]">
                <input type="text" wire:model.live.debounce.300ms="search"
                       placeholder="Search name, staff ID, email, designation…"
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
            <select wire:model.live="statusFilter" class="text-sm rounded-lg border-gray-300 shadow-sm">
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
                <option value="">All</option>
            </select>
            <a href="{{ route('settings.sections') }}"
               class="text-xs text-indigo-600 hover:text-indigo-800 underline self-center">
                Manage Sections
            </a>
        </div>
    </div>

    {{-- List --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">

        {{-- Table — horizontally scrollable on mobile so every column (staff ID,
             designation, section, email, phone…) stays reachable. --}}
      <div class="overflow-x-auto">
        <table class="min-w-[1750px] divide-y divide-gray-100 text-sm">
            <thead class="bg-gray-50 text-gray-500 uppercase text-xs tracking-wider">
                <tr>
                    <th class="px-4 py-3 text-left w-12">#</th>
                    <th class="px-4 py-3 text-left">Name</th>
                    <th class="px-4 py-3 text-left">Staff ID</th>
                    <th class="px-4 py-3 text-left">Designation</th>
                    <th class="px-4 py-3 text-left">Section</th>
                    <th class="px-4 py-3 text-left">Outlet</th>
                    <th class="px-4 py-3 text-left">Email</th>
                    <th class="px-4 py-3 text-left">Phone</th>
                    <th class="px-4 py-3 text-left">Join Date</th>
                    <th class="px-4 py-3 text-center">Employment</th>
                    <th class="px-4 py-3 text-center">Food Handler</th>
                    <th class="px-4 py-3 text-center">Typhoid Card</th>
                    <th class="px-4 py-3 text-center">Halal Training</th>
                    <th class="px-4 py-3 text-right">Service Pts</th>
                    <th class="px-4 py-3 text-center">Status</th>
                    <th class="px-4 py-3 text-center sticky right-0 z-10 bg-gray-50 border-l border-gray-100">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse ($employees as $emp)
                    <tr class="group hover:bg-gray-50 {{ ! $emp->is_active ? 'opacity-60' : '' }}">
                        <td class="px-4 py-3 text-gray-400 text-xs">{{ $employees->firstItem() + $loop->index }}</td>
                        <td class="px-4 py-3">
                            <button wire:click="openEdit({{ $emp->id }})" title="Edit employee"
                                    class="font-medium text-gray-800 text-left hover:text-indigo-600 hover:underline">
                                {{ $emp->name }}
                            </button>
                        </td>
                        <td class="px-4 py-3 text-gray-600 font-mono text-xs">{{ $emp->staff_id ?? '—' }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ $emp->designation ?? '—' }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ $emp->section?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ $emp->outlet?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-gray-600 text-xs">{{ $emp->email ?? '—' }}</td>
                        <td class="px-4 py-3 text-gray-600 text-xs">{{ $emp->phone ?? '—' }}</td>
                        <td class="px-4 py-3 text-gray-600 text-xs whitespace-nowrap">{{ $emp->join_date?->format('d M Y') ?? '—' }}</td>
                        <td class="px-4 py-3 text-center">
                            @if ($emp->employment_status)
                                @php
                                    $esColors = [
                                        'probation'          => 'bg-amber-100 text-amber-700',
                                        'confirmed'          => 'bg-green-100 text-green-700',
                                        'extended_probation' => 'bg-orange-100 text-orange-700',
                                        'outsourcing'        => 'bg-blue-100 text-blue-700',
                                    ];
                                    $probationOverdue = in_array($emp->employment_status, ['probation', 'extended_probation'], true)
                                        && $emp->employment_status_date?->isBefore(today());
                                @endphp
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium whitespace-nowrap {{ $probationOverdue ? 'bg-red-100 text-red-700' : $esColors[$emp->employment_status] }}">
                                    {{ $emp->employmentStatusLabel() }}
                                </span>
                                @if ($emp->employmentStatusDetail())
                                    <div class="text-[10px] mt-0.5 whitespace-nowrap {{ $probationOverdue ? 'text-red-500' : 'text-gray-400' }}">{{ $emp->employmentStatusDetail() }}</div>
                                @endif
                            @else
                                <span class="text-gray-400 text-xs">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-center">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $emp->food_handler_certified ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                                {{ $emp->food_handler_certified ? 'Certified' : 'No' }}
                            </span>
                            @if ($emp->food_handler_certified && $emp->food_handler_cert_no)
                                <div class="text-[10px] text-gray-400 mt-0.5 font-mono whitespace-nowrap">{{ $emp->food_handler_cert_no }}</div>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-center">
                            @php $typhoidExpired = $emp->typhoid_card && $emp->typhoid_expired_on?->isBefore(today()); @endphp
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $typhoidExpired ? 'bg-red-100 text-red-700' : ($emp->typhoid_card ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500') }}">
                                {{ $typhoidExpired ? 'Expired' : ($emp->typhoid_card ? 'Yes' : 'No') }}
                            </span>
                            @if ($emp->typhoid_card && $emp->typhoid_expired_on)
                                <div class="text-[10px] mt-0.5 whitespace-nowrap {{ $typhoidExpired ? 'text-red-500' : 'text-gray-400' }}">
                                    {{ $typhoidExpired ? 'expired' : 'until' }} {{ $emp->typhoid_expired_on->format('d M Y') }}
                                </div>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-center">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $emp->halal_training ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                                {{ $emp->halal_training ? 'Yes' : 'No' }}
                            </span>
                            @if ($emp->halal_training && $emp->halal_training_date)
                                <div class="text-[10px] text-gray-400 mt-0.5 whitespace-nowrap">attended {{ $emp->halal_training_date->format('d M Y') }}</div>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right text-gray-600 tabular-nums">
                            {{ $emp->service_points_entitlement !== null ? number_format((float) $emp->service_points_entitlement, 2) : '—' }}
                        </td>
                        <td class="px-4 py-3 text-center">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $emp->is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                                {{ $emp->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 sticky right-0 z-10 bg-white group-hover:bg-gray-50 border-l border-gray-100">
                            <div class="flex items-center justify-center gap-1">
                                <button wire:click="openEdit({{ $emp->id }})"
                                        title="Edit"
                                        class="px-2 py-1 text-xs font-medium rounded-md bg-indigo-50 text-indigo-700 hover:bg-indigo-100">
                                    Edit
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
                    <tr><td colspan="16" class="px-4 py-8 text-center text-gray-400">No employees yet. Add one or import from CSV.</td></tr>
                @endforelse
            </tbody>
        </table>
      </div>

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
                            <label class="text-xs font-semibold text-gray-600">Section</label>
                            <select wire:model="f_section_id" class="mt-1 w-full text-sm rounded-lg border-gray-300">
                                <option value="">— None —</option>
                                @foreach ($sections as $s)
                                    <option value="{{ $s->id }}">{{ $s->name }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('f_section_id')" class="mt-1" />
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
                            <div class="mt-1 flex gap-2">
                                <select wire:model="f_phone_code" class="w-28 flex-shrink-0 text-sm rounded-lg border-gray-300">
                                    @foreach (\App\Models\Employee::PHONE_COUNTRY_CODES as $iso => $dial)
                                        <option value="{{ $dial }}">{{ $iso }} {{ $dial }}</option>
                                    @endforeach
                                </select>
                                <input type="text" wire:model="f_phone" class="w-full text-sm rounded-lg border-gray-300" placeholder="12 345 6789" />
                            </div>
                            <x-input-error :messages="$errors->get('f_phone_code')" class="mt-1" />
                            <x-input-error :messages="$errors->get('f_phone')" class="mt-1" />
                        </div>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="text-xs font-semibold text-gray-600">Join Date</label>
                            <input type="date" wire:model="f_join_date" class="mt-1 w-full text-sm rounded-lg border-gray-300" />
                            <x-input-error :messages="$errors->get('f_join_date')" class="mt-1" />
                        </div>
                        <div class="flex flex-col justify-end gap-2 pb-1">
                            <label class="inline-flex items-center gap-2">
                                <input type="checkbox" wire:model.live="f_food_handler_certified" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                                <span class="text-sm text-gray-700">Food Handler Certified</span>
                            </label>
                            <label class="inline-flex items-center gap-2">
                                <input type="checkbox" wire:model.live="f_typhoid_card" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                                <span class="text-sm text-gray-700">Typhoid Card (jab taken)</span>
                            </label>
                            <label class="inline-flex items-center gap-2">
                                <input type="checkbox" wire:model.live="f_halal_training" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                                <span class="text-sm text-gray-700">Halal Awareness Training</span>
                            </label>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="text-xs font-semibold text-gray-600">Employment Status</label>
                            <select wire:model.live="f_employment_status" class="mt-1 w-full text-sm rounded-lg border-gray-300">
                                <option value="">— None —</option>
                                @foreach (\App\Models\Employee::EMPLOYMENT_STATUSES as $esValue => $esLabel)
                                    <option value="{{ $esValue }}">{{ $esLabel }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('f_employment_status')" class="mt-1" />
                        </div>
                        @if (in_array($f_employment_status, ['probation', 'confirmed', 'extended_probation'], true))
                            <div>
                                <label class="text-xs font-semibold text-gray-600">
                                    {{ ['probation' => 'Probation — Until', 'confirmed' => 'Confirmed — On', 'extended_probation' => 'Probation Extended — Until'][$f_employment_status] }}
                                    <span class="text-red-500">*</span>
                                </label>
                                <input type="date" wire:model="f_employment_status_date" class="mt-1 w-full text-sm rounded-lg border-gray-300" />
                                <x-input-error :messages="$errors->get('f_employment_status_date')" class="mt-1" />
                            </div>
                        @elseif ($f_employment_status === 'outsourcing')
                            <div>
                                <label class="text-xs font-semibold text-gray-600">Outsourcing Company</label>
                                <select wire:model.live="f_outsourcing_provider" class="mt-1 w-full text-sm rounded-lg border-gray-300">
                                    <option value="experiva">Experiva</option>
                                    <option value="others">Others</option>
                                </select>
                                @if ($f_outsourcing_provider === 'others')
                                    <input type="text" wire:model="f_outsourcing_company" class="mt-2 w-full text-sm rounded-lg border-gray-300" placeholder="Company name" />
                                @endif
                                <x-input-error :messages="$errors->get('f_outsourcing_company')" class="mt-1" />
                            </div>
                        @endif
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="text-xs font-semibold text-gray-600">Service Points Entitlement</label>
                            <input type="number" step="0.01" min="0" wire:model="f_service_points"
                                   class="mt-1 w-full text-sm rounded-lg border-gray-300" placeholder="e.g. 1.50" />
                            <x-input-error :messages="$errors->get('f_service_points')" class="mt-1" />
                        </div>
                    </div>
                    @if ($f_food_handler_certified)
                        <div class="p-3 bg-gray-50 rounded-lg border border-gray-100">
                            <label class="text-xs font-semibold text-gray-600">Food Handler Certificate — Serial No.</label>
                            <input type="text" wire:model="f_food_handler_cert_no" class="mt-1 w-full text-sm rounded-lg border-gray-300" placeholder="e.g. FHC-2026-0123" />
                            <x-input-error :messages="$errors->get('f_food_handler_cert_no')" class="mt-1" />
                        </div>
                    @endif
                    @if ($f_typhoid_card)
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 p-3 bg-gray-50 rounded-lg border border-gray-100">
                            <div>
                                <label class="text-xs font-semibold text-gray-600">Typhoid Card — Valid From</label>
                                <input type="date" wire:model="f_typhoid_valid_from" class="mt-1 w-full text-sm rounded-lg border-gray-300" />
                                <x-input-error :messages="$errors->get('f_typhoid_valid_from')" class="mt-1" />
                            </div>
                            <div>
                                <label class="text-xs font-semibold text-gray-600">Typhoid Card — Expired On</label>
                                <input type="date" wire:model="f_typhoid_expired_on" class="mt-1 w-full text-sm rounded-lg border-gray-300" />
                                <x-input-error :messages="$errors->get('f_typhoid_expired_on')" class="mt-1" />
                            </div>
                        </div>
                    @endif
                    @if ($f_halal_training)
                        <div class="p-3 bg-gray-50 rounded-lg border border-gray-100">
                            <label class="text-xs font-semibold text-gray-600">Halal Awareness Training — Date Attended</label>
                            <input type="date" wire:model="f_halal_training_date" class="mt-1 w-full text-sm rounded-lg border-gray-300" />
                            <x-input-error :messages="$errors->get('f_halal_training_date')" class="mt-1" />
                        </div>
                    @endif
                    <label class="inline-flex items-center gap-2">
                        <input type="checkbox" wire:model="f_is_active" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                        <span class="text-sm text-gray-700">Active</span>
                    </label>

                    {{-- Recent activity (edit only) — bottom of form --}}
                    <x-audit-timeline :type="\App\Models\Employee::class" :id="$editingId" title="Employee Activity" />

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
                        <p>Outlet, Employee Name, Designation, Section, Staff ID, E-mail, Phone Number, Join Date, Employment Status, Employment Status Date, Outsourcing Company, Food Handler Certified, Food Handler Cert No, Typhoid Card, Typhoid Valid From, Typhoid Expired On, Halal Awareness Training, Halal Training Date, Service Points Entitlement</p>
                        <p class="mt-0.5 text-blue-700">("Department" is also accepted as an alias for Section.)</p>
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
