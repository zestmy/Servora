<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One question.
 *
 * No CompanyScope: a question is reachable only through its quiz, which is
 * scoped, and adding a company_id here would be a second copy of the same fact
 * that could disagree with the first.
 */
class TrainingQuestion extends Model
{
    public const TYPES = [
        'mcq'        => 'Multiple choice',
        'true_false' => 'True or false',
        'multi'      => 'Choose all that apply',
    ];

    public const DIFFICULTIES = [
        'easy'   => 'Easy',
        'medium' => 'Medium',
        'hard'   => 'Hard',
    ];

    protected $fillable = [
        'training_quiz_id', 'type', 'prompt', 'options', 'correct',
        'explanation', 'difficulty', 'topic', 'points', 'seconds',
        'image_path', 'sort_order',
    ];

    protected $casts = [
        'options'    => 'array',
        'correct'    => 'array',
        'points'     => 'integer',
        'seconds'    => 'integer',
        'sort_order' => 'integer',
    ];

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(TrainingQuiz::class, 'training_quiz_id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(TrainingAnswer::class);
    }

    public function pointsValue(?TrainingQuiz $quiz = null): int
    {
        return (int) ($this->points ?: ($quiz ?? $this->quiz)?->default_points ?: 1000);
    }

    public function secondsValue(?TrainingQuiz $quiz = null): int
    {
        return (int) ($this->seconds ?: ($quiz ?? $this->quiz)?->default_seconds ?: 20);
    }

    /**
     * Is this set of chosen indexes right?
     *
     * Order-insensitive and duplicate-insensitive on purpose: "choose all that
     * apply" is a set, and a player who taps B then A has not answered a
     * different question from one who tapped A then B. An empty choice — which
     * is what running out of time records — is never correct, including for a
     * question that somehow has no correct answer, which is why the emptiness
     * check comes before the comparison.
     *
     * @param  array<int, int|string>  $chosen
     */
    public function isCorrect(array $chosen): bool
    {
        $given = array_values(array_unique(array_map('intval', $chosen)));
        $want  = array_values(array_unique(array_map('intval', (array) $this->correct)));

        if ($given === [] || $want === []) {
            return false;
        }

        sort($given);
        sort($want);

        return $given === $want;
    }

    /** @return array<int, string> */
    public function optionList(): array
    {
        return array_values(array_map('strval', (array) $this->options));
    }

    public function isMultiSelect(): bool
    {
        return $this->type === 'multi';
    }

    // ── Languages ─────────────────────────────────────────────────────────

    public function translations(): HasMany
    {
        return $this->hasMany(TrainingQuestionTranslation::class);
    }

    /**
     * The translation for a language, or null for the original.
     *
     * Null in, null out — the caller does not have to know whether a language
     * was chosen. Reads from an already-loaded relation when there is one, so
     * rendering a question does not cost a query per language lookup.
     */
    public function translation(?string $language): ?TrainingQuestionTranslation
    {
        if (! $language || $language === ($this->quiz?->language ?? 'en')) {
            return null;
        }

        if ($this->relationLoaded('translations')) {
            return $this->translations->firstWhere('language', $language);
        }

        return $this->translations()->where('language', $language)->first();
    }

    public function promptIn(?string $language): string
    {
        return $this->translation($language)?->prompt ?: $this->prompt;
    }

    /**
     * The options in a language, INDEX FOR INDEX with the original.
     *
     * A translation with a different number of options is refused rather than
     * padded: the answer key is a list of indexes into this array, so a
     * mismatch does not degrade the reading experience, it marks the wrong
     * option correct. Falling back to the original is the only safe answer, and
     * the writer refuses to store one that would land here.
     *
     * @return array<int, string>
     */
    public function optionListIn(?string $language): array
    {
        $translated = $this->translation($language)?->optionList();
        $original   = $this->optionList();

        return $translated && count($translated) === count($original)
            ? $translated
            : $original;
    }

    public function explanationIn(?string $language): ?string
    {
        return $this->translation($language)?->explanation ?: $this->explanation;
    }
}
