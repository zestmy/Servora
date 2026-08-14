<?php

namespace App\Models;

use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Proof somebody passed something.
 *
 * The recipient's name and the course title are FROZEN onto the row. A
 * certificate is a statement about a moment: renaming the course next year, or
 * a trainee changing their name, must not retroactively alter what was
 * awarded. The relations are still here, for "show me everything issued
 * against this course" — they are just not what gets printed.
 */
class TrainingCertificate extends Model
{
    protected $fillable = [
        'company_id', 'lms_user_id', 'training_course_id', 'training_quiz_id',
        'training_attempt_id', 'serial', 'recipient_name', 'title',
        'percent', 'issued_at', 'expires_on', 'revoked_at',
    ];

    protected $casts = [
        'percent'    => 'decimal:2',
        'issued_at'  => 'datetime',
        'expires_on' => 'date',
        'revoked_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope());
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function trainee(): BelongsTo
    {
        return $this->belongsTo(LmsUser::class, 'lms_user_id');
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(TrainingCourse::class, 'training_course_id');
    }

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(TrainingQuiz::class, 'training_quiz_id');
    }

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(TrainingAttempt::class, 'training_attempt_id');
    }

    public function scopeValid(Builder $query): Builder
    {
        return $query->whereNull('revoked_at')
            ->where(function ($q) {
                $q->whereNull('expires_on')->orWhereDate('expires_on', '>=', now()->toDateString());
            });
    }

    public function scopeExpiringWithin(Builder $query, int $days): Builder
    {
        return $query->whereNull('revoked_at')
            ->whereNotNull('expires_on')
            ->whereDate('expires_on', '>=', now()->toDateString())
            ->whereDate('expires_on', '<=', now()->addDays($days)->toDateString());
    }

    public function isExpired(): bool
    {
        return $this->expires_on !== null && $this->expires_on->isPast();
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function statusLabel(): string
    {
        return match (true) {
            $this->isRevoked() => 'Revoked',
            $this->isExpired() => 'Expired',
            default            => 'Valid',
        };
    }
}
