<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What somebody chose, how long it took, and what it was worth.
 *
 * `seconds_allowed` is frozen here rather than read back off the question, so
 * editing a question's time limit later cannot rewrite what anybody scored.
 */
class TrainingAnswer extends Model
{
    protected $fillable = [
        'training_attempt_id', 'training_question_id', 'chosen',
        'is_correct', 'points_awarded', 'seconds_taken', 'seconds_allowed', 'language',
    ];

    protected $casts = [
        'chosen'          => 'array',
        'is_correct'      => 'boolean',
        'points_awarded'  => 'integer',
        'seconds_taken'   => 'decimal:2',
        'seconds_allowed' => 'integer',
    ];

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(TrainingAttempt::class, 'training_attempt_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(TrainingQuestion::class, 'training_question_id');
    }

    /** Nothing chosen — the clock ran out. */
    public function timedOut(): bool
    {
        return empty($this->chosen);
    }
}
