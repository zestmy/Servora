<div class="space-y-3">
    @if (session('success'))
        <div class="rounded-surface bg-success-50 border border-success-200 px-3 py-2.5 text-sm text-success-800">
            {{ session('success') }}
        </div>
    @endif

    <div class="card p-4">
        <p class="text-xs font-medium uppercase tracking-wider text-gray-500">Audit fixes</p>
        <p class="mt-1 text-sm text-gray-700">
            Things an audit found at {{ $employee->outlet?->name ?? 'your outlet' }} that are yours to put right.
            Mark them done here; the auditor verifies them.
        </p>
    </div>

    <div class="block overflow-x-auto">
        <div class="seg w-full">
            <button wire:click="$set('tab', 'mine')"   class="seg-item flex-1 min-h-[2.5rem] {{ $tab === 'mine' ? 'seg-item-on' : '' }}">Mine{{ $mine->count() ? ' (' . $mine->count() . ')' : '' }}</button>
            <button wire:click="$set('tab', 'outlet')" class="seg-item flex-1 min-h-[2.5rem] {{ $tab === 'outlet' ? 'seg-item-on' : '' }}">Outlet{{ $outlet->count() ? ' (' . $outlet->count() . ')' : '' }}</button>
            <button wire:click="$set('tab', 'done')"   class="seg-item flex-1 min-h-[2.5rem] {{ $tab === 'done' ? 'seg-item-on' : '' }}">Verified</button>
        </div>
    </div>

    @if ($rows->isEmpty())
        <div class="card p-6 text-center">
            <x-icon name="check" size="h-8 w-8" class="mx-auto text-success-600" />
            <p class="mt-2 text-sm font-semibold text-gray-900">
                {{ $tab === 'mine' ? 'Nothing assigned to you' : ($tab === 'outlet' ? 'Nothing else open at your outlet' : 'Nothing verified yet') }}
            </p>
        </div>
    @else
        @foreach ($rows as $action)
            @php $isMine = $action->owner_employee_id === $employee->id && ! $action->isVerified(); @endphp
            <div wire:key="sa-{{ $action->id }}" class="card p-3 space-y-2 {{ $action->isOverdue() ? 'border-l-4 border-l-danger-500' : '' }}">
                <div class="flex items-start justify-between gap-2">
                    <p class="min-w-0 flex-1 text-sm font-medium text-gray-900">{{ $action->description }}</p>
                    <span @class([
                        'shrink-0',
                        'badge-neutral' => $action->status === 'open',
                        'badge-info'    => $action->status === 'in_progress',
                        'badge-warning' => $action->status === 'done',
                        'badge-success' => $action->status === 'verified',
                    ])>{{ $action->statusLabel() }}</span>
                </div>

                <p class="text-xs text-gray-600">
                    {{ $action->finding?->item_label }}
                    <span class="block">
                        {{ $action->finding?->section_name }} · audit {{ $action->finding?->audit?->audit_date?->format('d M Y') }}
                        @if ($action->finding?->isMajor()) · <span class="font-medium text-danger-700">Major</span> @endif
                    </span>
                </p>

                @if ($action->finding?->description)
                    <p class="text-xs text-gray-700">“{{ $action->finding->description }}”</p>
                @endif

                <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-gray-600">
                    @if ($tab === 'outlet')
                        <span>{{ $action->owner?->name ?? 'No owner yet' }}{{ $action->owner?->designation ? ' · ' . $action->owner->designation : '' }}</span>
                    @endif
                    @if ($action->due_date)
                        <span class="{{ $action->isOverdue() ? 'font-semibold text-danger-700' : '' }}">Due {{ $action->due_date->format('d M Y') }}{{ $action->isOverdue() ? ' · overdue' : '' }}</span>
                    @endif
                    @if ($action->isVerified())
                        <span>Verified {{ $action->verified_at?->format('d M Y') }}</span>
                    @endif
                </div>

                @if ($action->evidence_path)
                    <a href="{{ $action->evidenceUrl() }}" target="_blank" class="block h-20 w-20 overflow-hidden rounded-control border border-gray-200">
                        <img src="{{ $action->evidenceUrl() }}" alt="Photo of the fix" class="h-full w-full object-cover" loading="lazy" />
                    </a>
                @endif

                @if ($isMine)
                    <input type="text" wire:model="notes.{{ $action->id }}" maxlength="500" placeholder="What was done (optional)"
                           class="w-full min-h-[2.75rem] text-sm rounded-control border-gray-300" />

                    <div class="grid grid-cols-2 gap-2">
                        @if ($action->status === 'open')
                            <button wire:click="setStatus({{ $action->id }}, 'in_progress')"
                                    class="min-h-[2.75rem] rounded-control border border-gray-300 text-sm font-medium text-gray-700 active:bg-gray-50">
                                Started
                            </button>
                        @else
                            <label class="flex min-h-[2.75rem] cursor-pointer items-center justify-center rounded-control border border-gray-300 text-sm font-medium text-gray-700 active:bg-gray-50">
                                <input type="file" accept="image/*" capture="environment" class="sr-only" wire:model="evidence.{{ $action->id }}" />
                                <span wire:loading.remove wire:target="evidence.{{ $action->id }}">{{ $action->evidence_path ? 'Replace photo' : 'Photo of fix' }}</span>
                                <span wire:loading wire:target="evidence.{{ $action->id }}">Uploading…</span>
                            </label>
                        @endif
                        @if ($action->status !== 'done')
                            <button wire:click="setStatus({{ $action->id }}, 'done')"
                                    class="min-h-[2.75rem] rounded-control bg-brand-600 text-white text-sm font-semibold active:bg-brand-700">
                                Mark done
                            </button>
                        @else
                            <span class="flex min-h-[2.75rem] items-center justify-center rounded-control bg-warning-50 text-xs font-medium text-warning-800">
                                Waiting for the auditor
                            </span>
                        @endif
                    </div>
                    @if ($action->status === 'open')
                        <label class="flex min-h-[2.75rem] cursor-pointer items-center justify-center rounded-control border border-dashed border-gray-300 text-sm font-medium text-gray-600 active:bg-gray-50">
                            <input type="file" accept="image/*" capture="environment" class="sr-only" wire:model="evidence.{{ $action->id }}" />
                            <span wire:loading.remove wire:target="evidence.{{ $action->id }}">{{ $action->evidence_path ? 'Replace photo' : '+ Photo of fix' }}</span>
                            <span wire:loading wire:target="evidence.{{ $action->id }}">Uploading…</span>
                        </label>
                    @endif
                    <x-input-error :messages="$errors->get('evidence.' . $action->id)" class="mt-1" />
                @endif
            </div>
        @endforeach
    @endif
</div>
