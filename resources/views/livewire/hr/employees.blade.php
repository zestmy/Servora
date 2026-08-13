<div>
@php
    // Carried into the employee form and handed straight back on save, so
    // working through one branch's staff — or through the outsourced or
    // resigned ones — does not bounce you to an unfiltered list after every
    // record. 'all' is a value throughout: it means the list was widened on
    // purpose, and an empty string in a URL cannot say that.
    $returnFilters = [
        'outlet'     => $outletFilter !== '' ? $outletFilter : 'all',
        'section'    => $sectionFilter !== '' ? $sectionFilter : 'all',
        'employment' => $employmentStatusFilter !== '' ? $employmentStatusFilter : 'all',
        'status'     => $statusFilter !== '' ? $statusFilter : 'all',
    ];
@endphp

    @once
        <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.6/Sortable.min.js"></script>
    @endonce

    @if (session()->has('success'))
        <div wire:key="flash-{{ microtime(true) }}" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)"
             class="mb-4 px-4 py-3 bg-success-50 border border-success-200 text-success-700 text-sm rounded-lg">
            {{ session('success') }}
        </div>
    @endif
    @if (session()->has('error'))
        <div class="mb-4 px-4 py-3 bg-danger-50 border border-danger-200 text-danger-700 text-sm rounded-lg">
            {{ session('error') }}
        </div>
    @endif

    {{-- Header --}}
    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        <div>
            <p class="text-xs text-gray-600">HR / Employees</p>
            <h2 class="text-lg font-semibold text-gray-700 mt-1 flex items-center gap-2">
                Employees
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-brand-100 text-brand-700">
                    {{ number_format($employees->total()) }}
                </span>
            </h2>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <x-download-link :href="route('hr.employees.export-pdf', ['search' => $search, 'outlet' => $outletFilter, 'section' => $sectionFilter, 'status' => $statusFilter, 'employment_status' => $employmentStatusFilter])"
                    title="Export PDF"
                    class="px-2.5 md:px-3 py-2 text-sm font-medium text-danger-600 border border-danger-200 rounded-lg hover:bg-danger-50 transition flex items-center gap-1.5">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                </svg>
                <span class="hidden sm:inline">PDF</span>
            </x-download-link>
            <x-download-link :href="route('hr.employees.export-excel', ['search' => $search, 'outlet' => $outletFilter, 'section' => $sectionFilter, 'status' => $statusFilter, 'employment_status' => $employmentStatusFilter])"
                    title="Export Excel"
                    class="px-2.5 md:px-3 py-2 text-sm font-medium text-success-700 border border-success-200 rounded-lg hover:bg-success-50 transition flex items-center gap-1.5">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M3 14h18m-9-4v8m-8 0h16a2 2 0 002-2V8a2 2 0 00-2-2H4a2 2 0 00-2 2v8a2 2 0 002 2z" />
                </svg>
                <span class="hidden sm:inline">Excel</span>
            </x-download-link>
            <button wire:click="downloadTemplate"
                    title="Download CSV template"
                    class="btn-secondary">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a2 2 0 002 2h12a2 2 0 002-2v-1M12 12v6m0 0l-3-3m3 3l3-3M12 4v4" />
                </svg>
                <span class="hidden sm:inline">Template</span>
            </button>
            <button wire:click="openImport"
                    title="Import CSV"
                    class="btn-secondary">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 13h6m-3-3v6m5 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <span class="hidden sm:inline">Import CSV</span>
            </button>
            @canDo('hr.employees.manage')
            <a href="{{ route('hr.employees.create', $returnFilters) }}" class="btn-primary">
                <span class="sm:hidden">+ Add</span>
                <span class="hidden sm:inline">+ Add Employee</span>
            </a>
            @endcanDo
        </div>
    </div>

    {{-- Food Handler: a held/not-held count, which the expiry card below
         deliberately does not answer since the certificate is one-off. --}}
    @php
        $fhPct = $foodHandlerStats['total'] > 0
            ? round($foodHandlerStats['taken'] / $foodHandlerStats['total'] * 100)
            : 0;
    @endphp
    <div class="card p-4 mb-4">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <p class="text-xs font-medium uppercase tracking-wider text-gray-500">Food Handler Certificate</p>
                <p class="text-xs text-gray-500 mt-0.5">
                    Active staff in this view
                    @if ($foodHandlerStats['not_taken'] > 0)
                        · <button wire:click="toggleFoodHandler" class="font-medium text-brand-600 hover:text-brand-800">
                            {{ $showFoodHandler ? 'Hide' : 'Show' }} who has not taken it
                        </button>
                    @endif
                </p>
            </div>
            <div class="flex items-center gap-6">
                <div>
                    <p class="text-2xl font-semibold tabular-nums text-success-700">{{ number_format($foodHandlerStats['taken']) }}</p>
                    <p class="text-xs text-gray-600">Taken</p>
                </div>
                <div>
                    <p class="text-2xl font-semibold tabular-nums {{ $foodHandlerStats['not_taken'] > 0 ? 'text-danger-600' : 'text-gray-400' }}">
                        {{ number_format($foodHandlerStats['not_taken']) }}
                    </p>
                    <p class="text-xs text-gray-600">Not taken</p>
                </div>
                <div class="hidden sm:block">
                    <p class="text-2xl font-semibold tabular-nums text-gray-900">{{ $fhPct }}%</p>
                    <p class="text-xs text-gray-600">Certified</p>
                </div>
            </div>
        </div>
        @if ($foodHandlerStats['total'] > 0)
            <div class="mt-3 h-2 w-full rounded-full bg-gray-100 overflow-hidden">
                {{-- Width is inline, not a Tailwind class: the scanner cannot
                     generate a class built from a runtime value. --}}
                <div class="h-full bg-success-500 rounded-full" style="width: {{ $fhPct }}%"></div>
            </div>
        @endif

        {{-- Who has not taken it. A count says there is a problem; these are
             the people someone has to book onto a course, so each name links
             straight to the record where the certificate gets ticked off. --}}
        @if ($showFoodHandler && $foodHandlerStats['missing']->isNotEmpty())
            @php $fhMore = $foodHandlerStats['not_taken'] - $foodHandlerStats['missing']->count(); @endphp
            <div class="mt-3 pt-3 border-t border-gray-100">
                <p class="text-xs font-medium text-gray-700 mb-2">
                    Not taken ({{ number_format($foodHandlerStats['not_taken']) }})
                </p>
                <div class="flex flex-wrap gap-1.5">
                    @foreach ($foodHandlerStats['missing'] as $fhEmp)
                        <a href="{{ route('hr.employees.edit', ['id' => $fhEmp->id] + $returnFilters) }}"
                           wire:key="fh-{{ $fhEmp->id }}"
                           title="{{ collect([$fhEmp->outlet?->name, $fhEmp->section?->name])->filter()->join(' · ') ?: 'Open employee' }}"
                           class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium
                                  bg-danger-50 text-danger-700 border border-danger-200 hover:bg-danger-100 transition">
                            <x-employee-avatar :employee="$fhEmp" size="h-5 w-5" textSize="text-[9px]" />
                            {{ $fhEmp->name }}
                            @if ($fhEmp->staff_id)
                                <span class="font-mono text-[10px] text-danger-500">{{ $fhEmp->staff_id }}</span>
                            @endif
                        </a>
                    @endforeach
                </div>
                @if ($fhMore > 0)
                    <p class="mt-2 text-[11px] text-gray-500">
                        and {{ number_format($fhMore) }} more — filter by outlet or section to narrow the list.
                    </p>
                @endif
            </div>
        @endif
    </div>

    {{-- Compliance: what expires next, and who --}}
    @php $ds = \App\Services\Hr\DocumentExpiry::class; @endphp
    <div class="card p-4 mb-4">
        <div class="flex items-center justify-between gap-3">
            <div class="flex items-center gap-2">
                <h3 class="text-sm font-semibold text-gray-700">Documents &amp; Training</h3>
                @if ($compliance && array_sum($compliance['counts']) > 0)
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold
                                 {{ $compliance['counts'][$ds::EXPIRED] > 0 ? 'bg-danger-100 text-danger-700' : 'bg-warning-100 text-warning-700' }}">
                        {{ array_sum($compliance['counts']) }} need attention
                    </span>
                @endif
            </div>
            <button wire:click="toggleCompliance" class="text-xs font-medium text-brand-600 hover:text-brand-800">
                {{ $showCompliance ? 'Hide' : 'Show' }}
            </button>
        </div>

        @if ($showCompliance && $compliance)
            <p class="text-xs text-gray-500 mt-1">
                Active staff in this view ({{ $compliance['employees'] }}), expiring within {{ $compliance['warning_days'] }} days.
                <a href="{{ route('settings.certifications') }}" class="text-brand-600 hover:underline">Manage courses</a>
            </p>

            {{-- Per-document tallies --}}
            <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                @foreach ($compliance['documents'] as $doc)
                    @php
                        $needs = $doc[$ds::EXPIRED] + $doc[$ds::EXPIRING];
                        $tone  = $doc[$ds::EXPIRED] > 0
                            ? 'bg-danger-50 border-danger-200'
                            : ($needs > 0 ? 'bg-warning-50 border-warning-200' : 'bg-gray-50 border-gray-200');
                    @endphp
                    <div class="rounded-lg border p-3 {{ $tone }}">
                        <div class="flex items-start justify-between gap-2">
                            <p class="text-xs font-medium uppercase tracking-wider text-gray-600">{{ $doc['label'] }}</p>
                            @unless ($doc['required'])
                                <span class="text-[10px] text-gray-500 whitespace-nowrap">optional</span>
                            @endunless
                        </div>
                        <div class="mt-2 flex flex-wrap items-baseline gap-x-4 gap-y-1">
                            <span class="text-lg font-semibold tabular-nums {{ $doc[$ds::EXPIRED] > 0 ? 'text-danger-600' : 'text-gray-400' }}">
                                {{ $doc[$ds::EXPIRED] }}<span class="text-[11px] font-normal text-gray-500"> expired</span>
                            </span>
                            <span class="text-lg font-semibold tabular-nums {{ $doc[$ds::EXPIRING] > 0 ? 'text-warning-600' : 'text-gray-400' }}">
                                {{ $doc[$ds::EXPIRING] }}<span class="text-[11px] font-normal text-gray-500"> expiring</span>
                            </span>
                            <span class="text-lg font-semibold tabular-nums text-success-600">
                                {{ $doc[$ds::VALID] }}<span class="text-[11px] font-normal text-gray-500"> valid</span>
                            </span>
                        </div>
                        <p class="mt-1 text-[11px] text-gray-600">
                            @if ($doc[$ds::MISSING] > 0){{ $doc[$ds::MISSING] }} missing @endif
                            @if ($doc[$ds::UNDATED] > 0)· {{ $doc[$ds::UNDATED] }} with no expiry date @endif
                            {{-- Keep a space before @endif: Blade will not parse a directive
                                 that follows a word character, and it silently renders as text. --}}
                            @if ($doc[$ds::MISSING] === 0 && $doc[$ds::UNDATED] === 0) All recorded @endif
                        </p>
                    </div>
                @endforeach
            </div>

            {{-- Who, most urgent first --}}
            @php $rows = $compliance['rows']; @endphp
            @if ($rows->isEmpty())
                <p class="mt-4 px-3 py-4 text-center text-sm text-success-700 bg-success-50 border border-success-200 rounded-lg">
                    Everything is in date. Nothing expires in the next {{ $compliance['warning_days'] }} days.
                </p>
            @else
                <div class="mt-4 border border-gray-100 rounded-lg overflow-hidden">
                    <div class="divide-y divide-gray-50">
                        @foreach ($rows->take(12) as $row)
                            @php
                                $stateTone = [
                                    $ds::EXPIRED  => 'bg-danger-100 text-danger-700',
                                    $ds::EXPIRING => 'bg-warning-100 text-warning-700',
                                    $ds::UNDATED  => 'bg-gray-100 text-gray-600',
                                    $ds::MISSING  => 'bg-gray-100 text-gray-600',
                                ][$row['state']];
                            @endphp
                            <div wire:key="comp-{{ $row['employee_id'] }}-{{ $row['document_key'] }}"
                                 class="px-3 py-2 flex flex-wrap items-center gap-x-3 gap-y-1 hover:bg-gray-50">
                                <x-employee-avatar :id="$row['employee_id']" :name="$row['name']"
                                                   :photo="$row['photo_path'] ?? null"
                                                   size="h-7 w-7" textSize="text-[10px]" />
                                <a href="{{ route('hr.employees.edit', ['id' => $row['employee_id']] + $returnFilters) }}"
                                   class="text-sm font-medium text-gray-800 hover:text-brand-600 hover:underline">
                                    {{ $row['name'] }}
                                </a>
                                @if ($row['outlet'])
                                    <span class="text-[11px] text-gray-500">{{ $row['outlet'] }}</span>
                                @endif
                                <span class="text-xs text-gray-600">{{ $row['document'] }}</span>
                                <span class="ml-auto inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium whitespace-nowrap {{ $stateTone }}">
                                    @if ($row['state'] === $ds::EXPIRED)
                                        expired {{ abs($row['days']) }}d ago
                                    @elseif ($row['state'] === $ds::EXPIRING)
                                        {{ $row['days'] === 0 ? 'expires today' : 'in ' . $row['days'] . 'd' }}
                                    @elseif ($row['state'] === $ds::UNDATED)
                                        no expiry date
                                    @else
                                        not recorded
                                    @endif
                                </span>
                                @if ($row['expires_on'])
                                    <span class="text-[11px] text-gray-500 whitespace-nowrap w-20 text-right">{{ $row['expires_on']->format('d M Y') }}</span>
                                @endif
                            </div>
                        @endforeach
                    </div>
                    @if ($rows->count() > 12)
                        {{-- Say what was left out rather than letting the list
                             read as the whole story. --}}
                        <div class="px-3 py-2 bg-gray-50 text-xs text-gray-600 border-t border-gray-100">
                            Showing the 12 most urgent of {{ $rows->count() }}. The rest are in the scheduled reminder email.
                        </div>
                    @endif
                </div>
            @endif
        @endif
    </div>

    {{-- Filter Bar --}}
    <div class="card p-4 mb-4">
        <div class="flex flex-col sm:flex-row flex-wrap gap-3">
            <div class="flex-1 min-w-[180px]">
                <input type="text" wire:model.live.debounce.300ms="search"
                       placeholder="Search name, staff ID, email, designation…"
                       class="w-full text-sm rounded-lg border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500" />
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
                <option value="exclude_outsourcing">All Exclude Outsourcing</option>
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
               class="text-xs text-brand-600 hover:text-brand-800 underline self-center">
                Manage Sections
            </a>
        </div>
    </div>

    {{-- List --}}
    <div class="card overflow-hidden">

        {{-- Table — horizontally scrollable on mobile so every column (staff ID,
             designation, section, email, phone…) stays reachable. --}}
      <div class="overflow-x-auto">
        <table class="table-surface {{ $canViewPay ? 'min-w-[1950px]' : 'min-w-[1650px]' }}">
            <thead>
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
                    {{-- Pay-sensitive columns: hr.compensation only. --}}
                    @if ($canViewPay)
                        <th class="px-4 py-3 text-right">Salary</th>
                        <th class="px-4 py-3 text-right">Service Pts</th>
                    @endif
                    <th class="px-4 py-3 text-center">Status</th>
                    <th class="px-4 py-3 text-center sticky right-0 z-10 bg-gray-50 border-l border-gray-100">Actions</th>
                </tr>
            </thead>
            <tbody
                   x-data x-init="new Sortable($el, {
                       handle: '.row-drag-handle',
                       animation: 150,
                       ghostClass: 'bg-brand-50',
                       onEnd(e) {
                           const ids = Array.from(e.from.querySelectorAll('tr[data-employee-id]'))
                               .map(row => parseInt(row.dataset.employeeId));
                           $wire.reorderRows(ids);
                       }
                   })">
                @forelse ($employees as $emp)
                    <tr wire:key="emp-row-{{ $emp->id }}" data-employee-id="{{ $emp->id }}"
                        class="group hover:bg-gray-50 {{ ! $emp->is_active ? 'opacity-60' : '' }}">
                        <td class="px-4 py-3 text-gray-600 text-xs whitespace-nowrap">
                            <span class="row-drag-handle inline-flex align-middle cursor-grab active:cursor-grabbing text-gray-500 hover:text-gray-900"
                                  title="Drag to reorder">
                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 8h16M4 16h16"/></svg>
                            </span>
                            <span class="align-middle">{{ $employees->firstItem() + $loop->index }}</span>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-3">
                                <x-employee-avatar :employee="$emp" />
                                <div class="min-w-0">
                            <a href="{{ route('hr.employees.edit', ['id' => $emp->id] + $returnFilters) }}" title="Edit employee"
                               class="font-medium text-gray-800 text-left hover:text-brand-600 hover:underline">
                                {{ $emp->name }}
                            </a>
                            {{-- Straight to allowances, bank and statutory details.
                                 They are a screen away from the employee record, so
                                 without this the only route is knowing to start at
                                 HR → Compensation. --}}
                            @if ($canViewPay)
                                <a href="{{ route('hr.compensation.employee', $emp->id) }}"
                                   title="Allowances, bank account and statutory details"
                                   class="block text-[10px] text-brand-600 hover:text-brand-800 hover:underline">
                                    Pay &amp; bank details
                                </a>
                            @endif
                                </div>
                            </div>
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
                                    /*
                                     * Keyed on employment status, and READ
                                     * WITH A FALLBACK — a status added to
                                     * Employee::EMPLOYMENT_STATUSES without a
                                     * colour here took the whole list down
                                     * with an undefined key, which is how
                                     * "Internship" 500'd this screen.
                                     *
                                     * A missing colour should be a plain grey
                                     * badge, not an outage.
                                     */
                                    $esColors = [
                                        'probation'          => 'bg-warning-100 text-warning-700',
                                        'confirmed'          => 'bg-success-100 text-success-700',
                                        'extended_probation' => 'bg-orange-100 text-orange-700',
                                        'partimer'           => 'bg-purple-100 text-purple-700',
                                        'internship'         => 'bg-brand-100 text-brand-700',
                                        'outsourcing'        => 'bg-blue-100 text-blue-700',
                                        'resigned'           => 'bg-gray-200 text-gray-700',
                                    ];
                                    $probationOverdue = in_array($emp->employment_status, ['probation', 'extended_probation'], true)
                                        && $emp->employment_status_date?->isBefore(today());
                                @endphp
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium whitespace-nowrap {{ $probationOverdue ? 'bg-danger-100 text-danger-700' : ($esColors[$emp->employment_status] ?? 'bg-gray-100 text-gray-700') }}">
                                    {{ $emp->employmentStatusLabel() }}
                                </span>
                                @if ($emp->employmentStatusDetail())
                                    <div class="text-[10px] mt-0.5 whitespace-nowrap {{ $probationOverdue ? 'text-danger-500' : 'text-gray-600' }}">{{ $emp->employmentStatusDetail() }}</div>
                                @endif
                            @else
                                <span class="text-gray-600 text-xs">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-center">
                            <x-hr.doc-status :held="$emp->food_handler_certified" :expires="$emp->food_handler_expired_on"
                                             :tracks-expiry="$complianceSettings->expires('food_handler')"
                                             yes="Certified" :note="$emp->food_handler_cert_no" />
                        </td>
                        <td class="px-4 py-3 text-center">
                            <x-hr.doc-status :held="$emp->typhoid_card" :expires="$emp->typhoid_expired_on"
                                             :tracks-expiry="$complianceSettings->expires('typhoid')" />
                        </td>
                        <td class="px-4 py-3 text-center">
                            <x-hr.doc-status :held="$emp->halal_training" :expires="$emp->halal_training_expired_on"
                                             :tracks-expiry="$complianceSettings->expires('halal')" />
                            @if ($emp->halal_training && $emp->halal_training_date)
                                <div class="text-[10px] text-gray-600 mt-0.5 whitespace-nowrap">attended {{ $emp->halal_training_date->format('d M Y') }}</div>
                            @endif
                        </td>
                        @if ($canViewPay)
                            <td class="px-4 py-3 text-right text-gray-700 tabular-nums whitespace-nowrap">
                                @if ($emp->basic_salary !== null)
                                    {{ number_format((float) $emp->basic_salary, 2) }}
                                    <span class="text-[10px] text-gray-600">{{ \App\Models\Employee::PAY_TYPE_SUFFIXES[$emp->pay_type] ?? '' }}</span>
                                @else
                                    <span class="text-gray-600">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right text-gray-600 tabular-nums">
                                {{ $emp->service_points_entitlement !== null ? number_format((float) $emp->service_points_entitlement, 2) : '—' }}
                            </td>
                        @endif
                        <td class="px-4 py-3 text-center">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $emp->is_active ? 'bg-success-100 text-success-700' : 'bg-gray-100 text-gray-500' }}">
                                {{ $emp->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 sticky right-0 z-10 bg-white group-hover:bg-gray-50 border-l border-gray-100">
                            <div class="flex items-center justify-center gap-1">
                                <a href="{{ route('hr.employees.edit', ['id' => $emp->id] + $returnFilters) }}"
                                   title="Edit"
                                   class="px-2 py-1 text-xs font-medium rounded-md bg-brand-50 text-brand-700 hover:bg-brand-100">
                                    Edit
                                </a>
                                <button wire:click="toggleActive({{ $emp->id }})"
                                        title="{{ $emp->is_active ? 'Deactivate' : 'Activate' }}"
                                        class="text-warning-500 hover:text-warning-700 p-1">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                </button>
                                @canDo('hr.employees.delete')
                                <button wire:click="delete({{ $emp->id }})" wire:confirm="Delete this employee?"
                                        title="Delete"
                                        class="text-danger-400 hover:text-danger-600 p-1">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M1 7h22M9 7V4a2 2 0 012-2h2a2 2 0 012 2v3"/></svg>
                                </button>
                                @endcanDo
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="{{ $canViewPay ? 17 : 15 }}" class="px-4 py-8 text-center text-gray-600">No employees yet. Add one or import from CSV.</td></tr>
                @endforelse
            </tbody>
        </table>
      </div>

        @if ($employees->hasPages())
            <div class="px-4 py-3 border-t border-gray-100">{{ $employees->links() }}</div>
        @endif
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
                    <button @click="open = false" class="text-gray-600 hover:text-gray-900 p-1">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <div class="p-5 space-y-4">
                    <div class="px-3 py-2 bg-blue-50 border border-blue-200 text-blue-800 text-xs rounded-lg">
                        <p class="font-semibold mb-1">Expected columns</p>
                        <p>Outlet, Employee Name, Designation, Section, Staff ID, E-mail, Phone Number, Join Date, Employment Status, Employment Status Date, Outsourcing Company, Food Handler Certified, Food Handler Cert No, Food Handler Expiry, Typhoid Card, Typhoid Valid From, Typhoid Expired On, Halal Awareness Training, Halal Training Date, Halal Training Expiry, Break Minutes@if ($canViewPay), Service Points Entitlement, Basic Salary, Pay Type@endif</p>
                        <p class="mt-0.5 text-blue-700">("Department" is also accepted as an alias for Section.)</p>
                        {{-- Blank and 0 mean different things here, same as on the employee form. --}}
                        <p class="mt-0.5 text-blue-700">Break Minutes: leave the cell blank to follow the duty roster's rest duration, or enter 0 for no break allowance at all.</p>
                        @unless ($canViewPay)
                            <p class="mt-0.5 text-blue-700">Salary and Service Points columns are ignored — you don't have access to compensation data.</p>
                        @endunless
                        <p class="mt-1 text-blue-700">Existing employees are matched by Staff ID first, then E-mail, then (Outlet + Name). Matches update; new rows create.</p>
                        {{-- Catalogue courses are per-employee records, not columns, so they
                             are not importable — say so rather than let them look supported. --}}
                        <p class="mt-0.5 text-blue-700">Courses from Settings → Certifications &amp; Training are recorded on the employee form, not through this file.</p>
                    </div>

                    <div>
                        <label class="text-xs font-semibold text-gray-600">CSV File</label>
                        <input type="file" wire:model="csvFile" accept=".csv,text/csv" class="mt-1 w-full text-sm" />
                        <x-input-error :messages="$errors->get('csvFile')" class="mt-1" />
                        <button type="button" wire:click="downloadTemplate" class="mt-2 text-xs text-brand-600 hover:text-brand-800 underline">
                            Download template CSV
                        </button>
                    </div>

                    @if ($importResult)
                        <div class="px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg text-xs space-y-1">
                            <p class="font-semibold text-gray-700">Import complete</p>
                            <p class="text-gray-600">
                                Created: <strong class="text-success-600">{{ $importResult['created'] }}</strong> ·
                                Updated: <strong class="text-blue-600">{{ $importResult['updated'] }}</strong> ·
                                Skipped: <strong class="text-warning-600">{{ $importResult['skipped'] }}</strong>
                            </p>
                            @if (! empty($importResult['errors']))
                                <ul class="mt-2 list-disc list-inside text-danger-600 space-y-0.5">
                                    @foreach ($importResult['errors'] as $err)
                                        <li>{{ $err }}</li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    @endif

                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-gray-100">
                        <button type="button" @click="open = false" class="btn-secondary">Close</button>
                        <button type="button" wire:click="processImport"
                                wire:loading.attr="disabled"
                                class="btn-primary">
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
