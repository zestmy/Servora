<div>
    @if (session()->has('success'))
        <div wire:key="flash-{{ microtime(true) }}" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3500)"
             class="alert-success mb-4">{{ session('success') }}</div>
    @endif

    <x-page-header title="Appointed Auditors" eyebrow="Outlet Audits · Settings"
                   subtitle="The people an audit form's “Appointed auditor” field offers. Company-wide — an outlet's own staff come from the “Outlet employee” field instead.">
        <x-slot:actions>
            <a href="{{ route('audits.templates') }}" wire:navigate class="btn-secondary">Audit Forms</a>
            <a href="{{ route('settings.index', ['module' => 'outlet-audits']) }}" wire:navigate class="btn-secondary">Settings</a>
        </x-slot:actions>
    </x-page-header>

    @if ($canManage)
        <div class="card mb-4 p-5">
            <label class="label" for="auditor-search">Appoint an employee</label>
            <div class="relative mt-1 max-w-lg">
                <input id="auditor-search" type="text" wire:model.live.debounce.300ms="search" class="input"
                       placeholder="Search by name, staff ID or designation…" autocomplete="off" />
                @if ($results->isNotEmpty())
                    <div class="absolute z-20 mt-1 w-full overflow-hidden rounded-control border border-gray-200 bg-white shadow-e3">
                        @foreach ($results as $e)
                            <button type="button" wire:click="appoint({{ $e->id }})"
                                    class="flex min-h-[2.75rem] w-full items-center justify-between gap-3 border-b border-gray-50 px-4 py-2 text-left text-sm last:border-0 hover:bg-brand-50">
                                <span class="min-w-0">
                                    <span class="font-medium text-gray-900">{{ $e->name }}</span>
                                    <span class="block text-xs text-gray-600">{{ $e->designation ?: '—' }}{{ $e->outlet ? ' · ' . $e->outlet->name : '' }}</span>
                                </span>
                                <span class="text-xs font-medium text-brand-700">Appoint</span>
                            </button>
                        @endforeach
                    </div>
                @elseif (mb_strlen($search) >= 2)
                    <p class="help mt-1">Nobody matching who is not already appointed.</p>
                @endif
            </div>
            <p class="help mt-2">Only active employees can be appointed. An auditor who leaves drops off the list when their record is deactivated.</p>
        </div>
    @endif

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table-surface min-w-[640px]">
                <thead>
                    <tr>
                        <th class="px-4 py-3 text-left">Auditor</th>
                        <th class="px-4 py-3 text-left w-44">Designation</th>
                        <th class="px-4 py-3 text-left w-48">Home outlet</th>
                        <th class="px-4 py-3 text-left w-44">Appointed</th>
                        <th class="px-4 py-3 w-16"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($auditors as $a)
                        <tr wire:key="aud-{{ $a->id }}" class="hover:bg-gray-50">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <x-employee-avatar :employee="$a->employee" size="h-8 w-8" textSize="text-[10px]" />
                                    <span class="font-medium text-gray-900">{{ $a->employee->name }}</span>
                                    @unless ($a->employee->is_active)<span class="badge-neutral">Inactive</span>@endunless
                                </div>
                            </td>
                            <td class="px-4 py-3 text-gray-600">{{ $a->employee->designation ?: '—' }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $a->employee->outlet?->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $a->created_at->format('d M Y') }}{{ $a->appointedBy ? ' · ' . $a->appointedBy->name : '' }}</td>
                            <td class="px-4 py-3 text-right">
                                @if ($canManage)
                                    <button wire:click="remove({{ $a->id }})" class="icon-btn icon-btn-danger" title="Remove"
                                            data-confirm-delete="Remove {{ $a->employee->name }} from the appointed auditors. Audits already conducted keep the name.">
                                        <x-icon name="trash" size="h-4 w-4" />
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-12">
                                <div class="empty-state">
                                    <p class="empty-title">No appointed auditors yet</p>
                                    <p class="empty-body">Search for the QA staff who conduct audits and appoint them. Forms with an “Appointed auditor” field will then offer them.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
