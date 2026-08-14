<div class="p-4">
    <a href="{{ route('clock.staff.learn') }}" class="inline-flex items-center gap-1 text-sm text-gray-600 hover:text-gray-900 mb-4">
        <x-icon name="chevron-left" size="h-4 w-4" /> All training
    </a>

    <div class="mb-5">
        <p class="text-xs font-semibold uppercase tracking-wider text-gray-600">
            {{ $course->category ?: 'Training' }}
        </p>
        <h1 class="text-2xl font-bold text-gray-900 mt-1">{{ $course->title }}</h1>
        @if ($course->summary)
            <p class="text-gray-600 mt-2">{{ $course->summary }}</p>
        @endif
        <p class="text-xs text-gray-600 mt-2">
            About {{ $course->estimated_minutes }} minutes
            @if ($course->is_compliance) · <span class="badge-warning">Compliance</span> @endif
        </p>
    </div>

    @if ($video)
        <div class="card overflow-hidden mb-5">
            <div class="relative w-full" style="padding-top: 56.25%">
                <iframe class="absolute inset-0 h-full w-full"
                        src="{{ $video['type'] === 'youtube'
                            ? 'https://www.youtube.com/embed/' . $video['id']
                            : 'https://player.vimeo.com/video/' . $video['id'] }}"
                        title="{{ $course->title }}" loading="lazy"
                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                        allowfullscreen></iframe>
            </div>
        </div>
    @endif

    @if ($course->cover_path)
        <img src="{{ Storage::disk('public')->url($course->cover_path) }}" alt=""
             class="rounded-surface mb-5 w-full object-cover max-h-72">
    @endif

    {{-- The material.

         Rendered as escaped text with the line breaks kept, not as HTML. It is
         imported from PDFs and pasted from documents, which means it is
         untrusted input by any reasonable definition, and a course page is
         shown to every member of staff in the company. --}}
    <div class="card p-6 mb-6">
        <div class="prose-sm max-w-none whitespace-pre-wrap text-[15px] leading-relaxed text-gray-800">{{ $course->content }}</div>
    </div>

    {{-- ── The quiz ── --}}
    @if ($quiz)
        <div class="card p-6">
            <h2 class="text-base font-semibold text-gray-900">{{ $quiz->title }}</h2>
            <p class="text-sm text-gray-600 mt-1">
                {{ $quiz->questions_count }} {{ Str::plural('question', $quiz->questions_count) }}
                · pass at {{ $quiz->pass_mark }}%
                @if ($remaining !== null)
                    · {{ $remaining }} {{ Str::plural('attempt', $remaining) }} left
                @endif
            </p>

            @if ($best)
                <div class="mt-3 rounded-surface bg-gray-50 p-3 text-sm">
                    <p class="text-gray-800">
                        Your best: <span class="font-semibold">{{ (float) $best->percent }}%</span>
                        · {{ number_format($best->score) }} points
                        <span class="{{ $best->passed ? 'badge-success' : 'badge-warning' }} ml-1">
                            {{ $best->passed ? 'Passed' : 'Not passed yet' }}
                        </span>
                    </p>
                </div>
            @endif

            @if ($quiz->questions_count === 0)
                <p class="help mt-3">This quiz has no questions yet.</p>
            @elseif ($remaining !== null && $remaining <= 0)
                <p class="help mt-3">You have used all your attempts at this quiz.</p>
            @else
                <a href="{{ route('clock.staff.learn.quiz', $quiz->id) }}" class="btn-primary mt-4">
                    {{ $best ? 'Try again' : 'Start the quiz' }}
                </a>
            @endif

            @if ($attempts->isNotEmpty())
                <div class="mt-5 border-t border-gray-100 pt-4">
                    <p class="text-xs font-semibold uppercase tracking-wider text-gray-600 mb-2">Your attempts</p>
                    <ul class="space-y-1 text-sm">
                        @foreach ($attempts->take(5) as $attempt)
                            <li class="flex items-center justify-between gap-3">
                                <span class="text-gray-700">
                                    {{ $attempt->completed_at?->format('j M Y, H:i') }}
                                    @if ($attempt->mode === 'live') <span class="badge-brand ml-1">Live</span> @endif
                                </span>
                                <span class="tabular-nums {{ $attempt->passed ? 'text-success-700' : 'text-gray-700' }}">
                                    {{ (float) $attempt->percent }}%
                                </span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    @else
        <div class="alert-info">
            <x-icon name="info" size="h-5 w-5" class="flex-shrink-0" />
            <p>There is no quiz on this course yet — just read it through.</p>
        </div>
    @endif
</div>
