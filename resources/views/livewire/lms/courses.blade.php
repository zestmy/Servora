{{--
    The trainee's Training home.

    Outstanding work comes FIRST. Somebody opening this on a break has three
    minutes, and the honest answer to "what should I do now" is the thing with a
    due date on it — not the newest course.
--}}
<div>
    <div class="mb-6">
        <p class="text-xs font-semibold uppercase tracking-wider text-gray-600">Training</p>
        <h1 class="text-2xl font-bold text-gray-900 mt-1">Hi {{ Str::before($trainee->name, ' ') }}</h1>
        <p class="text-sm text-gray-600 mt-1">
            Learn it, prove it, climb the board.
        </p>
    </div>

    <div class="flex flex-wrap gap-2 mb-6">
        <a href="{{ route('lms.live') }}" class="btn-primary">
            <x-icon name="play" size="h-4 w-4" class="mr-1" /> Join a live session
        </a>
        <a href="{{ route('lms.progress') }}" class="btn-secondary">
            <x-icon name="trophy" size="h-4 w-4" class="mr-1" /> My progress
        </a>
    </div>

    {{-- ── What you owe ── --}}
    @if ($outstanding->isNotEmpty())
        <div class="card p-5 mb-6 border-l-4 border-l-warning-400">
            <h2 class="text-sm font-semibold text-gray-900 mb-1">To do</h2>
            <p class="help mb-3">
                {{ $outstanding->count() }} {{ Str::plural('thing', $outstanding->count()) }}
                assigned to you.
            </p>
            <ul class="space-y-2">
                @foreach ($outstanding as $item)
                    <li class="flex flex-wrap items-center justify-between gap-2 text-sm">
                        <span class="font-medium text-gray-900">{{ $item['title'] }}</span>
                        <span class="text-xs {{ $item['overdue'] ? 'text-danger-700 font-semibold' : 'text-gray-600' }}">
                            @if ($item['due_on'])
                                {{ $item['overdue'] ? 'Overdue — was due' : 'Due' }} {{ $item['due_on']->format('j M') }}
                            @else
                                No date set
                            @endif
                        </span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- ── Learning paths ── --}}
    @if ($paths->isNotEmpty())
        <div class="mb-6">
            <h2 class="text-sm font-semibold text-gray-900 mb-2">Your learning paths</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                @foreach ($paths as $path)
                    <div wire:key="path-{{ $path->id }}" class="card p-4">
                        <p class="font-medium text-gray-900">{{ $path->name }}</p>
                        <p class="help">{{ $path->items->count() }} {{ Str::plural('course', $path->items->count()) }}</p>
                        <ol class="mt-2 space-y-1 text-sm text-gray-700">
                            @foreach ($path->items as $i => $item)
                                <li class="flex items-center gap-2">
                                    <span class="w-4 text-right tabular-nums text-xs text-gray-600">{{ $i + 1 }}</span>
                                    <span class="truncate">{{ $item->course?->title ?? '—' }}</span>
                                </li>
                            @endforeach
                        </ol>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- ── Catalogue ── --}}
    <div class="flex flex-wrap items-end gap-3 mb-4">
        <div class="flex-1 min-w-[180px]">
            <label class="sr-only" for="lms-course-search">Search courses</label>
            <input id="lms-course-search" type="search" wire:model.live.debounce.300ms="search"
                   class="input" placeholder="Search courses">
        </div>
        @if ($categories->isNotEmpty())
            <div>
                <label class="sr-only" for="lms-course-cat">Category</label>
                <select id="lms-course-cat" wire:model.live="categoryFilter" class="input">
                    <option value="">All categories</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category }}">{{ $category }}</option>
                    @endforeach
                </select>
            </div>
        @endif
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        @forelse ($courses as $course)
            @php
                $quiz    = $course->quizzes->first();
                $attempt = $quiz ? ($best[$quiz->id] ?? null) : null;
            @endphp
            <a wire:key="course-{{ $course->id }}" href="{{ route('lms.courses.show', $course->id) }}"
               class="card overflow-hidden transition hover:shadow-e2 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500">
                @if ($course->cover_path)
                    <img src="{{ Storage::disk('public')->url($course->cover_path) }}" alt=""
                         class="h-32 w-full object-cover">
                @endif
                <div class="p-4">
                    <div class="flex items-start justify-between gap-2">
                        <p class="font-semibold text-gray-900">{{ $course->title }}</p>
                        @if ($attempt)
                            <span class="{{ $attempt->passed ? 'badge-success' : 'badge-warning' }} flex-shrink-0">
                                {{ (float) $attempt->percent }}%
                            </span>
                        @endif
                    </div>

                    @if ($course->summary)
                        <p class="mt-1 text-sm text-gray-600 line-clamp-2">{{ $course->summary }}</p>
                    @endif

                    <p class="mt-3 text-xs text-gray-600">
                        {{ $course->estimated_minutes }} min read
                        @if ($course->category) · {{ $course->category }} @endif
                        @if ($quiz) · {{ $quiz->title }} @endif
                    </p>

                    @if ($course->is_compliance)
                        <span class="badge-warning mt-2 inline-block">Compliance</span>
                    @endif
                </div>
            </a>
        @empty
            <div class="sm:col-span-2 lg:col-span-3">
                <div class="empty-state">
                    <x-icon name="academic" size="h-8 w-8" class="text-gray-500" />
                    <p class="empty-title">No courses yet</p>
                    <p class="empty-body">
                        Your manager has not published any training for your outlet yet.
                        The SOPs are still in the menu on the left.
                    </p>
                </div>
            </div>
        @endforelse
    </div>
</div>
