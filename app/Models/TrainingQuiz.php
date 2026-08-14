<?php

namespace App\Models;

use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A set of questions about a course, and the rules for playing them.
 *
 * The quiz owns the defaults; a question may override points and seconds. That
 * split is what lets an author raise the stakes on the one question that
 * matters without hand-setting the other nine.
 */
class TrainingQuiz extends Model
{
    use SoftDeletes;

    protected $table = 'training_quizzes';

    public const STATUSES = [
        'draft'     => 'Draft',
        'published' => 'Published',
        'archived'  => 'Archived',
    ];

    protected $fillable = [
        'company_id', 'training_course_id', 'title', 'description', 'status',
        'pass_mark', 'default_seconds', 'default_points',
        'speed_bonus', 'streak_bonus', 'shuffle_questions', 'shuffle_options',
        'max_attempts', 'issues_certificate',
        'generated_by_ai', 'ai_model', 'generated_at', 'created_by',
    ];

    protected $casts = [
        'pass_mark'          => 'integer',
        'default_seconds'    => 'integer',
        'default_points'     => 'integer',
        'max_attempts'       => 'integer',
        'speed_bonus'        => 'boolean',
        'streak_bonus'       => 'boolean',
        'shuffle_questions'  => 'boolean',
        'shuffle_options'    => 'boolean',
        'issues_certificate' => 'boolean',
        'generated_by_ai'    => 'boolean',
        'generated_at'       => 'datetime',
    ];

    /**
     * Mirrors the column defaults. Eloquent does not read DB-side defaults back
     * after an insert, so without these a just-created quiz scores every answer
     * with a null speed bonus until it is reloaded.
     */
    protected $attributes = [
        'pass_mark'       => 70,
        'default_seconds' => 20,
        'default_points'  => 1000,
        'speed_bonus'     => true,
        'streak_bonus'    => true,
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope());
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(TrainingCourse::class, 'training_course_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(TrainingQuestion::class)->orderBy('sort_order')->orderBy('id');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(TrainingAttempt::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(TrainingSession::class);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published');
    }

    /** Every point on offer, which is what a live score is read against. */
    public function maxScore(): int
    {
        return (int) $this->questions->sum(fn (TrainingQuestion $q) => $q->pointsValue($this));
    }

    /**
     * Whether this trainee has any attempts left.
     *
     * Live sessions do not consume the allowance — the host decides who is in
     * the room, and a staff member should not be locked out of the shift
     * briefing because they practised twice at home.
     */
    public function attemptsRemaining(?int $lmsUserId): ?int
    {
        if ($this->max_attempts <= 0 || ! $lmsUserId) {
            return null; // unlimited
        }

        $used = $this->attempts()
            ->where('lms_user_id', $lmsUserId)
            ->where('mode', 'self')
            ->whereNotNull('completed_at')
            ->count();

        return max(0, $this->max_attempts - $used);
    }
}
