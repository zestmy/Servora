<div>
    <x-training.flash />

    <x-page-header :title="$quiz->title" eyebrow="Learning & Development / Quizzes"
                   :subtitle="$quiz->course?->title ? 'From the course “' . $quiz->course->title . '”' : 'Not attached to a course'">
        <x-slot:actions>
            <a href="{{ route('training.quizzes') }}" class="btn-ghost">Back to quizzes</a>
            @can('training.manage')
                @if ($quiz->course)
                    <button type="button" wire:click="$toggle('showGenerate')" class="btn-secondary">
                        <x-icon name="sparkles" size="h-4 w-4" class="mr-1" /> Rewrite with AI
                    </button>
                @endif
            @endcan
        </x-slot:actions>
    </x-page-header>

    @if ($showGenerate)
        <div class="card p-5 mb-4">
            <h2 class="text-sm font-semibold text-gray-900 mb-1">Rewrite the questions</h2>
            <p class="help mb-3">Reads the course material again and writes a fresh set.</p>

            @if (! $aiReady)
                <div class="alert-warning">
                    <x-icon name="warning" size="h-5 w-5" class="flex-shrink-0" />
                    <p>No AI key is configured. A system administrator can add one under Settings &gt; API Keys.</p>
                </div>
            @else
                <div class="flex flex-wrap items-end gap-3">
                    <div>
                        <label class="label" for="regen-count">Questions</label>
                        <input id="regen-count" type="number" min="3" max="30" wire:model="questionCount" class="input w-24">
                    </div>
                    <div>
                        <label class="label" for="regen-difficulty">Difficulty</label>
                        <select id="regen-difficulty" wire:model="questionDifficulty" class="input">
                            <option value="mixed">Mixed</option>
                            <option value="easy">Easy</option>
                            <option value="hard">Hard</option>
                        </select>
                    </div>
                    <div>
                        <label class="label" for="regen-language">Write it in</label>
                        <select id="regen-language" wire:model.live="genLanguage" class="input">
                            @foreach (\App\Models\TrainingQuiz::LANGUAGES as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    @if ($genTarget === 'replace')
                        <label class="flex items-center gap-2 pb-2 text-sm text-gray-800">
                            <input type="checkbox" wire:model="replaceExisting" class="rounded-control border-gray-300">
                            Replace the current questions
                        </label>
                    @endif

                    <button type="button" wire:click="regenerate" class="btn-primary">
                        <span wire:loading.remove wire:target="regenerate">
                            {{ $genTarget === 'new' ? 'Write the new paper' : 'Rewrite' }}
                        </span>
                        <span wire:loading wire:target="regenerate">Writing…</span>
                    </button>
                    <button type="button" wire:click="$set('showGenerate', false)" class="btn-ghost">Cancel</button>
                </div>

                {{-- WHERE IT LANDS. This is the whole of a reported data loss:
                     picking Malay here used to change what the quiz WAS and
                     then overwrite its questions, so a Malay paper written
                     beside an English one arrived by destroying it. A course is
                     meant to carry both — the staff course screen offers every
                     published quiz for the reader's section and lets them
                     choose — so a different language now defaults to a separate
                     paper, and says so before anything is written. --}}
                <div class="mt-4 border-t border-gray-100 pt-3">
                    <p class="label mb-1.5">Where the questions go</p>
                    <div class="space-y-1.5">
                        <label class="flex items-start gap-2 text-sm text-gray-800">
                            <input type="radio" value="replace" wire:model.live="genTarget"
                                   class="mt-0.5 border-gray-300 text-brand-600">
                            <span>Into this quiz
                                <span class="block help">
                                    “{{ $quiz->title }}” keeps its title and settings. Its current
                                    questions are replaced if the box above is ticked.
                                </span>
                            </span>
                        </label>
                        <label class="flex items-start gap-2 text-sm text-gray-800">
                            <input type="radio" value="new" wire:model.live="genTarget"
                                   class="mt-0.5 border-gray-300 text-brand-600">
                            <span>Into a new quiz on the same course
                                <span class="block help">
                                    A separate paper in {{ \App\Models\TrainingQuiz::LANGUAGES[$genLanguage] ?? $genLanguage }},
                                    starting as a draft. This one is left exactly as it is, and staff
                                    are offered both on the course page.
                                </span>
                            </span>
                        </label>
                    </div>
                </div>
            @endif
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        {{-- ── Questions ── --}}
        <div class="lg:col-span-2 space-y-3">
            <div class="flex items-center justify-between gap-3">
                <h2 class="text-sm font-semibold text-gray-900">
                    {{ $questions->count() }} question{{ $questions->count() === 1 ? '' : 's' }}
                    <span class="font-normal text-gray-600">· {{ number_format($quiz->maxScore()) }} points on offer</span>
                </h2>
                @can('training.manage')
                    <button type="button" wire:click="newQuestion" class="btn-secondary btn-sm">+ Add a question</button>
                @endcan
            </div>

            @forelse ($questions as $i => $question)
                {{-- The one being edited is marked, so the page says which
                     question the form below belongs to. --}}
                <div wire:key="q-{{ $question->id }}"
                     class="card p-4 {{ $editingId === $question->id ? 'ring-2 ring-brand-300' : '' }}">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0 flex-1">
                            <p class="text-xs text-gray-600 mb-1">
                                {{ $i + 1 }} · {{ \App\Models\TrainingQuestion::TYPES[$question->type] ?? $question->type }}
                                · {{ \App\Models\TrainingQuestion::DIFFICULTIES[$question->difficulty] ?? $question->difficulty }}
                                · {{ $question->pointsValue($quiz) }} pts
                                · {{ $question->secondsValue($quiz) }}s
                                @if ($question->topic)
                                    · <span class="badge-neutral">{{ $question->topic }}</span>
                                @endif
                            </p>
                            <p class="font-medium text-gray-900">{{ $question->prompt }}</p>
                            <ul class="mt-2 space-y-1">
                                @foreach ($question->optionList() as $index => $option)
                                    @php $isRight = in_array($index, array_map('intval', (array) $question->correct), true); @endphp
                                    <li class="flex items-start gap-2 text-sm {{ $isRight ? 'text-success-800' : 'text-gray-700' }}">
                                        @if ($isRight)
                                            <x-icon name="check" size="h-4 w-4" class="mt-0.5 flex-shrink-0" />
                                        @else
                                            <span class="mt-0.5 h-4 w-4 flex-shrink-0" aria-hidden="true"></span>
                                        @endif
                                        <span>{{ $option }}</span>
                                    </li>
                                @endforeach
                            </ul>
                            @if ($question->explanation)
                                <p class="help mt-2">{{ $question->explanation }}</p>
                            @endif
                        </div>

                        @can('training.manage')
                            <div class="flex flex-col items-end gap-1 text-xs">
                                <div class="flex gap-1">
                                    <button wire:click="move({{ $question->id }}, 'up')" class="icon-btn" aria-label="Move up"
                                            @disabled($i === 0)>
                                        <x-icon name="chevron-down" size="h-4 w-4" class="rotate-180" />
                                    </button>
                                    <button wire:click="move({{ $question->id }}, 'down')" class="icon-btn" aria-label="Move down"
                                            @disabled($i === $questions->count() - 1)>
                                        <x-icon name="chevron-down" size="h-4 w-4" />
                                    </button>
                                </div>
                                <button wire:click="editQuestion({{ $question->id }})" class="text-brand-600 hover:underline">Edit</button>
                                <button wire:click="deleteQuestion({{ $question->id }})"
                                        data-confirm-delete="Delete this question?"
                                        class="text-gray-600 hover:text-danger-500">Delete</button>
                            </div>
                        @endcan
                    </div>
                </div>
            @empty
                <div class="empty-state">
                    <x-icon name="sparkles" size="h-8 w-8" class="text-gray-500" />
                    <p class="empty-title">No questions yet</p>
                    <p class="empty-body">
                        Let the AI draft a set from the course material, or write the first one yourself.
                    </p>
                </div>
            @endforelse

            {{-- ── Question editor ──

                 IT SCROLLS ITSELF INTO VIEW, and that is a bug fix rather than
                 a flourish. The editor renders below the question list, so on
                 an eight-question quiz pressing Edit on question three opened a
                 form roughly two thousand pixels further down the page. The
                 form worked perfectly; the screen simply did not move, so the
                 button read as dead — reported, correctly, as "I cannot edit
                 the question".

                 x-init rather than a dispatched event: the panel is inserted
                 into the DOM by the morph, which is exactly when Alpine
                 initialises it, and once per opening rather than on every
                 keystroke that re-renders it. --}}
            @if ($editingId !== null)
                <div class="panel p-5 ring-2 ring-brand-200" wire:key="question-editor"
                     x-data
                     x-init="$nextTick(() => $el.scrollIntoView({
                         behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth',
                         block: 'center',
                     }))">
                    <div class="flex items-start justify-between gap-3 mb-3">
                        <h3 class="text-sm font-semibold text-gray-900">
                            {{ $editingId ? 'Edit question' : 'New question' }}
                        </h3>
                        <button type="button" wire:click="cancelQuestion"
                                class="text-xs text-gray-600 hover:text-gray-900">Close</button>
                    </div>

                    <div class="space-y-3">
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div>
                                <label class="label" for="q-type">Type</label>
                                <select id="q-type" wire:model.live="qType" class="input">
                                    @foreach (\App\Models\TrainingQuestion::TYPES as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="label" for="q-difficulty-edit">Difficulty</label>
                                <select id="q-difficulty-edit" wire:model="qDifficulty" class="input">
                                    @foreach (\App\Models\TrainingQuestion::DIFFICULTIES as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="label" for="q-topic">Topic</label>
                                <input id="q-topic" type="text" wire:model="qTopic" class="input"
                                       placeholder="Allergens, Milk, Upselling…">
                            </div>
                        </div>

                        <div>
                            <label class="label" for="q-prompt">Question</label>
                            <textarea id="q-prompt" wire:model="qPrompt" rows="2" class="input"></textarea>
                            @error('qPrompt') <p class="error-text">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <p class="label">Options — tick the correct one{{ $qType === 'multi' ? 's' : '' }}</p>
                            <div class="space-y-2">
                                @foreach ($qOptions as $index => $option)
                                    <div class="flex items-center gap-2" wire:key="opt-{{ $index }}">
                                        <input type="checkbox" value="{{ $index }}" wire:model="qCorrect"
                                               aria-label="Option {{ $index + 1 }} is correct"
                                               class="rounded-control border-gray-300">
                                        <input type="text" wire:model="qOptions.{{ $index }}" class="input flex-1"
                                               aria-label="Option {{ $index + 1 }}"
                                               placeholder="Option {{ $index + 1 }}"
                                               @readonly($qType === 'true_false')>
                                        @if ($qType !== 'true_false' && count($qOptions) > 2)
                                            <button type="button" wire:click="removeOption({{ $index }})"
                                                    class="icon-btn text-gray-600 hover:text-danger-500"
                                                    aria-label="Remove option {{ $index + 1 }}">
                                                <x-icon name="trash" size="h-4 w-4" />
                                            </button>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                            @if ($qType !== 'true_false' && count($qOptions) < 6)
                                <button type="button" wire:click="addOption" class="btn-ghost btn-sm mt-2">+ Another option</button>
                            @endif
                            @error('qOptions') <p class="error-text">{{ $message }}</p> @enderror
                            @error('qCorrect') <p class="error-text">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="label" for="q-explanation">Why (shown after answering)</label>
                            <textarea id="q-explanation" wire:model="qExplanation" rows="2" class="input"></textarea>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="label" for="q-points">Points</label>
                                <input id="q-points" type="number" min="100" max="5000" wire:model="qPoints"
                                       class="input" placeholder="{{ $quiz->default_points }}">
                                <p class="help">Blank uses the quiz default.</p>
                            </div>
                            <div>
                                <label class="label" for="q-seconds">Seconds</label>
                                <input id="q-seconds" type="number" min="5" max="300" wire:model="qSeconds"
                                       class="input" placeholder="{{ $quiz->default_seconds }}">
                                <p class="help">Blank uses the quiz default.</p>
                            </div>
                        </div>

                        <div class="flex items-center gap-2">
                            <button type="button" wire:click="saveQuestion" class="btn-primary">Save question</button>
                            <button type="button" wire:click="cancelQuestion" class="btn-ghost">Cancel</button>
                        </div>
                    </div>
                </div>
            @endif
        </div>

        {{-- ── Settings ── --}}
        <div class="space-y-4">
            <div class="card p-5 space-y-3">
                <h2 class="text-sm font-semibold text-gray-900">Quiz settings</h2>

                <div>
                    <label class="label" for="quiz-title">Title</label>
                    <input id="quiz-title" type="text" wire:model="title" class="input">
                    @error('title') <p class="error-text">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="label" for="quiz-description">Description</label>
                    <textarea id="quiz-description" wire:model="description" rows="2" class="input"></textarea>
                </div>
                <div>
                    <label class="label" for="quiz-section">Who it is for</label>
                    <select id="quiz-section" wire:model="sectionId" class="input">
                        <option value="">Everyone</option>
                        @foreach ($sections as $section)
                            <option value="{{ $section->id }}">{{ $section->name }}</option>
                        @endforeach
                    </select>
                    <p class="help">
                        The same course can carry one questionnaire for the kitchen and another for
                        the floor. Leave it on Everyone for anything safety- or compliance-related.
                    </p>
                    @error('sectionId') <p class="error-text">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="label" for="quiz-language">Question language</label>
                    <select id="quiz-language" wire:model.live="language" class="input">
                        @foreach (\App\Models\TrainingQuiz::LANGUAGES as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    <p class="help">
                        What the AI writes the questions in. The course material can be in any
                        language — an English SOP can be asked about in Malay.
                    </p>
                    @error('language') <p class="error-text">{{ $message }}</p> @enderror
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="label" for="quiz-status-edit">Status</label>
                        <select id="quiz-status-edit" wire:model="status" class="input">
                            @foreach (\App\Models\TrainingQuiz::STATUSES as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('status') <p class="error-text">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="label" for="quiz-pass">Pass mark %</label>
                        <input id="quiz-pass" type="number" min="1" max="100" wire:model="passMark" class="input">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="label" for="quiz-seconds">Seconds / question</label>
                        <input id="quiz-seconds" type="number" min="5" max="300" wire:model="defaultSeconds" class="input">
                    </div>
                    <div>
                        <label class="label" for="quiz-points">Points / question</label>
                        <input id="quiz-points" type="number" min="100" max="5000" wire:model="defaultPoints" class="input">
                    </div>
                </div>
                <div>
                    <label class="label" for="quiz-attempts">Attempts allowed</label>
                    <input id="quiz-attempts" type="number" min="0" max="20" wire:model="maxAttempts" class="input w-28">
                    <p class="help">0 means unlimited. Live sessions never use up an attempt.</p>
                </div>
            </div>

            <div class="card p-5 space-y-2">
                <h2 class="text-sm font-semibold text-gray-900">How it plays</h2>

                <label class="flex items-start gap-2 text-sm text-gray-800">
                    <input type="checkbox" wire:model="speedBonus" class="mt-0.5 rounded-control border-gray-300">
                    <span>Speed bonus
                        <span class="block help">Full points instantly, half on the buzzer. Turn it off for compliance.</span>
                    </span>
                </label>
                <label class="flex items-start gap-2 text-sm text-gray-800">
                    <input type="checkbox" wire:model="streakBonus" class="mt-0.5 rounded-control border-gray-300">
                    <span>Streak bonus
                        <span class="block help">+10% at three in a row, +20% at five.</span>
                    </span>
                </label>
                <div class="pt-1">
                    <label class="label" for="quiz-penalty">Wrong answer costs</label>
                    <div class="flex items-center gap-2">
                        <input id="quiz-penalty" type="number" min="0" max="100" step="5"
                               wire:model="wrongPenaltyPercent" class="input w-24">
                        <span class="text-sm text-gray-700">% of the question's points</span>
                    </div>
                    <p class="help">
                        0 means a wrong answer is simply worth nothing, which is how Kahoot plays it.
                        Raise it to make guessing expensive on a safety paper. Running out of time never
                        costs anything, and nobody's total goes below zero.
                    </p>
                    @error('wrongPenaltyPercent') <p class="error-text">{{ $message }}</p> @enderror
                </div>

                <div class="pt-1">
                    <label class="label" for="quiz-music">Background music</label>
                    <input id="quiz-music" type="url" wire:model="musicUrl" class="input"
                           placeholder="https://www.youtube.com/watch?v=…">
                    <p class="help">
                        A YouTube link — a video or a playlist. It plays quietly behind the questions on
                        the staff phone, muted until they tap the speaker. Leave it blank for silence.
                    </p>
                    @error('musicUrl') <p class="error-text">{{ $message }}</p> @enderror
                </div>

                <label class="flex items-center gap-2 text-sm text-gray-800">
                    <input type="checkbox" wire:model="shuffleQuestions" class="rounded-control border-gray-300">
                    Shuffle the questions
                </label>
                <label class="flex items-center gap-2 text-sm text-gray-800">
                    <input type="checkbox" wire:model="shuffleOptions" class="rounded-control border-gray-300">
                    Shuffle the options
                </label>
                <label class="flex items-start gap-2 text-sm text-gray-800">
                    <input type="checkbox" wire:model="issuesCertificate" class="mt-0.5 rounded-control border-gray-300">
                    <span>Issue a certificate on passing
                        <span class="block help">Uses the course's recertify period for the expiry date.</span>
                    </span>
                </label>

                @can('training.manage')
                    <button type="button" wire:click="saveSettings" class="btn-primary w-full mt-2">Save settings</button>
                @endcan
            </div>

            {{-- The poster. A link and a QR that take somebody from a
                 noticeboard to the first question in one scan and one PIN. --}}
            <div class="card p-5">
                <h2 class="text-sm font-semibold text-gray-900">Share this quiz</h2>

                @if (! $shareUrl)
                    <p class="help mt-1">
                        Publish the quiz and a public link appears here — a QR for the pass, or a
                        link to drop in the staff group. It asks for the same PIN they clock in with.
                    </p>
                @else
                    <p class="help mt-1">
                        Print the code or send the link. Staff scan it, key in their PIN, and start.
                    </p>

                    <div class="mt-3 flex items-start gap-4">
                        <img src="{{ $shareQr }}" alt="QR code for {{ $quiz->title }}"
                             class="h-28 w-28 shrink-0 rounded-surface border border-gray-200 bg-white p-1">

                        <div class="min-w-0 flex-1"
                             x-data="{ copied: false }">
                            <p class="break-all rounded-control bg-gray-50 px-2 py-1.5 font-mono text-xs text-gray-700">
                                {{ $shareUrl }}
                            </p>

                            {{-- navigator.clipboard is unavailable on plain
                                 http, which is every local dev setup — the
                                 textarea fallback is what keeps the button from
                                 being dead there. --}}
                            <button type="button" class="btn-secondary btn-sm mt-2"
                                    @click="
                                        const url = @js($shareUrl);
                                        if (navigator.clipboard) {
                                            navigator.clipboard.writeText(url);
                                        } else {
                                            const box = document.createElement('textarea');
                                            box.value = url;
                                            document.body.appendChild(box);
                                            box.select();
                                            document.execCommand('copy');
                                            box.remove();
                                        }
                                        copied = true;
                                        setTimeout(() => copied = false, 2000);
                                    ">
                                <span x-show="! copied">Copy link</span>
                                <span x-show="copied" x-cloak class="text-success-700">Copied</span>
                            </button>
                        </div>
                    </div>
                @endif
            </div>

            @if ($quiz->generated_by_ai)
                <p class="text-xs text-gray-600">
                    First drafted by {{ $quiz->ai_model }}
                    @if ($quiz->generated_at) on {{ $quiz->generated_at->format('j M Y, H:i') }} @endif.
                    Every question here is whatever you last saved.
                </p>
            @endif
        </div>
    </div>
</div>
