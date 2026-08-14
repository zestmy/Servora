<div>
    <x-training.flash />

    <x-page-header :title="$courseId ? 'Edit course' : 'New course'" eyebrow="Learning & Development / Courses"
                   subtitle="Bring in material you already have, then let the AI turn it into a quiz.">
        <x-slot:actions>
            <a href="{{ route('training.courses') }}" class="btn-ghost">Back to courses</a>
            <button type="button" wire:click="save" class="btn-primary">Save</button>
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        {{-- ── Left: the material ── --}}
        <div class="lg:col-span-2 space-y-4">
            <div class="card p-5">
                <h2 class="text-sm font-semibold text-gray-900 mb-3">Where the material comes from</h2>

                <div class="seg mb-4" role="tablist">
                    @foreach (\App\Models\TrainingCourse::SOURCE_TYPES as $value => $label)
                        <button type="button" role="tab" wire:click="$set('sourceType', '{{ $value }}')"
                                aria-selected="{{ $sourceType === $value ? 'true' : 'false' }}"
                                class="seg-item {{ $sourceType === $value ? 'seg-item-on' : '' }}">
                            {{ $label }}
                        </button>
                    @endforeach
                </div>

                @if ($sourceType === 'sop')
                    <div class="flex flex-wrap items-end gap-3">
                        <div class="flex-1 min-w-[220px]">
                            <label class="label" for="sop-recipe">Recipe SOP</label>
                            <select id="sop-recipe" wire:model="sourceRecipeId" class="input">
                                <option value="">Choose an SOP…</option>
                                @foreach ($recipes as $recipe)
                                    <option value="{{ $recipe->id }}">
                                        {{ $recipe->name }}{{ $recipe->category ? ' — ' . $recipe->category : '' }}
                                    </option>
                                @endforeach
                            </select>
                            @error('sourceRecipeId') <p class="error-text">{{ $message }}</p> @enderror
                        </div>
                        <button type="button" wire:click="importSop" class="btn-secondary">
                            <span wire:loading.remove wire:target="importSop">Import the SOP</span>
                            <span wire:loading wire:target="importSop">Importing…</span>
                        </button>
                    </div>
                    <p class="help mt-2">
                        Brings in the description, ingredients and method as editable text. It replaces whatever
                        is in the box below, so import first and edit after.
                    </p>
                @elseif ($sourceType === 'upload')
                    <div class="flex flex-wrap items-end gap-3">
                        <div class="flex-1 min-w-[220px]">
                            <label class="label" for="course-upload">Document</label>
                            <input id="course-upload" type="file" wire:model="upload"
                                   accept=".pdf,.docx,.txt,.md" class="input">
                            @error('upload') <p class="error-text">{{ $message }}</p> @enderror
                        </div>
                        <button type="button" wire:click="importUpload" class="btn-secondary">
                            <span wire:loading.remove wire:target="importUpload,upload">Read the document</span>
                            <span wire:loading wire:target="importUpload,upload">Reading…</span>
                        </button>
                    </div>
                    <p class="help mt-2">PDF, Word or plain text, up to 12&nbsp;MB.</p>

                    @if ($extractionNote)
                        <div class="alert-warning mt-3">
                            <x-icon name="warning" size="h-5 w-5" class="flex-shrink-0" />
                            <p>{{ $extractionNote }}</p>
                        </div>
                    @endif
                @else
                    <p class="help">Write or paste the material straight into the box below.</p>
                @endif
            </div>

            <div class="card p-5">
                <div class="flex items-center justify-between gap-3 mb-2">
                    <label class="label mb-0" for="course-content">Course material</label>
                    <span class="text-xs text-gray-600">
                        About {{ \App\Models\TrainingCourse::readingMinutes($content) }} min to read
                    </span>
                </div>
                <textarea id="course-content" wire:model.blur="content" rows="18"
                          class="input font-mono text-xs leading-relaxed"
                          placeholder="What should the team know? Write it the way you would explain it on a shift."></textarea>
                @error('content') <p class="error-text">{{ $message }}</p> @enderror
            </div>

            {{-- The AI panel sits under the material rather than at the top,
                 because the honest order is: get the material right, then ask
                 for questions about it. --}}
            <div class="card p-5">
                <div class="flex items-start gap-3">
                    <x-icon name="sparkles" size="h-5 w-5" class="mt-0.5 flex-shrink-0 text-brand-600" />
                    <div class="flex-1 min-w-0">
                        <h2 class="text-sm font-semibold text-gray-900">Generate the quiz</h2>
                        <p class="help">
                            The AI reads only what is in the box above and writes questions about it —
                            nothing from outside. You review every question before it goes live.
                            The material and the questions do not have to share a language: an English
                            SOP can be asked about in Malay.
                        </p>

                        @if (! $aiReady)
                            <div class="alert-warning mt-3">
                                <x-icon name="warning" size="h-5 w-5" class="flex-shrink-0" />
                                <p>
                                    No AI key is configured yet, so questions have to be written by hand.
                                    A system administrator can add one under Settings&nbsp;&gt;&nbsp;API Keys.
                                </p>
                            </div>
                        @else
                            <div class="mt-3 flex flex-wrap items-end gap-3">
                                <div>
                                    <label class="label" for="q-count">Questions</label>
                                    <input id="q-count" type="number" min="3" max="30" wire:model="questionCount"
                                           class="input w-24">
                                </div>
                                <div>
                                    <label class="label" for="q-difficulty">Difficulty</label>
                                    <select id="q-difficulty" wire:model="questionDifficulty" class="input">
                                        <option value="mixed">Mixed</option>
                                        <option value="easy">Easy — induction</option>
                                        <option value="hard">Hard — recertification</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="label" for="q-language">Language</label>
                                    <select id="q-language" wire:model="questionLanguage" class="input">
                                        @foreach (\App\Models\TrainingQuiz::LANGUAGES as $value => $label)
                                            <option value="{{ $value }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <button type="button" wire:click="generateQuiz" class="btn-primary"
                                        @disabled(blank($content))>
                                    <span wire:loading.remove wire:target="generateQuiz">Write the questions</span>
                                    <span wire:loading wire:target="generateQuiz">Writing… this takes a moment</span>
                                </button>
                            </div>
                            @if (blank($content))
                                <p class="help mt-2">Add some material first.</p>
                            @endif
                        @endif

                        @if ($course && $course->quizzes->isNotEmpty())
                            <div class="mt-4 border-t border-gray-100 pt-3">
                                <p class="text-xs font-medium uppercase tracking-wider text-gray-600 mb-2">Quizzes on this course</p>
                                <ul class="space-y-1">
                                    @foreach ($course->quizzes as $quiz)
                                        <li class="flex items-center justify-between gap-2 text-sm">
                                            <span class="text-gray-800">{{ $quiz->title }}</span>
                                            <a href="{{ route('training.quizzes.edit', $quiz->id) }}"
                                               class="text-brand-600 hover:underline text-xs">Open</a>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- ── Right: what it is ── --}}
        <div class="space-y-4">
            <div class="card p-5 space-y-3">
                <div>
                    <label class="label" for="course-title">Title</label>
                    <input id="course-title" type="text" wire:model="title" class="input">
                    @error('title') <p class="error-text">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="label" for="course-summary">Summary</label>
                    <textarea id="course-summary" wire:model="summary" rows="2" class="input"
                              placeholder="One line the team sees on the card."></textarea>
                    @error('summary') <p class="error-text">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="label" for="course-category">Category</label>
                    <input id="course-category" type="text" wire:model="category" class="input"
                           list="course-categories" placeholder="Coffee, Service, Safety…">
                    @error('category') <p class="error-text">{{ $message }}</p> @enderror
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="label" for="course-minutes">Minutes</label>
                        <input id="course-minutes" type="number" min="1" max="600"
                               wire:model="estimatedMinutes" class="input">
                        @error('estimatedMinutes') <p class="error-text">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="label" for="course-status">Status</label>
                        <select id="course-status" wire:model="status" class="input">
                            @foreach (\App\Models\TrainingCourse::STATUSES as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div>
                    <label class="label" for="course-video">Video link</label>
                    <input id="course-video" type="url" wire:model="videoUrl" class="input"
                           placeholder="YouTube or Vimeo">
                    @error('videoUrl') <p class="error-text">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="label" for="course-cover">Cover image</label>
                    <input id="course-cover" type="file" wire:model="cover" accept="image/*" class="input">
                    @error('cover') <p class="error-text">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="card p-5 space-y-3">
                <label class="flex items-start gap-2 text-sm text-gray-800">
                    <input type="checkbox" wire:model.live="isCompliance" class="mt-0.5 rounded-control border-gray-300">
                    <span>
                        Compliance course
                        <span class="block help">
                            Certificates, expiry dates and its own line on the compliance report.
                        </span>
                    </span>
                </label>

                @if ($isCompliance)
                    <div>
                        <label class="label" for="course-recertify">Recertify every (months)</label>
                        <input id="course-recertify" type="number" min="1" max="120"
                               wire:model="recertifyMonths" class="input w-32" placeholder="12">
                        @error('recertifyMonths') <p class="error-text">{{ $message }}</p> @enderror
                        <p class="help">Leave blank if the certificate never expires.</p>
                    </div>
                @endif
            </div>

            <div class="card p-5">
                <p class="label">Who can see it</p>
                <p class="help mb-2">Tick nothing and every outlet gets it.</p>
                <div class="max-h-56 overflow-y-auto space-y-1 pr-1">
                    @foreach ($outlets as $outlet)
                        <label class="flex items-center gap-2 text-sm text-gray-800">
                            <input type="checkbox" value="{{ $outlet->id }}" wire:model="outletIds"
                                   class="rounded-control border-gray-300">
                            {{ $outlet->name }}
                        </label>
                    @endforeach
                </div>
                @error('outletIds.*') <p class="error-text">{{ $message }}</p> @enderror
            </div>
        </div>
    </div>

    <datalist id="course-categories">
        @foreach ($recipes->pluck('category')->filter()->unique()->sort() as $category)
            <option value="{{ $category }}"></option>
        @endforeach
    </datalist>
</div>
