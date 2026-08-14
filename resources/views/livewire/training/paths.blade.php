<div>
    <x-training.flash />

    <x-page-header title="Learning paths" eyebrow="Learning & Development"
                   subtitle="An ordered run of courses — the induction, the barista ladder. The order is the point.">
        <x-slot:actions>
            @can('training.manage')
                <button type="button" wire:click="openCreate" class="btn-primary">+ New path</button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        {{-- ── Paths ── --}}
        <div class="card overflow-hidden">
            <div class="divide-y divide-gray-100">
                @forelse ($paths as $row)
                    <div wire:key="path-{{ $row->id }}"
                         class="px-4 py-3 {{ $editingPathId === $row->id ? 'bg-brand-50/60' : 'hover:bg-gray-50' }}">
                        <div class="flex items-start justify-between gap-2">
                            <button type="button" wire:click="selectPath({{ $row->id }})" class="text-left flex-1 min-w-0">
                                <p class="font-medium text-gray-900 truncate">{{ $row->name }}</p>
                                <p class="text-xs text-gray-600">
                                    {{ $row->items_count }} {{ Str::plural('course', $row->items_count) }}
                                    · {{ \App\Models\TrainingPath::STATUSES[$row->status] ?? $row->status }}
                                </p>
                            </button>
                            @can('training.manage')
                                <div class="flex flex-col items-end gap-1 text-xs">
                                    <button wire:click="openEdit({{ $row->id }})" class="text-brand-600 hover:underline">Edit</button>
                                    <button wire:click="deletePath({{ $row->id }})"
                                            data-confirm-delete="Delete &quot;{{ $row->name }}&quot;?"
                                            class="text-gray-600 hover:text-danger-500">Delete</button>
                                </div>
                            @endcan
                        </div>
                    </div>
                @empty
                    <div class="empty-state border-0 bg-transparent">
                        <p class="empty-title">No paths yet</p>
                        <p class="empty-body">A path is how a new starter knows what to do first.</p>
                    </div>
                @endforelse
            </div>
        </div>

        {{-- ── Contents ── --}}
        <div class="lg:col-span-2">
            @if (! $path)
                <div class="empty-state h-full">
                    <x-icon name="academic" size="h-8 w-8" class="text-gray-500" />
                    <p class="empty-title">Pick a path</p>
                    <p class="empty-body">Then add courses to it, in the order they should be done.</p>
                </div>
            @else
                <div class="card p-5">
                    <div class="flex flex-wrap items-start justify-between gap-3 mb-4">
                        <div>
                            <h2 class="text-base font-semibold text-gray-900">{{ $path->name }}</h2>
                            <p class="text-sm text-gray-600">
                                {{ $path->description ?: 'No description' }}
                            </p>
                            <p class="help mt-1">
                                {{ $path->unlocks_sequentially
                                    ? 'Each course unlocks when the one before it is passed.'
                                    : 'Courses can be done in any order.' }}
                                @if ($path->target_days) Target: {{ $path->target_days }} days. @endif
                            </p>
                        </div>
                    </div>

                    @can('training.manage')
                        <div class="flex flex-wrap items-end gap-2 mb-4">
                            <div class="flex-1 min-w-[200px]">
                                <label class="label" for="path-add">Add a course</label>
                                <select id="path-add" wire:model="addCourseId" class="input">
                                    <option value="">Choose a published course…</option>
                                    @foreach ($courses as $course)
                                        <option value="{{ $course->id }}">{{ $course->title }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <button type="button" wire:click="addCourse" class="btn-secondary">Add</button>
                        </div>
                    @endcan

                    @if ($path->items->isEmpty())
                        <div class="empty-state">
                            <p class="empty-title">Nothing in this path yet</p>
                            <p class="empty-body">Add the course a new starter should do first.</p>
                        </div>
                    @else
                        <ol class="space-y-2">
                            @foreach ($path->items as $i => $item)
                                <li wire:key="item-{{ $item->id }}"
                                    class="list-row flex items-center justify-between gap-3">
                                    <span class="flex items-center gap-3 min-w-0">
                                        <span class="w-6 text-right tabular-nums text-sm font-semibold text-gray-600">{{ $i + 1 }}</span>
                                        <span class="min-w-0">
                                            <span class="block truncate font-medium text-gray-900">
                                                {{ $item->course?->title ?? 'Removed course' }}
                                            </span>
                                            <span class="block text-xs text-gray-600">
                                                {{ $item->course?->estimated_minutes }} min
                                                @if (! $item->is_required) · <span class="badge-neutral">Optional</span> @endif
                                                @if ($item->course && $item->course->status !== 'published')
                                                    · <span class="badge-warning">Not published</span>
                                                @endif
                                            </span>
                                        </span>
                                    </span>

                                    @can('training.manage')
                                        <span class="flex items-center gap-1 flex-shrink-0">
                                            <button wire:click="move({{ $item->id }}, 'up')" class="icon-btn"
                                                    aria-label="Move up" @disabled($i === 0)>
                                                <x-icon name="chevron-down" size="h-4 w-4" class="rotate-180" />
                                            </button>
                                            <button wire:click="move({{ $item->id }}, 'down')" class="icon-btn"
                                                    aria-label="Move down" @disabled($i === $path->items->count() - 1)>
                                                <x-icon name="chevron-down" size="h-4 w-4" />
                                            </button>
                                            <button wire:click="toggleRequired({{ $item->id }})"
                                                    class="text-xs text-gray-600 hover:text-gray-900 px-2">
                                                {{ $item->is_required ? 'Make optional' : 'Make required' }}
                                            </button>
                                            {{-- Gated like Print Sets' removeLine: it deletes a
                                                 saved row, even though the course itself and
                                                 everyone's results are untouched. The message
                                                 says so, because "remove" next to a course
                                                 title reads like it removes the course. --}}
                                            <button wire:click="removeItem({{ $item->id }})"
                                                    data-confirm-delete="Remove this course from the path? The course itself and everyone's results stay."
                                                    class="icon-btn text-gray-600 hover:text-danger-500"
                                                    aria-label="Remove from path">
                                                <x-icon name="trash" size="h-4 w-4" />
                                            </button>
                                        </span>
                                    @endcan
                                </li>
                            @endforeach
                        </ol>
                    @endif
                </div>
            @endif
        </div>
    </div>

    {{-- ── Path settings ── --}}
    @if ($showModal)
        <div class="fixed inset-0 z-overlay flex items-center justify-center bg-gray-900/50 p-4" wire:key="path-modal">
            <div class="card w-full max-w-md p-6">
                <h2 class="text-base font-semibold text-gray-900 mb-4">
                    {{ $modalPathId ? 'Edit path' : 'New path' }}
                </h2>

                <div class="space-y-3">
                    <div>
                        <label class="label" for="path-name">Name</label>
                        <input id="path-name" type="text" wire:model="name" class="input"
                               placeholder="Front of house induction">
                        @error('name') <p class="error-text">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="label" for="path-description">Description</label>
                        <textarea id="path-description" wire:model="description" rows="2" class="input"></textarea>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="label" for="path-status">Status</label>
                            <select id="path-status" wire:model="status" class="input">
                                @foreach (\App\Models\TrainingPath::STATUSES as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="label" for="path-days">Target days</label>
                            <input id="path-days" type="number" min="1" max="365" wire:model="targetDays" class="input">
                            @error('targetDays') <p class="error-text">{{ $message }}</p> @enderror
                        </div>
                    </div>
                    <label class="flex items-start gap-2 text-sm text-gray-800">
                        <input type="checkbox" wire:model="unlocksSequentially" class="mt-0.5 rounded-control border-gray-300">
                        <span>Unlock in order
                            <span class="block help">
                                Off for a refresher set, on for an induction where the order actually matters.
                            </span>
                        </span>
                    </label>
                </div>

                <div class="flex items-center justify-end gap-2 mt-5">
                    <button type="button" wire:click="$set('showModal', false)" class="btn-ghost">Cancel</button>
                    <button type="button" wire:click="savePath" class="btn-primary">Save</button>
                </div>
            </div>
        </div>
    @endif
</div>
