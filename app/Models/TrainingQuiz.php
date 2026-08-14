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

    /**
     * What the questions are asked in — see the migration for why `ms` and `id`
     * are separate rather than one Malay option.
     */
    public const LANGUAGES = [
        'en' => 'English',
        'ms' => 'Bahasa Malaysia',
        'id' => 'Bahasa Indonesia',
    ];

    /**
     * True/false wording per language.
     *
     * Here rather than in the question editor because BOTH sides need it and
     * they must agree: the editor fills these in when somebody picks the
     * true/false type, and the AI is told to use exactly these words. Two
     * copies would drift, and a quiz with "True/False" in an otherwise Malay
     * paper is the kind of thing staff notice and authors do not.
     */
    public const BOOLEAN_OPTIONS = [
        'en' => ['True', 'False'],
        'ms' => ['Betul', 'Salah'],
        'id' => ['Benar', 'Salah'],
    ];

    /** @return array<int, string> */
    public static function booleanOptionsFor(?string $language): array
    {
        return self::BOOLEAN_OPTIONS[$language] ?? self::BOOLEAN_OPTIONS['en'];
    }

    public function languageLabel(): string
    {
        return self::LANGUAGES[$this->language] ?? self::LANGUAGES['en'];
    }

    protected $fillable = [
        'company_id', 'training_course_id', 'section_id', 'title', 'description', 'language', 'status',
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
     *
     * The shuffle pair matters for a quieter reason than the scoring pair: a
     * null reads as false, so a quiz hosted in the same request it was created
     * in would silently deal every player the same order — LiveSessionService
     * asks `if ($quiz->shuffle_questions)` and would never have been told.
     * Nothing errors, and nobody finds out.
     */
    protected $attributes = [
        'status'             => 'draft',
        'language'           => 'en',
        'pass_mark'          => 70,
        'default_seconds'    => 20,
        'default_points'     => 1000,
        'speed_bonus'        => true,
        'streak_bonus'       => true,
        'shuffle_questions'  => true,
        'shuffle_options'    => true,
        'max_attempts'       => 0,
        'issues_certificate' => false,
        'generated_by_ai'    => false,
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

    /** Front of house, back of house, or null for everybody. */
    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function sectionLabel(): string
    {
        return $this->section?->name ?? 'Everyone';
    }

    /**
     * Quizzes this person should be offered.
     *
     * Untagged quizzes are included for EVERY section, not excluded from all of
     * them — null means "everybody" here, so a compliance quiz nobody
     * remembered to tag still reaches the whole floor. Failing the other way
     * would hide safety material from people and look like nothing was wrong.
     *
     * Somebody with no section at all (none on this company today, but the
     * column is nullable) sees only the untagged ones, which is the same rule
     * read from the other side.
     */
    public function scopeForSection(Builder $query, ?int $sectionId): Builder
    {
        return $query->where(function ($q) use ($sectionId) {
            $q->whereNull('section_id');

            if ($sectionId) {
                $q->orWhere('section_id', $sectionId);
            }
        });
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
    public function attemptsRemaining(?int $employeeId): ?int
    {
        if ($this->max_attempts <= 0 || ! $employeeId) {
            return null; // unlimited
        }

        $used = $this->attempts()
            ->where('employee_id', $employeeId)
            ->where('mode', 'self')
            ->whereNotNull('completed_at')
            ->count();

        return max(0, $this->max_attempts - $used);
    }
}
