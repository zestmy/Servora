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
                                    {{ $assignment->targetKind() }}
                                    @if (! $assignment->is_mandatory)
                                        · <span class="badge-neutral">Optional</span>
                                    @endif
                                </p>
                            </td>
                            <td class="px-4 py-3 text-gray-700">
                                {{ $assignment->audienceName() }}
                                <span class="block text-xs text-gray-600">
                                    @if ($assignment->employee_id)
                                        One person
                                    @elseif ($assignment->section_id)
                                        That section only
                                    @elseif ($assignment->outlet_id)
                                        Everyone at this outlet
                                    @else
                                        Everyone
                                    @endif
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
        {{-- TELEPORTED TO BODY, and that is the actual fix rather than a
             refinement. `position: fixed` is relative to the viewport UNLESS an
             ancestor has a transform, in which case that ancestor becomes the
             containing block and clips it. layouts.app wraps every page in
             `.page-enter`, whose animation carries `both` as its fill mode — so
             the translateY it ends on PERSISTS, and every fixed child of a
             Livewire page is quietly trapped inside the content column. That is
             why the panel was cut off at the bottom of the table area rather
             than at the bottom of the window. The layout already solves this
             once for the user menu, with the same escape hatch.

             The overlay also scrolls and the card is height-capped: a centred
             panel taller than the window overflows in both directions and puts
             its own heading out of reach. --}}
        @teleport('body')
        <div class="fixed inset-0 z-overlay overflow-y-auto bg-gray-900/50 p-4
                    flex items-start justify-center sm:items-center"
             wire:key="assignment-modal">
            <div class="card my-auto w-full max-w-lg p-6">
                <h2 class="text-base font-semibold text-gray-900 mb-4">New assignment</h2>

                <div class="space-y-4">
                    <div>
                        <p class="label">What</p>
                        <div class="seg mb-2">
                            <button type="button" wire:click="$set('targetType', 'course')"
                                    class="seg-item {{ $targetType === 'course' ? 'seg-item-on' : '' }}">A course</button>
                            <button type="button" wire:click="$set('targetType', 'quiz')"
                                    class="seg-item {{ $targetType === 'quiz' ? 'seg-item-on' : '' }}">One quiz</button>
                            <button type="button" wire:click="$set('targetType', 'path')"
                                    class="seg-item {{ $targetType === 'path' ? 'seg-item-on' : '' }}">A learning path</button>
                        </div>

                        @if ($targetType === 'quiz')
                            <select wire:model="quizId" class="input" aria-label="Quiz">
                                <option value="">Choose a quiz…</option>
                                @foreach ($quizzes as $q)
                                    <option value="{{ $q->id }}">
                                        {{ $q->title }}{{ ($q->language ?? 'en') !== 'en' ? ' — ' . $q->languageLabel() : '' }}
                                    </option>
                                @endforeach
                            </select>
                            @error('quizId') <p class="error-text">{{ $message }}</p> @enderror
                            <p class="help">
                                Name the paper rather than the course when a course carries more than
                                one — a kitchen set beside a floor set, or English beside Malay.
                            </p>
                        @elseif ($targetType === 'course')
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
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                <div>
                                    <label class="sr-only" for="assign-outlet">Outlet</label>
                                    <select id="assign-outlet" wire:model="outletId" class="input">
                                        <option value="">Choose an outlet…</option>
                                        @foreach ($outlets as $outlet)
                                            <option value="{{ $outlet->id }}">{{ $outlet->name }}</option>
                                        @endforeach
                                    </select>
                                    @error('outletId') <p class="error-text">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label class="sr-only" for="assign-section">Section</label>
                                    {{-- Narrows the outlet, it does not replace it: "the kitchen at
                                         Bangsar" needs both, and it is the commonest ask. --}}
                                    <select id="assign-section" wire:model="sectionId" class="input">
                                        <option value="">Every section</option>
                                        @foreach ($sections as $section)
                                            <option value="{{ $section->id }}">{{ $section->name }} only</option>
                                        @endforeach
                                    </select>
                                    @error('sectionId') <p class="error-text">{{ $message }}</p> @enderror
                                </div>
                            </div>
                            <p class="help">
                                New starters at this branch inherit it automatically — and if you pick a
                                section, only the people posted to it.
                            </p>
                        @else
                            <select wire:model="traineeId" class="input" aria-label="Staff member">
                                <option value="">Choose a person…</option>
                                @foreach ($trainees as $trainee)
                                    <option value="{{ $trainee->id }}">{{ $trainee->name }}{{ $trainee->staff_id ? ' — ' . $trainee->staff_id : '' }}</option>
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
        @endteleport
    @endif
</div>
