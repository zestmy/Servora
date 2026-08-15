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
                        <select id="regen-language" wire:model="language" class="input">
                            @foreach (\App\Models\TrainingQuiz::LANGUAGES as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <label class="flex items-center gap-2 pb-2 text-sm text-gray-800">
                        <input type="checkbox" wire:model="replaceExisting" class="rounded-control border-gray-300">
                        Replace the current questions
                    </label>

                    <button type="button" wire:click="regenerate" class="btn-primary">
                        <span wire:loading.remove wire:target="regenerate">Rewrite</span>
                        <span wire:loading wire:target="regenerate">Writing…</span>
                    </button>
                    <button type="button" wire:click="$set('showGenerate', false)" class="btn-ghost">Cancel</button>
                </div>

                {{-- This is the language the questions are WRITTEN in, and it
                     is not how a quiz reaches a Malay-speaking floor. That is
                     the languages card in the sidebar, which translates these
                     questions rather than writing different ones. --}}
                <p class="help mt-3">
                    Rewriting replaces this quiz's questions — and any translations of them
                    go with the questions they belonged to. To offer the same quiz in another
                    language, use <span class="font-medium text-gray-700">Languages</span> instead.
                </p>
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

            {{-- A NEW question opens at the top: it is where the eye is after
                 pressing "Add a question", and the existing list reads as
                 context below it rather than as something to scroll past. --}}
            @if ($editingId === 0)
                @include('livewire.training.partials.question-editor', ['heading' => 'New question'])
            @endif

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

                {{-- Directly BELOW the question it belongs to. A form full of
                     prompts and options means nothing without the question it
                     is changing sitting immediately above it. --}}
                @if ($editingId === $question->id)
                    @include('livewire.training.partials.question-editor', ['heading' => 'Edit question ' . ($i + 1)])
                @endif
            @empty
                <div class="empty-state">
                    <x-icon name="sparkles" size="h-8 w-8" class="text-gray-500" />
                    <p class="empty-title">No questions yet</p>
                    <p class="empty-body">
                        Let the AI draft a set from the course material, or write the first one yourself.
                    </p>
                </div>
            @endforelse
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
                {{-- ── Who it is for ──

                     TICKED SETS, NOT TWO DROPDOWNS. A single section forces
                     every real audience into "one section or everybody": a menu
                     paper is for FOH and Bar but not the kitchen, an allergen
                     paper for the kitchen and FOH but not the office. Neither
                     was expressible, so authors wrote the quiz twice — and two
                     papers drift, score separately, and have to be added up by
                     hand.

                     Nothing ticked means everybody, on both sides. It is what
                     every existing quiz means, and it is the forgiving
                     direction to fail: hiding safety material from people looks
                     like nothing is wrong at all. --}}
                <div>
                    <p class="label">Who it is for</p>

                    <fieldset class="mt-1">
                        <legend class="sr-only">Sections</legend>
                        <p class="text-xs font-medium text-gray-700">Sections</p>
                        @if ($sections->isEmpty())
                            <p class="help">No sections set up yet — this quiz reaches everybody.</p>
                        @else
                            <div class="mt-1 flex flex-wrap gap-x-4 gap-y-1.5">
                                @foreach ($sections as $section)
                                    <label class="flex items-center gap-2 text-sm text-gray-800">
                                        <input type="checkbox" value="{{ $section->id }}" wire:model="sectionIds"
                                               class="rounded-control border-gray-300">
                                        {{ $section->name }}
                                    </label>
                                @endforeach
                            </div>
                        @endif
                        @error('sectionIds.*') <p class="error-text">{{ $message }}</p> @enderror
                    </fieldset>

                    <fieldset class="mt-3">
                        <legend class="sr-only">Outlets</legend>
                        <p class="text-xs font-medium text-gray-700">Outlets</p>
                        <div class="mt-1 flex flex-wrap gap-x-4 gap-y-1.5">
                            @foreach ($outlets as $outlet)
                                <label class="flex items-center gap-2 text-sm text-gray-800">
                                    <input type="checkbox" value="{{ $outlet->id }}" wire:model="outletIds"
                                           class="rounded-control border-gray-300">
                                    {{ $outlet->name }}
                                </label>
                            @endforeach
                        </div>
                        @error('outletIds.*') <p class="error-text">{{ $message }}</p> @enderror
                    </fieldset>

                    <p class="help mt-2">
                        Tick nothing on a row to mean all of it. The two narrow each other, so
                        “Kitchen” plus “Bangsar” reaches the kitchen at Bangsar and nowhere else.
                        Leave both empty for anything safety- or compliance-related.
                    </p>
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

                {{-- ── Background music ──

                     TWO WAYS IN, and they are not equivalent. An uploaded file
                     is a native <audio> element in the same document as the
                     button, so the tap authorises it on every platform and it
                     draws nothing. A YouTube link is an embed, and a user
                     gesture belongs to the frame it happened in — so it plays
                     on Android and on a computer and NOT on an iPhone, whatever
                     is done on our side of the iframe.

                     Both are offered because the link is the quick way to try
                     something. The copy says which is which, because a field
                     that works on the author's laptop and fails on the floor's
                     phones is only fair if it says so. --}}
                <div>
                    <p class="label">Background music</p>

                    @if ($musicPath || $musicFile)
                        <div class="mb-2 rounded-surface border border-gray-200 bg-gray-50 p-3">
                            <p class="text-sm font-medium text-gray-900">
                                {{ $musicFile ? 'Ready to save' : 'Current track' }}
                            </p>
                            @if ($musicPath && ! $musicFile)
                                <audio controls preload="none" class="mt-2 w-full"
                                       src="{{ Storage::disk('public')->url($musicPath) }}"></audio>
                            @endif
                            <button type="button" wire:click="removeMusicFile"
                                    class="mt-2 text-xs text-gray-600 hover:text-danger-600">Remove</button>
                        </div>
                    @endif

                    <label class="sr-only" for="quiz-music-file">Upload a track</label>
                    <input id="quiz-music-file" type="file" wire:model="musicFile" accept="audio/*" class="input">
                    <p class="help mt-1" wire:loading wire:target="musicFile">Uploading…</p>
                    <p class="help">
                        An mp3 or m4a, up to 12&nbsp;MB. This is the option that plays on every phone,
                        iPhones included.
                    </p>
                    @error('musicFile') <p class="error-text">{{ $message }}</p> @enderror

                    {{-- The link, kept because it is the quick way to try a
                         track — and labelled with what it cannot do. It plays
                         hidden, so no window ever appears over the quiz; on an
                         iPhone that means it does not play at all, because a
                         gesture belongs to the frame it happened in and reaching
                         an embedded player means crossing into a cross-origin
                         iframe. The file above is the answer for phones. --}}
                    <label class="label mt-3" for="quiz-music">…or a link</label>
                    <input id="quiz-music" type="url" wire:model="musicUrl" class="input"
                           placeholder="https://www.youtube.com/watch?v=…">
                    <p class="help">
                        A DIRECT AUDIO LINK — one ending .mp3, .m4a, .ogg or .wav — behaves exactly
                        like an upload and plays on every phone. A YouTube link plays out of sight on
                        Android and on a computer, but
                        <span class="font-medium text-gray-700">iPhones cannot play an embed</span>
                        at all. Used only when no file is uploaded.
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

            {{-- ── Languages ──

                 ONE QUIZ, several languages, rather than one quiz per
                 language. A second quiz would be its own row everywhere it
                 counts — its own attempts, its own leaderboard line, its own
                 assignment to clear — so a floor where half the staff read
                 Malay would produce two of every number and "who has done the
                 allergen quiz" would have to be asked twice. Here the questions
                 are the same questions with the same answer key, and only the
                 words differ. See the migration on
                 training_question_translations. --}}
            <div class="card p-5">
                <h2 class="text-sm font-semibold text-gray-900">Languages</h2>
                <p class="help mt-1">
                    Staff pick one before they start. Their score counts once whichever they read.
                </p>

                <ul class="mt-3 space-y-1.5">
                    <li class="flex items-center justify-between gap-2 text-sm">
                        <span class="font-medium text-gray-900">{{ $quiz->languageLabel() }}</span>
                        <span class="badge-neutral">Written in</span>
                    </li>

                    @foreach (\App\Models\TrainingQuiz::LANGUAGES as $code => $label)
                        @continue ($code === ($quiz->language ?: 'en'))
                        @php $done = $coverage[$code] ?? 0; @endphp
                        @continue ($done === 0)

                        <li class="flex items-center justify-between gap-2 text-sm">
                            <span class="text-gray-900">{{ $label }}</span>
                            <span class="flex items-center gap-2">
                                @if (isset($available[$code]))
                                    <span class="badge-success">Offered</span>
                                @else
                                    {{-- A partial translation is NOT offered to
                                         staff: somebody who picked Malay would
                                         be dropped into English at question
                                         five, at speed, for points. --}}
                                    <span class="badge-warning">{{ $done }} of {{ $questions->count() }}</span>
                                @endif
                                @can('training.manage')
                                    <button type="button" wire:click="removeLanguage('{{ $code }}')"
                                            data-confirm-delete="Remove {{ $label }} from this quiz? The translated wording is deleted."
                                            class="text-xs text-gray-600 hover:text-danger-500">Remove</button>
                                @endcan
                            </span>
                        </li>
                    @endforeach
                </ul>

                @can('training.manage')
                    @if ($questions->isEmpty())
                        <p class="help mt-3">Write the questions first, then translate them.</p>
                    @elseif (! $aiReady)
                        <p class="help mt-3">Translation needs the AI key, under Settings &gt; API Keys.</p>
                    @else
                        <div class="mt-3 flex items-end gap-2">
                            <div class="flex-1">
                                <label class="sr-only" for="translate-to">Translate into</label>
                                <select id="translate-to" wire:model="translateTo" class="input">
                                    <option value="">Add a language…</option>
                                    @foreach (\App\Models\TrainingQuiz::LANGUAGES as $code => $label)
                                        @continue ($code === ($quiz->language ?: 'en'))
                                        <option value="{{ $code }}">
                                            {{ ($coverage[$code] ?? 0) > 0 ? 'Finish ' : '' }}{{ $label }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <button type="button" wire:click="translate" class="btn-secondary"
                                    @disabled($translateTo === '')>
                                <span wire:loading.remove wire:target="translate">Translate</span>
                                <span wire:loading wire:target="translate">Translating…</span>
                            </button>
                        </div>
                        <p class="help mt-2">
                            The same questions in the same order — read them through before publishing.
                        </p>
                    @endif
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
