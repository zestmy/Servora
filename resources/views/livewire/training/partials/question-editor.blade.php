{{--
    The question form, rendered INLINE where the author is looking.

    It used to live at the bottom of the list, once, for both jobs — so
    pressing Edit on question three of eight opened a form two thousand pixels
    further down the page and nothing appeared to happen. Reported, correctly,
    as "I cannot edit the question".

    A new question opens at the TOP, because that is where the eye already is
    after pressing "Add a question", and the list below it is context rather
    than something to scroll past. An edit opens directly BELOW the question it
    belongs to, because a form that says "prompt" and "options" means nothing
    without the question it is changing sitting immediately above it.

    Included in two places rather than moved by CSS: the position is a fact
    about the DOM, and Livewire has to morph it into one place or the other.

    @param $heading  what this form is for
--}}
<div class="panel p-5 ring-2 ring-brand-200" wire:key="question-editor">
    <div class="flex items-start justify-between gap-3 mb-3">
        <h3 class="text-sm font-semibold text-gray-900">{{ $heading }}</h3>
        <button type="button" wire:click="cancelQuestion"
                class="text-xs text-gray-600 hover:text-gray-900">Close</button>
    </div>

    {{-- ── Which language ──

         Only when the quiz HAS another one, and only for a question that
         already exists — a new question is written in the original and
         translated afterwards.

         Editing a translation changes the WORDS AND NOTHING ELSE: the type,
         the points, the seconds and above all the answer key stay on the
         original. A translation that could change which option is correct
         would be a second answer key, and the first thing to go wrong would be
         a Malay reader marked down for the right answer. --}}
    @if ($editingId && count($coverage) > 0)
        <div class="mb-3">
            <label class="label" for="q-language">Editing</label>
            <select id="q-language" wire:model.live="editingLanguage" class="input">
                <option value="">{{ $quiz->languageLabel() }} — the original</option>
                @foreach (\App\Models\TrainingQuiz::LANGUAGES as $code => $label)
                    @continue ($code === ($quiz->language ?: 'en'))
                    @continue (! isset($coverage[$code]))
                    <option value="{{ $code }}">{{ $label }} — wording only</option>
                @endforeach
            </select>
            @if ($editingLanguage)
                <p class="help">
                    The machine wrote this. Correct the words freely — keep the options in the same
                    order, because the answer key is stored as positions in that list.
                </p>
            @endif
        </div>
    @endif

    <div class="space-y-3">
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 {{ $editingLanguage ? 'hidden' : '' }}">
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

        {{-- ── The picture ──
             Hidden while a translation is being edited: the image belongs to
             the question, not to any one language, and offering it here
             would imply otherwise. --}}
        @if (! $editingLanguage)
            <div>
                <p class="label">Photo <span class="font-normal text-gray-500">(optional)</span></p>

                @php
                    /* The extension check keeps temporaryUrl() from throwing on
                       a not-yet-converted HEIC — a throw here is a dead editor,
                       not a missing thumbnail. */
                    $qImagePreviewable = $qImage && is_object($qImage) && in_array(
                        strtolower($qImage->getClientOriginalExtension()),
                        ['jpg', 'jpeg', 'png', 'gif', 'webp'],
                        true,
                    );
                @endphp

                @if ($qImagePreviewable || $qImagePath)
                    <div class="mb-2 rounded-surface border border-gray-200 bg-gray-50 p-3">
                        <img class="max-h-40 rounded-surface"
                             src="{{ $qImagePreviewable ? $qImage->temporaryUrl() : Storage::disk('public')->url($qImagePath) }}"
                             alt="Question photo">
                        <button type="button" wire:click="removeQuestionImage"
                                class="mt-2 text-xs text-gray-600 hover:text-danger-600">Remove</button>
                    </div>
                @endif

                <label class="sr-only" for="q-image">Upload a photo</label>
                <input id="q-image" type="file" wire:model="qImage" accept="image/*" class="input">
                <p class="help mt-1" wire:loading wire:target="qImage">Uploading…</p>
                <p class="help">
                    Shown above the question on the trainee's phone — a chopping board, a label,
                    a fridge shelf. Up to 4&nbsp;MB.
                </p>
                @error('qImage') <p class="error-text">{{ $message }}</p> @enderror
            </div>
        @endif

        <div>
            <p class="label">
                @if ($editingLanguage)
                    Options — same order as the original
                @else
                    Options — tick the correct one{{ $qType === 'multi' ? 's' : '' }}
                @endif
            </p>
            <div class="space-y-2">
                @foreach ($qOptions as $index => $option)
                    <div class="flex items-center gap-2" wire:key="opt-{{ $index }}">
                        <input type="checkbox" value="{{ $index }}" wire:model="qCorrect"
                               aria-label="Option {{ $index + 1 }} is correct"
                               class="rounded-control border-gray-300"
                                   @disabled($editingLanguage !== '')>
                        <input type="text" wire:model="qOptions.{{ $index }}" class="input flex-1"
                               aria-label="Option {{ $index + 1 }}"
                               placeholder="Option {{ $index + 1 }}"
                               @readonly($qType === 'true_false')>
                        @if ($qType !== 'true_false' && count($qOptions) > 2 && ! $editingLanguage)
                            <button type="button" wire:click="removeOption({{ $index }})"
                                    class="icon-btn text-gray-600 hover:text-danger-500"
                                    aria-label="Remove option {{ $index + 1 }}">
                                <x-icon name="trash" size="h-4 w-4" />
                            </button>
                        @endif
                    </div>
                @endforeach
            </div>
            @if ($qType !== 'true_false' && count($qOptions) < 6 && ! $editingLanguage)
                <button type="button" wire:click="addOption" class="btn-ghost btn-sm mt-2">+ Another option</button>
            @endif
            @error('qOptions') <p class="error-text">{{ $message }}</p> @enderror
            @error('qCorrect') <p class="error-text">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="label" for="q-explanation">Why (shown after answering)</label>
            <textarea id="q-explanation" wire:model="qExplanation" rows="2" class="input"></textarea>
        </div>

        <div class="grid grid-cols-2 gap-3 {{ $editingLanguage ? 'hidden' : '' }}">
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
