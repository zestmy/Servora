<div class="max-w-7xl mx-auto px-4 py-6">
    {{-- Header --}}
    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <div>
            <h1 class="text-lg font-semibold text-gray-800">Audit Logs</h1>
            <p class="text-xs text-gray-400 mt-0.5">Every tracked action across your operation — who did what, and when.</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('audit-logs.export.csv') }}{{ !empty($exportParams) ? '?' . http_build_query($exportParams) : '' }}"
               class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg border border-gray-300 bg-white text-gray-600 hover:bg-gray-50 transition">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3"/></svg>
                CSV
            </a>
            <a href="{{ route('audit-logs.export.pdf') }}{{ !empty($exportParams) ? '?' . http_build_query($exportParams) : '' }}"
               class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg border border-gray-300 bg-white text-gray-600 hover:bg-gray-50 transition">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                PDF
            </a>
        </div>
    </div>

    {{-- Filters --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 mb-4">
        {{-- Quick ranges --}}
        <div class="flex items-center gap-1.5 mb-3 flex-wrap">
            @foreach (['today' => 'Today', 'last_7' => 'Last 7 Days', 'last_30' => 'Last 30 Days', 'this_month' => 'This Month', 'last_month' => 'Last Month', 'all' => 'All Time'] as $key => $label)
                <button wire:click="setQuickRange('{{ $key }}')"
                        class="px-3 py-1.5 text-xs font-medium rounded-lg border transition
                               {{ $quickRange === $key ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-gray-600 border-gray-200 hover:bg-gray-50' }}">
                    {{ $label }}
                </button>
            @endforeach
        </div>

        <div class="flex flex-wrap gap-3 items-center">
            {{-- Date range --}}
            <div class="flex items-center gap-2">
                <span class="text-sm text-gray-500">From</span>
                <input type="date" wire:model.live="dateFrom" max="{{ date('Y-m-d') }}"
                       class="rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                <span class="text-sm text-gray-500">To</span>
                <input type="date" wire:model.live="dateTo" max="{{ date('Y-m-d') }}"
                       class="rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
            </div>

            {{-- User --}}
            <select wire:model.live="userFilter" class="rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">All Users</option>
                @foreach ($users as $u)
                    <option value="{{ $u->id }}">{{ $u->name }}</option>
                @endforeach
            </select>

            {{-- Branch --}}
            @if ($outlets->isNotEmpty())
                <select wire:model.live="outletFilter" class="rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">All Branches</option>
                    @foreach ($outlets as $outlet)
                        <option value="{{ $outlet->id }}">{{ $outlet->name }}</option>
                    @endforeach
                </select>
            @endif

            {{-- Module --}}
            <select wire:model.live="typeFilter" class="rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">All Modules</option>
                @foreach ($moduleOptions as $class => $label)
                    <option value="{{ $class }}">{{ $label }}</option>
                @endforeach
            </select>

            {{-- Event --}}
            <select wire:model.live="eventFilter" class="rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">All Actions</option>
                @foreach ($events as $ev)
                    <option value="{{ $ev }}">{{ ucwords(str_replace('_', ' ', $ev)) }}</option>
                @endforeach
            </select>

            {{-- Search --}}
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search user / record #…"
                   class="rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 min-w-[200px]" />

            <button wire:click="resetFilters" class="text-xs text-gray-400 hover:text-gray-600 underline">Reset</button>
        </div>
    </div>

    {{-- Table --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-[900px] w-full text-sm">
                <thead class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wider">
                    <tr>
                        <th class="px-4 py-3 text-left w-44">When</th>
                        <th class="px-4 py-3 text-left">User</th>
                        <th class="px-4 py-3 text-left">Action</th>
                        <th class="px-4 py-3 text-left">Module</th>
                        <th class="px-4 py-3 text-left">Record</th>
                        <th class="px-4 py-3 text-left">Branch</th>
                        <th class="px-4 py-3 text-right w-24">Details</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($logs as $log)
                        @php
                            $badge = match ($log->event) {
                                'created'                     => 'bg-green-100 text-green-700',
                                'updated'                     => 'bg-blue-100 text-blue-700',
                                'deleted', 'force_deleted'    => 'bg-red-100 text-red-600',
                                'restored'                    => 'bg-indigo-100 text-indigo-700',
                                'approved', 'received', 'submitted' => 'bg-green-100 text-green-700',
                                'rejected', 'cancelled'       => 'bg-red-100 text-red-600',
                                default                       => 'bg-gray-100 text-gray-600',
                            };
                            $old = $log->old_values ?? [];
                            $new = $log->new_values ?? [];
                            $keys = array_values(array_unique(array_merge(array_keys($old), array_keys($new))));
                        @endphp
                        <tr x-data="{ open: false }" wire:key="log-{{ $log->id }}" class="hover:bg-gray-50 transition align-top">
                            <td class="px-4 py-3 text-gray-700 whitespace-nowrap">{{ $log->created_at?->format('d M Y, H:i') }}</td>
                            <td class="px-4 py-3 text-gray-700">{{ $log->actorName() }}@if($log->guard === 'lms')<span class="text-[10px] text-gray-400"> (LMS)</span>@endif</td>
                            <td class="px-4 py-3">
                                <span class="inline-block px-2 py-0.5 rounded-full text-[11px] font-medium {{ $badge }}">
                                    {{ ucwords(str_replace('_', ' ', $log->event)) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-gray-700">{{ \App\Services\AuditLogService::label($log->auditable_type) }}</td>
                            <td class="px-4 py-3">
                                @if ($recLabel = $recordLabels[$log->auditable_type . ':' . $log->auditable_id] ?? null)
                                    <span class="text-gray-700">{{ $recLabel }}</span>
                                    <span class="text-[11px] text-gray-400 whitespace-nowrap">#{{ $log->auditable_id }}</span>
                                @else
                                    <span class="text-gray-500">#{{ $log->auditable_id }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-gray-500">{{ $log->outlet?->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-right">
                                @if (!empty($keys))
                                    <button @click="open = !open" class="text-xs text-indigo-600 hover:text-indigo-700">
                                        <span x-show="!open">View</span>
                                        <span x-show="open" x-cloak>Hide</span>
                                    </button>
                                @else
                                    <span class="text-xs text-gray-300">—</span>
                                @endif
                            </td>
                        </tr>
                        @if (!empty($keys))
                            <tr x-show="open" x-cloak wire:key="log-detail-{{ $log->id }}" class="bg-gray-50/60">
                                <td colspan="7" class="px-4 py-3">
                                    <div class="rounded-lg border border-gray-200 overflow-hidden">
                                        <table class="w-full text-xs">
                                            <thead class="bg-gray-100 text-gray-500 uppercase tracking-wider">
                                                <tr>
                                                    <th class="px-3 py-2 text-left w-48">Field</th>
                                                    <th class="px-3 py-2 text-left">Before</th>
                                                    <th class="px-3 py-2 text-left">After</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-gray-100">
                                                @foreach ($keys as $k)
                                                    @php
                                                        $before = array_key_exists($k, $old) ? $old[$k] : null;
                                                        $after  = array_key_exists($k, $new) ? $new[$k] : null;
                                                        $fmt = function ($v) use ($fkLabels, $k) {
                                                            if (is_null($v)) return '—';
                                                            if (is_bool($v)) return $v ? 'true' : 'false';
                                                            if (is_array($v)) return json_encode($v);
                                                            if (is_numeric($v) && isset($fkLabels[$k][(int) $v])) {
                                                                return $fkLabels[$k][(int) $v] . " (#{$v})";
                                                            }
                                                            return (string) $v;
                                                        };
                                                        // "Supplier Id" reads poorly once the value is a name — show "Supplier".
                                                        $fieldLabel = \App\Services\AuditLogService::isForeignKey($k)
                                                            ? ucwords(str_replace('_', ' ', preg_replace('/_id$/', '', $k)))
                                                            : ucwords(str_replace('_', ' ', $k));
                                                    @endphp
                                                    <tr>
                                                        <td class="px-3 py-1.5 font-medium text-gray-600">{{ $fieldLabel }}</td>
                                                        <td class="px-3 py-1.5 text-red-600 break-all">{{ $fmt($before) }}</td>
                                                        <td class="px-3 py-1.5 text-green-700 break-all">{{ $fmt($after) }}</td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                    @if ($log->ip_address)
                                        <p class="text-[10px] text-gray-400 mt-1.5">IP {{ $log->ip_address }}</p>
                                    @endif
                                </td>
                            </tr>
                        @endif
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-10 text-center text-sm text-gray-400">No audit entries match these filters.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">
        {{ $logs->links() }}
    </div>
</div>
