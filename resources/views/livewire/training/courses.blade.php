<div>
    <x-training.flash />

    <x-page-header title="Courses" eyebrow="Learning & Development"
                   subtitle="The material your team learns from — SOPs, policies, and anything you write here.">
        <x-slot:actions>
            @can('training.manage')
                <a href="{{ route('training.courses.create') }}" class="btn-primary">+ New course</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
        <div class="card p-4"><div class="stat"><span class="stat-label">Courses</span><span class="stat-value">{{ $stats['total'] }}</span></div></div>
        <div class="card p-4"><div class="stat"><span class="stat-label">Published</span><span class="stat-value">{{ $stats['published'] }}</span></div></div>
        <div class="card p-4"><div class="stat"><span class="stat-label">Compliance</span><span class="stat-value">{{ $stats['compliance'] }}</span></div></div>
        {{-- The number worth acting on: material nobody can be tested on. --}}
        <div class="card p-4">
            <div class="stat">
                <span class="stat-label">Without a quiz</span>
                <span class="stat-value {{ $stats['no_quiz'] > 0 ? 'text-warning-700' : '' }}">{{ $stats['no_quiz'] }}</span>
            </div>
        </div>
    </div>

    <div class="toolbar mb-4">
        <div class="flex flex-wrap items-end gap-3">
            <div class="flex-1 min-w-[200px]">
                <label class="label" for="course-search">Search</label>
                <input id="course-search" type="search" wire:model.live.debounce.300ms="search"
                       class="input" placeholder="Course title or summary">
            </div>
            <div>
                <label class="label" for="course-status">Status</label>
                <select id="course-status" wire:model.live="statusFilter" class="input">
                    <option value="">All</option>
                    @foreach (\App\Models\TrainingCourse::STATUSES as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="label" for="course-category">Category</label>
                <select id="course-category" wire:model.live="categoryFilter" class="input">
                    <option value="">All</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category }}">{{ $category }}</option>
                    @endforeach
                </select>
            </div>
            <label class="flex items-center gap-2 pb-2 text-sm text-gray-700">
                <input type="checkbox" wire:model.live="complianceOnly" class="rounded-control border-gray-300">
                Compliance only
            </label>
        </div>
    </div>

    <div class="card overflow-hidden">
        <div class="table-scroll">
            <table class="table-surface min-w-full">
                <thead>
                    <tr class="text-left">
                        <th class="px-4 py-3">Course</th>
                        <th class="px-4 py-3">Source</th>
                        <th class="px-4 py-3">Quiz</th>
                        <th class="px-4 py-3">Outlets</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($courses as $course)
                        <tr wire:key="course-{{ $course->id }}" class="hover:bg-gray-50">
                            <td class="px-4 py-3">
                                <p class="font-medium text-gray-900">{{ $course->title }}</p>
                                <p class="text-xs text-gray-600">
                                    {{ $course->category ?: 'Uncategorised' }}
                                    · {{ $course->estimated_minutes }} min
                                    @if ($course->is_compliance)
                                        <span class="badge-warning ml-1">Compliance</span>
                                    @endif
                                </p>
                            </td>
                            <td class="px-4 py-3 text-gray-700">
                                {{ $course->sourceLabel() }}
                                @if ($course->sourceRecipe)
                                    <span class="block text-xs text-gray-600">{{ $course->sourceRecipe->name }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                @if ($course->quizzes_count > 0)
                                    <span class="text-gray-700">{{ $course->quizzes_count }}</span>
                                @else
                                    <span class="text-warning-600">None yet</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-gray-700">
                                {{ $course->outlets_count > 0 ? $course->outlets_count . ' outlet' . ($course->outlets_count === 1 ? '' : 's') : 'All outlets' }}
                            </td>
                            <td class="px-4 py-3">
                                <span class="{{ $course->status === 'published' ? 'badge-success' : 'badge-neutral' }}">
                                    {{ \App\Models\TrainingCourse::STATUSES[$course->status] ?? $course->status }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-2 text-xs">
                                    @can('training.manage')
                                        <a href="{{ route('training.courses.edit', $course->id) }}"
                                           class="text-brand-600 hover:underline">Edit</a>
                                        @if ($course->status === 'published')
                                            <button wire:click="unpublish({{ $course->id }})"
                                                    class="text-gray-600 hover:text-gray-900">Unpublish</button>
                                        @else
                                            <button wire:click="publish({{ $course->id }})"
                                                    class="text-gray-600 hover:text-gray-900">Publish</button>
                                        @endif
                                        <button wire:click="delete({{ $course->id }})"
                                                data-confirm-delete="Delete &quot;{{ $course->title }}&quot;? Its quizzes go with it."
                                                class="text-gray-600 hover:text-danger-500">Delete</button>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">
                                <div class="empty-state">
                                    <x-icon name="academic" size="h-8 w-8" class="text-gray-500" />
                                    <p class="empty-title">No courses yet</p>
                                    <p class="empty-body">
                                        Start from an SOP you already have — the recipe method becomes the material,
                                        and the AI writes the quiz.
                                    </p>
                                    @can('training.manage')
                                        <a href="{{ route('training.courses.create') }}" class="btn-primary mt-3">Create the first course</a>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">{{ $courses->links() }}</div>
</div>
