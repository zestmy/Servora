<div class="p-4 space-y-4">

    <div>
        <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">Learning</p>
        <h1 class="text-2xl font-bold text-gray-900 mt-0.5">
            Hi {{ Str::before($employee->name, ' ') }}
        </h1>
        <p class="text-sm text-gray-600 mt-0.5">Learn it, prove it, climb the board.</p>
    </div>

    @if (session()->has('error'))
        <div class="alert-danger">
            <x-icon name="alert" size="h-5 w-5" class="flex-shrink-0" />
            <p>{{ session('error') }}</p>
        </div>
    @endif

    {{-- ── Open challenges ──
         Above everything, including what you owe: a challenge has a closing
         date and a leaderboard your colleagues are already on, which is the
         only thing here with a reason to be done TODAY rather than this week. --}}
    @foreach ($challenges as $challenge)
        <div wire:key="challenge-{{ $challenge->id }}"
             class="card overflow-hidden border-l-4 border-l-brand-500">
            <div class="bg-brand-600 px-4 py-2 text-white">
                <div class="flex items-center justify-between gap-3">
                    <span class="flex items-center gap-2 min-w-0">
                        <x-icon name="trophy" size="h-4 w-4" class="shrink-0 text-brand-100" />
                        <span class="truncate text-xs font-semibold uppercase tracking-wider">Challenge</span>
                    </span>
                    <span class="shrink-0 text-xs text-brand-100">{{ $challenge->windowLabel() }}</span>
                </div>
            </div>
            <div class="p-4">
                <p class="font-semibold text-gray-900">
                    {{ $challenge->name ?: $challenge->quiz->title }}
                </p>
                <p class="help">
                    {{ $challenge->quiz->title }} · pass at {{ $challenge->quiz->pass_mark }}%
                    · one attempt only
                </p>
                <a href="{{ route('clock.staff.learn.quiz', [$challenge->training_quiz_id, $challenge->id]) }}"
                   wire:navigate class="btn-primary mt-3 w-full justify-center">
                    Take the challenge
                </a>
            </div>
        </div>
    @endforeach

    {{-- What you owe, before the catalogue. Somebody on a break has three
         minutes and the honest answer to "what now" is the thing with a date. --}}
    @if ($outstanding->isNotEmpty())
        <div class="card border-l-4 border-l-warning-400 p-4">
            <h2 class="text-sm font-semibold text-gray-900">To do</h2>
            <p class="help mb-2">
                {{ $outstanding->count() }} {{ Str::plural('thing', $outstanding->count()) }} assigned to you.
            </p>
            <ul class="space-y-2">
                @foreach ($outstanding as $item)
                    <li class="flex flex-wrap items-center justify-between gap-2 text-sm">
                        <span class="font-medium text-gray-900">{{ $item['title'] }}</span>
                        <span class="text-xs {{ $item['overdue'] ? 'font-semibold text-danger-700' : 'text-gray-600' }}">
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

    <div>
        <label class="sr-only" for="staff-course-search">Search training</label>
        <input id="staff-course-search" type="search" wire:model.live.debounce.300ms="search"
               class="input" placeholder="Search training">
    </div>

    {{-- ── Quizzes with no course to sit inside ──

         A quiz used to be reachable only through the course it hangs off, so a
         published quiz on a course still in draft was invisible to the whole
         floor. The course is the reading; the quiz is the thing somebody has
         three minutes for between covers. Both belong on this screen. --}}
    @if ($quizzes->isNotEmpty())
        <div class="space-y-3">
            @foreach ($quizzes as $quiz)
                @php $mine = $best[$quiz->id] ?? null; @endphp
                <a wire:key="quiz-{{ $quiz->id }}"
                   href="{{ route('clock.staff.learn.quiz', $quiz->id) }}" wire:navigate
                   class="card block p-4 active:bg-gray-50 border-l-4 border-l-brand-400">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <p class="font-semibold text-gray-900">{{ $quiz->title }}</p>
                            <p class="mt-0.5 text-xs text-gray-600">
                                {{ $quiz->questions_count }} {{ Str::plural('question', $quiz->questions_count) }}
                                · {{ $quiz->default_seconds }}s each
                                · pass {{ $quiz->pass_mark }}%
                                @if ($quiz->isMultilingual())
                                    · <span class="badge-neutral">{{ count($quiz->availableLanguages()) }} languages</span>
                                @endif
                            </p>
                        </div>
                        @if ($mine)
                            <span class="{{ $mine->passed ? 'badge-success' : 'badge-warning' }} shrink-0">
                                {{ (float) $mine->percent }}%
                            </span>
                        @endif
                    </div>
                </a>
            @endforeach
        </div>
    @endif

    <div class="space-y-3">
        @forelse ($courses as $course)
            @php
                $quiz    = $course->quizzes->first();
                $attempt = $quiz ? ($best[$quiz->id] ?? null) : null;
            @endphp
            <a wire:key="course-{{ $course->id }}"
               href="{{ route('clock.staff.learn.course', $course->id) }}" wire:navigate
               class="card block overflow-hidden active:bg-gray-50">
                @if ($course->cover_path)
                    <img src="{{ Storage::disk('public')->url($course->cover_path) }}" alt=""
                         class="h-28 w-full object-cover">
                @endif
                <div class="p-4">
                    <div class="flex items-start justify-between gap-2">
                        <p class="font-semibold text-gray-900">{{ $course->title }}</p>
                        @if ($attempt)
                            <span class="{{ $attempt->passed ? 'badge-success' : 'badge-warning' }} shrink-0">
                                {{ (float) $attempt->percent }}%
                            </span>
                        @endif
                    </div>

                    @if ($course->summary)
                        <p class="mt-1 text-sm text-gray-600 line-clamp-2">{{ $course->summary }}</p>
                    @endif

                    <p class="mt-2 text-xs text-gray-600">
                        {{ $course->estimated_minutes }} min read
                        @if ($course->category) · {{ $course->category }} @endif
                        @if ($quiz) · quiz @endif
                        @if ($course->is_compliance)
                            · <span class="badge-warning">Compliance</span>
                        @endif
                    </p>
                </div>
            </a>
        @empty
            {{-- Only when there is genuinely nothing. A quiz with no course
                 still counts as training on this screen, and telling somebody
                 "nothing published yet" above a quiz they can take is the kind
                 of small wrongness that makes people stop trusting a page. --}}
            @if ($quizzes->isEmpty())
                <div class="empty-state">
                    <x-icon name="academic" size="h-8 w-8" class="text-gray-500" />
                    <p class="empty-title">Nothing published yet</p>
                    <p class="empty-body">
                        When your manager publishes training for your branch it appears here.
                    </p>
                </div>
            @endif
        @endforelse
    </div>

    <div class="grid grid-cols-2 gap-2">
        <a href="{{ route('clock.staff.learn.progress') }}" wire:navigate
           class="btn-secondary justify-center">
            My progress
        </a>
        <a href="{{ route('clock.staff.learn.certificates') }}" wire:navigate
           class="btn-secondary justify-center">
            My certificates
        </a>
    </div>
</div>
