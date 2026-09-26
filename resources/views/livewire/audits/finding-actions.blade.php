<div>
    @if ($actions->isNotEmpty())
        <ul class="space-y-2">
            @foreach ($actions as $action)
                <li wire:key="ca-{{ $action->id }}" class="rounded-control border border-gray-200 bg-white p-3">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div class="min-w-0 flex-1">
                            <p class="text-sm text-gray-900">{{ $action->description }}</p>
                            <p class="mt-1 text-xs text-gray-600">
                                @if ($action->owner)
                                    <span class="font-medium text-gray-800">{{ $action->owner->name }}</span>{{ $action->owner->designation ? ' · ' . $action->owner->designation : '' }}
                                @else
                                    <span class="text-warning-700">No owner</span>
                                @endif
                                @if ($action->due_date)
                                    · due <span class="{{ $action->isOverdue() ? 'font-medium text-danger-700' : '' }}">{{ $action->due_date->format('d M Y') }}</span>
                                @endif
                                @if ($action->isVerified())
                                    · verified by {{ $action->verifiedBy?->name ?? '—' }} {{ $action->verified_at?->format('d M') }}
                                @elseif ($action->completed_at)
                                    · done {{ $action->completed_at->format('d M') }}
                                @endif
                            </p>
                            @if ($action->completion_note)
                                <p class="mt-1 text-xs text-gray-700">“{{ $action->completion_note }}”</p>
                            @endif
                            @if ($action->verification_note)
                                <p class="mt-1 text-xs text-gray-700">Verifier: “{{ $action->verification_note }}”</p>
                            @endif
                        </div>
                        <span @class([
                            'badge-neutral' => $action->status === 'open',
                            'badge-info'    => $action->status === 'in_progress',
                            'badge-warning' => $action->status === 'done',
                            'badge-success' => $action->status === 'verified',
                        ])>{{ $action->statusLabel() }}</span>
                    </div>

                    @php
                        $evidence     = $action->photos->where('kind', 'evidence');
                        $verification = $action->photos->where('kind', 'verification');
                    @endphp
                    @if ($evidence->isNotEmpty() || $verification->isNotEmpty())
                        <div class="mt-2 flex flex-wrap gap-4">
                            @foreach (['evidence' => ['Photos of the fix', $evidence], 'verification' => ['Verification photos', $verification]] as $kind => [$title, $set])
                                @if ($set->isNotEmpty())
                                    <div>
                                        <p class="mb-1 text-[11px] font-medium uppercase tracking-wider {{ $kind === 'verification' ? 'text-success-700' : 'text-gray-600' }}">{{ $title }}</p>
                                        <div class="flex flex-wrap gap-1.5">
                                            @foreach ($set as $photo)
                                                <div wire:key="cap-{{ $photo->id }}" class="relative">
                                                    <a href="{{ $photo->url() }}" target="_blank" class="block h-16 w-16 overflow-hidden rounded-control border {{ $kind === 'verification' ? 'border-success-300' : 'border-gray-200' }}">
                                                        <img src="{{ $photo->url() }}" alt="" class="h-full w-full object-cover" loading="lazy" />
                                                    </a>
                                                    @if ($canManage && ! $action->isVerified())
                                                        <button type="button" wire:click="removePhoto({{ $photo->id }})"
                                                                data-confirm-delete="Remove this photo from the corrective action. It is deleted from storage."
                                                                class="absolute -right-1.5 -top-1.5 flex h-5 w-5 items-center justify-center rounded-full bg-gray-900 text-white shadow" aria-label="Remove photo">
                                                            <x-icon name="close" size="h-3 w-3" />
                                                        </button>
                                                    @endif
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    @endif

                    @if ($canManage)
                        <div class="mt-2 flex flex-wrap items-center gap-2">
                            @if (! $action->isVerified())
                                <select class="input h-9 w-40 py-1 text-xs" aria-label="Owner"
                                        wire:change="setOwner({{ $action->id }}, $event.target.value)">
                                    <option value="">Owner…</option>
                                    @foreach ($employees as $e)
                                        <option value="{{ $e->id }}" @selected($action->owner_employee_id === $e->id)>{{ $e->name }}{{ $e->designation ? ' · ' . $e->designation : '' }}</option>
                                    @endforeach
                                </select>
                                <input type="date" class="input h-9 w-40 py-1 text-xs" value="{{ $action->due_date?->toDateString() }}"
                                       wire:change="setDueDate({{ $action->id }}, $event.target.value)" aria-label="Due date" />
                                <input type="text" wire:model="notes.{{ $action->id }}" class="input h-9 min-w-[10rem] flex-1 py-1 text-xs" placeholder="Note with the change (optional)" />
                                @if ($action->status !== 'in_progress')
                                    <button type="button" wire:click="setStatus({{ $action->id }}, 'in_progress')" class="btn-ghost text-xs">In progress</button>
                                @endif
                                @if ($action->status !== 'done')
                                    <button type="button" wire:click="setStatus({{ $action->id }}, 'done')" class="btn-secondary text-xs">Mark done</button>
                                @endif
                                <button type="button" wire:click="verify({{ $action->id }})" class="btn-primary text-xs">Verify</button>
                                @if ($evidence->count() < \App\Models\CorrectiveActionPhoto::MAX_PER_KIND)
                                    <label class="btn-ghost cursor-pointer text-xs">
                                        <input type="file" accept="image/*" capture="environment" class="sr-only" wire:model="evidence.{{ $action->id }}" />
                                        <span wire:loading.remove wire:target="evidence.{{ $action->id }}">+ Photo of fix</span>
                                        <span wire:loading wire:target="evidence.{{ $action->id }}">Uploading…</span>
                                    </label>
                                @endif
                                @if ($verification->count() < \App\Models\CorrectiveActionPhoto::MAX_PER_KIND)
                                    <label class="btn-ghost cursor-pointer text-xs text-success-700">
                                        <input type="file" accept="image/*" capture="environment" class="sr-only" wire:model="verification.{{ $action->id }}" />
                                        <span wire:loading.remove wire:target="verification.{{ $action->id }}">+ Verification photo</span>
                                        <span wire:loading wire:target="verification.{{ $action->id }}">Uploading…</span>
                                    </label>
                                @endif
                                @if ($action->status === 'open')
                                    <button type="button" wire:click="delete({{ $action->id }})" class="icon-btn icon-btn-danger" title="Remove"
                                            data-confirm-delete="Remove this corrective action. The finding stays open.">
                                        <x-icon name="trash" size="h-4 w-4" />
                                    </button>
                                @endif
                            @else
                                <button type="button" wire:click="unverify({{ $action->id }})" class="btn-ghost text-xs">Un-verify</button>
                                @if ($verification->count() < \App\Models\CorrectiveActionPhoto::MAX_PER_KIND)
                                    <label class="btn-ghost cursor-pointer text-xs text-success-700">
                                        <input type="file" accept="image/*" capture="environment" class="sr-only" wire:model="verification.{{ $action->id }}" />
                                        <span wire:loading.remove wire:target="verification.{{ $action->id }}">+ Verification photo</span>
                                        <span wire:loading wire:target="verification.{{ $action->id }}">Uploading…</span>
                                    </label>
                                @endif
                            @endif
                        </div>
                        @error('owner.' . $action->id) <p class="error-text">{{ $message }}</p> @enderror
                        @error('evidence.' . $action->id) <p class="error-text">{{ $message }}</p> @enderror
                        @error('verification.' . $action->id) <p class="error-text">{{ $message }}</p> @enderror
                    @endif
                </li>
            @endforeach
        </ul>
    @endif

    @if ($canManage)
        @if ($adding)
            <form wire:submit="add" class="mt-2 rounded-control border border-brand-200 bg-brand-50/40 p-3">
                <div class="grid gap-2 sm:grid-cols-[1fr_12rem_10rem]">
                    <div class="sm:col-span-3">
                        <input type="text" wire:model="description" class="input" placeholder="What will be done" autofocus />
                        @error('description') <p class="error-text">{{ $message }}</p> @enderror
                    </div>
                    <div class="sm:col-start-2">
                        <select wire:model="ownerId" class="input" aria-label="Owner">
                            <option value="">Owner (optional)</option>
                            @foreach ($employees as $e)
                                <option value="{{ $e->id }}">{{ $e->name }}{{ $e->designation ? ' · ' . $e->designation : '' }}</option>
                            @endforeach
                        </select>
                        @error('ownerId') <p class="error-text">{{ $message }}</p> @enderror
                    </div>
                    <input type="date" wire:model="dueDate" class="input" aria-label="Due date" />
                </div>
                <div class="mt-2 flex justify-end gap-2">
                    <button type="button" wire:click="cancelAdding" class="btn-ghost text-xs">Cancel</button>
                    <button type="submit" class="btn-primary text-xs">Add action</button>
                </div>
            </form>
        @else
            <button type="button" wire:click="startAdding" class="btn-ghost mt-1 text-xs">+ Corrective action</button>
        @endif
    @elseif ($actions->isEmpty())
        <p class="help">No corrective action raised yet.</p>
    @endif
</div>
