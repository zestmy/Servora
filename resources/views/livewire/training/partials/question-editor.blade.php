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
