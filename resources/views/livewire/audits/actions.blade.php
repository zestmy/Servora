<div>
    <x-page-header title="Corrective Actions" eyebrow="Outlet Audits"
                   subtitle="Every non-conformance still open across your outlets, by who owns the fix.">
        <x-slot:actions>
            <a href="{{ route('audits.index') }}" wire:navigate class="btn-secondary">Audits</a>
        </x-slot:actions>
    </x-page-header>

    <div class="mb-4 grid gap-3 sm:grid-cols-3">
        <button type="button" wire:click="$set('statusFilter', 'outstanding')" class="card p-4 text-left {{ $statusFilter === 'outstanding' ? 'ring-2 ring-brand-500' : '' }}">
            <p class="stat-label">Outstanding actions</p>
            <p class="stat-value">{{ $counts['outstanding'] }}</p>
        </button>
        <button type="button" wire:click="$set('statusFilter', 'overdue')" class="card p-4 text-left {{ $statusFilter === 'overdue' ? 'ring-2 ring-brand-500' : '' }}">
            <p class="stat-label">Overdue</p>
            <p class="stat-value {{ $counts['overdue'] ? 'text-danger-700' : '' }}">{{ $counts['overdue'] }}</p>
        </button>
        <button type="button" wire:click="$set('statusFilter', 'unassigned')" class="card p-4 text-left {{ $statusFilter === 'unassigned' ? 'ring-2 ring-brand-500' : '' }}">
            <p class="stat-label">Findings with no action</p>
            <p class="stat-value {{ $counts['unassigned'] ? 'text-warning-700' : '' }}">{{ $counts['unassigned'] }}</p>
        </button>
    </div>

    <div class="toolbar mb-4">
        <div class="flex w-full flex-col flex-wrap gap-3 sm:flex-row sm:items-center">
            <div class="min-w-0 flex-1 sm:min-w-[180px]">
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search item or action…" class="input" />
            </div>
            @if ($outlets->isNotEmpty())
                <select wire:model.live="outletFilter" class="input sm:w-44">
                    <option value="">All outlets</option>
                    @foreach ($outlets as $outlet)
                        <option value="{{ $outlet->id }}">{{ $outlet->name }}</option>
                    @endforeach
                </select>
            @endif
            <select wire:model.live="ownerFilter" class="input sm:w-48">
                <option value="">Any owner</option>
                @foreach ($owners as $o)
                    <option value="{{ $o->id }}">{{ $o->name }}{{ $o->designation ? ' · ' . $o->designation : '' }}</option>
                @endforeach
            </select>
            <select wire:model.live="severityFilter" class="input sm:w-36">
                <option value="">Any severity</option>
                <option value="major">Major</option>
                <option value="minor">Minor</option>
            </select>
            <select wire:model.live="statusFilter" class="input sm:w-40">
                <option value="outstanding">Outstanding</option>
                <option value="overdue">Overdue</option>
                <option value="unassigned">No action yet</option>
                <option value="verified">Verified</option>
                <option value="all">All actions</option>
            </select>
            <button wire:click="resetFilters" class="btn-ghost">Reset</button>
        </div>
    </div>

    @if (empty($groups))
        <div class="card p-10">
            <div class="empty-state">
                <x-icon name="check" size="h-8 w-8" class="text-success-600" />
                <p class="empty-title">Nothing here</p>
                <p class="empty-body">No corrective actions match these filters.</p>
            </div>
        </div>
    @else
        <div class="space-y-4">
            @foreach ($groups as $outletId => $group)
                <div class="card overflow-hidden" wire:key="grp-{{ $outletId }}">
                    <div class="flex items-center justify-between border-b border-gray-100 bg-gray-50 px-4 py-2.5">
                        <h2 class="text-sm font-semibold text-gray-900">{{ $group['outlet']?->name ?? 'Outlet' }}</h2>
                        <span class="text-xs text-gray-600">
                            {{ collect($group['owners'] ?? [])->flatten(1)->count() }} action{{ collect($group['owners'] ?? [])->flatten(1)->count() === 1 ? '' : 's' }}
                            @if (! empty($group['unassigned'])) · {{ count($group['unassigned']) }} finding{{ count($group['unassigned']) === 1 ? '' : 's' }} with no action @endif
                        </span>
                    </div>

                    @if (! empty($group['unassigned']))
                        <div class="border-b border-gray-100 bg-warning-50/50 px-4 py-2">
                            <p class="text-xs font-semibold uppercase tracking-wider text-warning-800">No action raised yet</p>
                        </div>
                        <ul class="divide-y divide-gray-100">
                            @foreach ($group['unassigned'] as $finding)
                                <li wire:key="uf-{{ $finding->id }}" class="flex flex-wrap items-center justify-between gap-2 px-4 py-3">
                                    <div class="min-w-0 flex-1">
                                        <p class="text-sm text-gray-900">{{ $finding->item_label }}</p>
                                        <p class="text-xs text-gray-600">
                                            {{ $finding->section_name }} · {{ $finding->audit?->audit_date?->format('d M Y') }}
                                            @if ($finding->isMajor()) · <span class="font-medium text-danger-700">Major</span> @endif
                                        </p>
                                    </div>
                                    <a href="{{ route('audits.show', $finding->audit_id) }}" wire:navigate class="btn-secondary text-xs">Open audit</a>
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    @foreach ($group['owners'] ?? [] as $ownerKey => $actions)
                        <div class="border-b border-gray-100 bg-gray-50/60 px-4 py-2">
                            <p class="text-xs font-semibold uppercase tracking-wider text-gray-700">{{ $ownerKey }}</p>
                        </div>
                        <ul class="divide-y divide-gray-100">
                            @foreach ($actions as $action)
                                <li wire:key="act-{{ $action->id }}" class="px-4 py-3">
                                    <div class="flex flex-wrap items-start justify-between gap-2">
                                        <div class="min-w-0 flex-1">
                                            <p class="text-sm text-gray-900">{{ $action->description }}</p>
                                            <p class="mt-0.5 text-xs text-gray-600">
                                                <a href="{{ route('audits.show', $action->finding?->audit_id) }}" wire:navigate class="hover:text-brand-700">
                                                    {{ $action->finding?->item_label }}
                                                </a>
                                                · {{ $action->finding?->section_name }}
                                                · {{ $action->finding?->audit?->audit_date?->format('d M Y') }}
                                                @if ($action->finding?->isMajor()) · <span class="font-medium text-danger-700">Major</span> @endif
                                            </p>
                                        </div>
                                        <div class="flex flex-shrink-0 flex-wrap items-center gap-1.5">
                                            @if ($action->due_date)
                                                <span class="text-xs tabular-nums {{ $action->isOverdue() ? 'font-medium text-danger-700' : 'text-gray-600' }}">
                                                    due {{ $action->due_date->format('d M') }}
                                                </span>
                                            @endif
                                            <span @class([
                                                'badge-neutral' => $action->status === 'open',
                                                'badge-info'    => $action->status === 'in_progress',
                                                'badge-warning' => $action->status === 'done',
                                                'badge-success' => $action->status === 'verified',
                                            ])>{{ $action->statusLabel() }}</span>
                                            @if ($canManage && ! $action->isVerified())
                                                @if ($action->status === 'open')
                                                    <button type="button" wire:click="setStatus({{ $action->id }}, 'in_progress')" class="btn-ghost text-xs">Start</button>
                                                @endif
                                                @if ($action->status !== 'done')
                                                    <button type="button" wire:click="setStatus({{ $action->id }}, 'done')" class="btn-secondary text-xs">Done</button>
                                                @endif
                                                <button type="button" wire:click="verify({{ $action->id }})" class="btn-primary text-xs">Verify</button>
                                            @endif
                                        </div>
                                    </div>

                                    @php
                                        $evidence     = $action->photos->where('kind', 'evidence');
                                        $verification = $action->photos->where('kind', 'verification');
                                        $max          = \App\Models\CorrectiveActionPhoto::MAX_PER_KIND;
                                    @endphp
                                    @if ($evidence->isNotEmpty() || $verification->isNotEmpty() || $canManage)
                                        <div class="mt-2 flex flex-wrap items-end gap-x-4 gap-y-2">
                                            @foreach (['evidence' => ['Fix', $evidence], 'verification' => ['Verification', $verification]] as $kind => [$title, $set])
                                                @if ($set->isNotEmpty())
                                                    <div>
                                                        <p class="mb-1 text-[11px] font-medium uppercase tracking-wider {{ $kind === 'verification' ? 'text-success-700' : 'text-gray-600' }}">{{ $title }}</p>
                                                        <div class="flex flex-wrap gap-1.5">
                                                            @foreach ($set as $photo)
                                                                <a href="{{ $photo->url() }}" target="_blank" wire:key="ap-{{ $photo->id }}"
                                                                   class="block h-12 w-12 overflow-hidden rounded-control border {{ $kind === 'verification' ? 'border-success-300' : 'border-gray-200' }}">
                                                                    <img src="{{ $photo->url() }}" alt="" class="h-full w-full object-cover" loading="lazy" />
                                                                </a>
                                                            @endforeach
                                                        </div>
                                                    </div>
                                                @endif
                                            @endforeach

                                            @if ($canManage)
                                                <div class="flex flex-wrap gap-1">
                                                    @if (! $action->isVerified() && $evidence->count() < $max)
                                                        <label class="btn-ghost cursor-pointer text-xs">
                                                            <input type="file" accept="image/*" capture="environment" class="sr-only" wire:model="evidence.{{ $action->id }}" />
                                                            <span wire:loading.remove wire:target="evidence.{{ $action->id }}">+ Photo of fix</span>
                                                            <span wire:loading wire:target="evidence.{{ $action->id }}">Uploading…</span>
                                                        </label>
                                                    @endif
                                                    @if ($verification->count() < $max)
                                                        <label class="btn-ghost cursor-pointer text-xs text-success-700">
                                                            <input type="file" accept="image/*" capture="environment" class="sr-only" wire:model="verification.{{ $action->id }}" />
                                                            <span wire:loading.remove wire:target="verification.{{ $action->id }}">+ Verification photo</span>
                                                            <span wire:loading wire:target="verification.{{ $action->id }}">Uploading…</span>
                                                        </label>
                                                    @endif
                                                </div>
                                            @endif
                                        </div>
                                        @error('evidence.' . $action->id) <p class="error-text">{{ $message }}</p> @enderror
                                        @error('verification.' . $action->id) <p class="error-text">{{ $message }}</p> @enderror
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endforeach
                </div>
            @endforeach
        </div>
    @endif
</div>
