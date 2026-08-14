<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The same question, in another language.
 *
 * No company scope and no soft deletes: it has no life of its own. It belongs
 * to a question, it dies with the question, and the question is already scoped.
 *
 * It carries WORDS ONLY. Points, seconds, type, difficulty and — above all —
 * the answer key stay on the question, so a translation cannot disagree with
 * the original about what the right answer is. See the migration note.
 */
class TrainingQuestionTranslation extends Model
{
    protected $fillable = [
        'training_question_id', 'language', 'prompt', 'options', 'explanation', 'machine_translated',
    ];

    protected $casts = [
        'options'            => 'array',
        'machine_translated' => 'boolean',
    ];

    protected $attributes = [
        'machine_translated' => true,
    ];

    public function question(): BelongsTo
    {
        return $this->belongsTo(TrainingQuestion::class, 'training_question_id');
    }

    /** @return array<int, string> */
    public function optionList(): array
    {
        return array_values(array_map('strval', (array) $this->options));
    }
}
