<div>
    <x-training.flash />

    <x-page-header title="Assignments" eyebrow="Learning & Development"
                   subtitle="What each outlet or person has to complete, and by when.">
        <x-slot:actions>
            @can('training.assign')
                <button type="button" wire:click="openCreate" class="btn-primary">+ New assignment</button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="card overflow-hidden">
        <div class="table-scroll">
            <table class="table-surface min-w-full">
                <thead>
                    <tr class="text-left">
                        <th class="px-4 py-3">What</th>
                        <th class="px-4 py-3">Who</th>
                        <th class="px-4 py-3">Due</th>
                        <th class="px-4 py-3">Set by</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($assignments as $assignment)
                        <tr wire:key="assignment-{{ $assignment->id }}">
                            <td class="px-4 py-3">
                                <p class="font-medium text-gray-900">{{ $assignment->targetName() }}</p>
                                <p class="text-xs text-gray-600">
                                    {{ $assignment->path ? 'Learning path' : 'Course' }}
                                    @if (! $assignment->is_mandatory)
                                        · <span class="badge-neutral">Optional</span>
                                    @endif
                                </p>
                            </td>
                            <td class="px-4 py-3 text-gray-700">
                                {{ $assignment->audienceName() }}
                                <span class="block text-xs text-gray-600">
                                    {{ $assignment->outlet_id ? 'Everyone at this outlet' : 'One person' }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                @if ($assignment->due_on)
                                    <span class="{{ $assignment->due_on->isPast() ? 'text-danger-700 font-medium' : 'text-gray-700' }}">
                                        {{ $assignment->due_on->format('j M Y') }}
                                    </span>
                                    @if ($assignment->due_on->isPast())
                                        <span class="block text-xs text-danger-700">Overdue</span>
                                    @endif
                                @else
                                    <span class="text-gray-600">No date</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-gray-700">{{ $assignment->assignedBy?->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-right">
                                @can('training.assign')
                                    <button wire:click="delete({{ $assignment->id }})"
                                            data-confirm-delete="Remove this assignment?"
                                            class="text-xs text-gray-600 hover:text-danger-500">Remove</button>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">
                                <div class="empty-state">
                                    <x-icon name="calendar-days" size="h-8 w-8" class="text-gray-500" />
                                    <p class="empty-title">Nothing is assigned yet</p>
                                    <p class="empty-body">
                                        Assign to an OUTLET rather than to named people where you can — anyone
                                        who joins that branch later picks the requirement up automatically.
                                    </p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">{{ $assignments->links() }}</div>

    {{-- ── New assignment ── --}}
    @if ($showModal)
        <div class="fixed inset-0 z-overlay flex items-center justify-center bg-gray-900/50 p-4"
             wire:key="assignment-modal">
            <div class="card w-full max-w-lg p-6">
                <h2 class="text-base font-semibold text-gray-900 mb-4">New assignment</h2>

                <div class="space-y-4">
                    <div>
                        <p class="label">What</p>
                        <div class="seg mb-2">
                            <button type="button" wire:click="$set('targetType', 'course')"
                                    class="seg-item {{ $targetType === 'course' ? 'seg-item-on' : '' }}">A course</button>
                            <button type="button" wire:click="$set('targetType', 'path')"
                                    class="seg-item {{ $targetType === 'path' ? 'seg-item-on' : '' }}">A learning path</button>
                        </div>

                        @if ($targetType === 'course')
                            <select wire:model="courseId" class="input" aria-label="Course">
                                <option value="">Choose a course…</option>
                                @foreach ($courses as $course)
                                    <option value="{{ $course->id }}">{{ $course->title }}</option>
                                @endforeach
                            </select>
                            @error('courseId') <p class="error-text">{{ $message }}</p> @enderror
                        @else
                            <select wire:model="pathId" class="input" aria-label="Learning path">
                                <option value="">Choose a path…</option>
                                @foreach ($paths as $path)
                                    <option value="{{ $path->id }}">{{ $path->name }}</option>
                                @endforeach
                            </select>
                            @error('pathId') <p class="error-text">{{ $message }}</p> @enderror
                        @endif
                    </div>

                    <div>
                        <p class="label">Who</p>
                        <div class="seg mb-2">
                            <button type="button" wire:click="$set('audienceType', 'outlet')"
                                    class="seg-item {{ $audienceType === 'outlet' ? 'seg-item-on' : '' }}">An outlet</button>
                            <button type="button" wire:click="$set('audienceType', 'trainee')"
                                    class="seg-item {{ $audienceType === 'trainee' ? 'seg-item-on' : '' }}">One person</button>
                        </div>

                        @if ($audienceType === 'outlet')
                            <select wire:model="outletId" class="input" aria-label="Outlet">
                                <option value="">Choose an outlet…</option>
                                @foreach ($outlets as $outlet)
                                    <option value="{{ $outlet->id }}">{{ $outlet->name }}</option>
                                @endforeach
                            </select>
                            @error('outletId') <p class="error-text">{{ $message }}</p> @enderror
                            <p class="help">New starters at this branch inherit it automatically.</p>
                        @else
                            <select wire:model="traineeId" class="input" aria-label="Trainee">
                                <option value="">Choose a person…</option>
                                @foreach ($trainees as $trainee)
                                    <option value="{{ $trainee->id }}">{{ $trainee->name }} — {{ $trainee->email }}</option>
                                @endforeach
                            </select>
                            @error('traineeId') <p class="error-text">{{ $message }}</p> @enderror
                        @endif
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="label" for="assign-due">Due date</label>
                            <input id="assign-due" type="date" wire:model="dueOn" class="input">
                            @error('dueOn') <p class="error-text">{{ $message }}</p> @enderror
                        </div>
                        <label class="flex items-center gap-2 pb-2 text-sm text-gray-800 self-end">
                            <input type="checkbox" wire:model="isMandatory" class="rounded-control border-gray-300">
                            Mandatory
                        </label>
                    </div>

                    <div>
                        <label class="label" for="assign-note">Note (optional)</label>
                        <textarea id="assign-note" wire:model="note" rows="2" class="input"
                                  placeholder="Why this, and by when."></textarea>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 mt-5">
                    <button type="button" wire:click="$set('showModal', false)" class="btn-ghost">Cancel</button>
                    <button type="button" wire:click="save" class="btn-primary">Assign</button>
                </div>
            </div>
        </div>
    @endif
</div>
