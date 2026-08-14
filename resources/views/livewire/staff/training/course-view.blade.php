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


    {{-- ── The quizzes ──

         A LIST, not one quiz. The same material can carry a kitchen
         questionnaire and a floor one, and an English set beside a Malay set —
         so the page offers whatever this person is entitled to and lets them
         pick. Section filtering already happened in SQL; anything still here is
         theirs to take. --}}
    @php
        // Label the language on EVERY paper when there is more than one to
        // choose between, English included. A badge that appears only on the
        // Malay set tells a Malay reader which one is theirs and tells an
        // English reader nothing at all — the unlabelled card is just the one
        // without a badge, which is not how anybody reads a list.
        $multilingual = $quizzes->pluck('language')
            ->map(fn ($l) => $l ?: 'en')->unique()->count() > 1;
    @endphp

    @forelse ($quizzes as $quiz)
        @php $mine = $best[$quiz->id] ?? null; @endphp
        <div wire:key="quiz-{{ $quiz->id }}" class="card p-5 mb-3">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <h2 class="font-semibold text-gray-900">{{ $quiz->title }}</h2>
                    <p class="text-xs text-gray-600 mt-0.5">
                        {{ $quiz->questions_count }} {{ Str::plural('question', $quiz->questions_count) }}
                        · pass {{ $quiz->pass_mark }}%
                        @if ($multilingual || ($quiz->language ?? 'en') !== 'en')
                            · <span class="badge-neutral">{{ $quiz->languageLabel() }}</span>
                        @endif
                        @if ($quiz->section_id)
                            · <span class="badge-brand">{{ $quiz->sectionLabel() }}</span>
                        @endif
                    </p>
                </div>
                @if ($mine)
                    <span class="{{ $mine->passed ? 'badge-success' : 'badge-warning' }} shrink-0">
                        {{ (float) $mine->percent }}%
                    </span>
                @endif
            </div>

            @php $remaining = $quiz->attemptsRemaining($employee->id); @endphp

            @if ($quiz->questions_count === 0)
                <p class="help mt-3">No questions on this one yet.</p>
            @elseif ($remaining !== null && $remaining <= 0)
                <p class="help mt-3">You have used all your attempts at this quiz.</p>
            @else
                <a href="{{ route('clock.staff.learn.quiz', $quiz->id) }}" wire:navigate
                   class="btn-primary mt-3 w-full justify-center">
                    {{ $mine ? 'Try again' : 'Start' }}
                </a>
                @if ($remaining !== null)
                    <p class="help mt-1 text-center">
                        {{ $remaining }} {{ Str::plural('attempt', $remaining) }} left
                    </p>
                @endif
            @endif
        </div>
    @empty
        <div class="alert-info">
            <x-icon name="info" size="h-5 w-5" class="flex-shrink-0" />
            <p>No quiz for your section on this course yet — read it through.</p>
        </div>
    @endforelse
</div>
