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

        {{-- ── Straight to the quiz ──

             The material on a real course runs to a couple of thousand words,
             so the quiz cards sit below a screen and a half of reading — fine
             for somebody learning it for the first time, and wrong for the far
             commoner case of a person who has read it, is on a break, and came
             back to sit the paper. They should not have to scroll past the
             lesson to find the test.

             Only when there is exactly one, and only as a shortcut: with two
             papers on offer (a Malay one beside an English one, a kitchen one
             beside a floor one) picking for them would be choosing somebody's
             language and section on their behalf. Then this becomes a jump to
             the list instead, which is honest about what it does. --}}
        @if ($quizzes->count() === 1)
            @php $only = $quizzes->first(); @endphp
            @if ($only->questions_count > 0)
                <a href="{{ route('clock.staff.learn.quiz', $only->id) }}" wire:navigate
                   class="btn-primary mt-3 w-full justify-center">
                    <x-icon name="play" size="h-4 w-4" class="mr-1" />
                    {{ ($best[$only->id] ?? null) ? 'Take the quiz again' : 'Take the quiz' }}
                </a>
            @endif
        @elseif ($quizzes->count() > 1)
            <a href="#quizzes" class="btn-primary mt-3 w-full justify-center">
                <x-icon name="play" size="h-4 w-4" class="mr-1" />
                Take a quiz ({{ $quizzes->count() }} to choose from)
            </a>
        @endif
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

    {{-- ── The material ──

         THE ORIGINAL DOCUMENT WHERE THERE IS ONE. The importer pulls the words
         out of a PDF so the AI can read them, and that text is fine for a
         machine and poor for a person: a menu or an SOP is laid out — tables,
         photographs of the dish, sections in the order somebody works through
         them — and extraction flattens all of it into a wall of prose. Staff
         were reading the wall while the document it came from sat on disk.

         THE TEXT STAYS, underneath and collapsed. A phone that will not draw a
         PDF still has to be able to read the course, and the extracted text is
         also exactly what the quiz was written from — so somebody revising for
         it is better served by the words the questions came out of. --}}
    @if ($course->sourceIsPdf())
        <div class="card overflow-hidden mb-4">
            <div class="flex items-center justify-between gap-3 border-b border-gray-100 px-4 py-2.5">
                <p class="min-w-0 truncate text-xs font-medium text-gray-700">
                    {{ $course->source_filename ?: 'Course material' }}
                </p>
                {{-- Full screen hands it to the browser's own PDF viewer, which
                     on a phone is far better than anything embedded: pinch to
                     zoom, and a page control that belongs to the platform. --}}
                <a href="{{ route('clock.staff.learn.material', $course->id) }}"
                   target="_blank" rel="noopener"
                   class="btn-secondary btn-sm shrink-0">Full screen</a>
            </div>

            {{-- 70vh: tall enough to read a page of a menu without scrolling
                 the page itself, short enough that the quiz button below stays
                 reachable. --}}
            <iframe src="{{ route('clock.staff.learn.material', $course->id) }}"
                    title="{{ $course->title }}"
                    class="h-[70vh] w-full border-0 bg-gray-100"></iframe>
        </div>

        @if ($course->content)
            <details class="card p-4 mb-6">
                <summary class="cursor-pointer list-none text-sm font-medium text-gray-900">
                    Read it as text
                    <span class="block help">
                        Plainer, and it is the version the quiz was written from.
                    </span>
                </summary>
                <div class="prose-sm mt-3 max-w-none whitespace-pre-wrap border-t border-gray-100 pt-3 text-[15px] leading-relaxed text-gray-800">{{ $course->content }}</div>
            </details>
        @endif
    @else
        {{-- Rendered as escaped text with the line breaks kept, not as HTML. It
             is imported from documents and pasted from who knows where, which
             makes it untrusted input by any reasonable definition, and a course
             page is shown to every member of staff in the company. --}}
        <div class="card p-6 mb-6">
            <div class="prose-sm max-w-none whitespace-pre-wrap text-[15px] leading-relaxed text-gray-800">{{ $course->content }}</div>
        </div>
    @endif


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

    <div id="quizzes" class="scroll-mt-4"></div>

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
                        @if ($quiz->sections->isNotEmpty())
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
