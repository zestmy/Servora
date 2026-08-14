<div class="p-4">
    @if (session()->has('error'))
        <div class="alert-danger mb-4">
            <x-icon name="alert" size="h-5 w-5" class="flex-shrink-0" />
            <p>{{ session('error') }}</p>
        </div>
    @endif

    @if (! $attempt)
        <div class="empty-state">
            <p class="empty-title">Nothing to show</p>
            <p class="empty-body">This quiz has no result for you yet.</p>
            <a href="{{ route('clock.staff.learn') }}" class="btn-primary mt-3">Back to training</a>
        </div>
    @else
        <div class="card p-8 text-center">
            @if ($attempt->passed)
                <x-icon name="trophy" size="h-12 w-12" class="mx-auto text-warning-500" />
                <h1 class="text-2xl font-bold text-gray-900 mt-3">Passed</h1>
            @else
                <x-icon name="academic" size="h-12 w-12" class="mx-auto text-gray-500" />
                <h1 class="text-2xl font-bold text-gray-900 mt-3">Not this time</h1>
            @endif

            <p class="text-sm text-gray-600 mt-1">{{ $attempt->quiz?->title }}</p>

            <div class="grid grid-cols-3 gap-4 mt-6">
                <div class="stat items-center">
                    <span class="stat-label">Score</span>
                    <span class="stat-value">{{ number_format($attempt->score) }}</span>
                </div>
                <div class="stat items-center">
                    <span class="stat-label">Correct</span>
                    <span class="stat-value">{{ $attempt->correct_count }}<span class="text-base font-normal text-gray-600">/{{ $attempt->question_count }}</span></span>
                </div>
                <div class="stat items-center">
                    <span class="stat-label">Result</span>
                    <span class="stat-value">{{ (float) $attempt->percent }}%</span>
                </div>
            </div>

            @unless ($attempt->passed)
                <p class="text-sm text-gray-600 mt-5 max-w-prose mx-auto">
                    The pass mark is {{ $attempt->quiz?->pass_mark }}%. Read the course through again —
                    the answers are all in it — and have another go.
                </p>
            @endunless
        </div>

        @if ($certificate)
            <div class="card p-5 mt-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="font-semibold text-gray-900">Your certificate is ready</p>
                        <p class="text-xs text-gray-600 font-mono">{{ $certificate->serial }}</p>
                    </div>
                    <a href="{{ route('training.certificates.pdf', $certificate->id) }}" class="btn-secondary">
                        <x-icon name="download" size="h-4 w-4" class="mr-1" /> Download
                    </a>
                </div>
            </div>
        @endif

        {{-- Every question, with the right answer and why. This is the part
             that teaches — a percentage on its own tells somebody they were
             wrong without telling them anything they can use. --}}
        @if ($attempt->answers->isNotEmpty())
            <div class="card p-5 mt-4">
                <h2 class="text-sm font-semibold text-gray-900 mb-3">What you answered</h2>
                <ol class="space-y-4">
                    @foreach ($attempt->answers as $answer)
                        @php $question = $answer->question; @endphp
                        @continue (! $question)
                        <li wire:key="review-{{ $answer->id }}" class="border-b border-gray-100 pb-4 last:border-b-0 last:pb-0">
                            <div class="flex items-start gap-2">
                                @if ($answer->is_correct)
                                    <x-icon name="check" size="h-5 w-5" class="mt-0.5 flex-shrink-0 text-success-600" />
                                @else
                                    <x-icon name="alert" size="h-5 w-5" class="mt-0.5 flex-shrink-0 text-warning-600" />
                                @endif
                                <div class="min-w-0 flex-1">
                                    <p class="font-medium text-gray-900">{{ $question->prompt }}</p>

                                    <ul class="mt-2 space-y-1 text-sm">
                                        @foreach ($question->optionList() as $index => $option)
                                            @php
                                                $right = in_array($index, array_map('intval', (array) $question->correct), true);
                                                $mine  = in_array($index, array_map('intval', (array) $answer->chosen), true);
                                            @endphp
                                            @if ($right || $mine)
                                                <li class="{{ $right ? 'text-success-800' : 'text-danger-800' }}">
                                                    {{ $option }}
                                                    <span class="text-xs text-gray-600">
                                                        {{ $right ? '— correct' : '' }}{{ $mine && ! $right ? '— you chose this' : '' }}
                                                    </span>
                                                </li>
                                            @endif
                                        @endforeach
                                    </ul>

                                    @if ($answer->timedOut())
                                        <p class="help mt-1">You ran out of time on this one.</p>
                                    @endif

                                    @if ($question->explanation)
                                        <p class="mt-2 text-sm text-gray-700">{{ $question->explanation }}</p>
                                    @endif
                                </div>
                            </div>
                        </li>
                    @endforeach
                </ol>
            </div>
        @endif

        {{-- $course, NOT $attempt->quiz->course. A quiz can be published while
             its course is still a draft, and the staff course screen refuses
             to open a draft — so the relation being present said nothing about
             whether the link would work. It reliably produced a 404 for
             somebody who had just finished a quiz. See QuizPlay::readableCourse. --}}
        <div class="flex flex-wrap gap-2 mt-5">
            @if ($course)
                <a href="{{ route('clock.staff.learn.course', $course->id) }}" class="btn-secondary">
                    Back to the course
                </a>
            @endif
            <a href="{{ route('clock.staff.learn.progress') }}" class="btn-primary">My progress</a>
            <a href="{{ route('clock.staff.learn') }}" class="btn-ghost">All training</a>
        </div>
    @endif
</div>
