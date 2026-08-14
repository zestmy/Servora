<?php

namespace App\Models;

use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * One run at a quiz, live or self-paced.
 *
 * `percent` is percent CORRECT rather than percent of points — see the
 * migration note. The score is the game; the percent is the record.
 */
class TrainingAttempt extends Model
{
    protected $fillable = [
        'company_id', 'training_quiz_id', 'lms_user_id', 'outlet_id',
        'training_session_id', 'training_session_player_id', 'mode',
        'started_at', 'completed_at', 'score', 'max_score',
        'correct_count', 'question_count', 'percent', 'passed', 'question_order',
    ];

    protected $casts = [
        'started_at'     => 'datetime',
        'completed_at'   => 'datetime',
        'score'          => 'integer',
        'max_score'      => 'integer',
        'correct_count'  => 'integer',
        'question_count' => 'integer',
        'percent'        => 'decimal:2',
        'passed'         => 'boolean',
        'question_order' => 'array',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope());
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(TrainingQuiz::class, 'training_quiz_id');
    }

    public function trainee(): BelongsTo
    {
        return $this->belongsTo(LmsUser::class, 'lms_user_id');
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(TrainingSession::class, 'training_session_id');
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(TrainingSessionPlayer::class, 'training_session_player_id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(TrainingAnswer::class);
    }

    public function certificate(): HasOne
    {
        return $this->hasOne(TrainingCertificate::class, 'training_attempt_id');
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->whereNotNull('completed_at');
    }

    /** Whole seconds spent answering, which is not the same as wall time. */
    public function secondsSpent(): int
    {
        return (int) round($this->answers->sum('seconds_taken'));
    }
}
