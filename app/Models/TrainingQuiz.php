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
        'speed_bonus', 'streak_bonus', 'wrong_penalty_percent', 'music_url', 'share_token',
        'shuffle_questions', 'shuffle_options',
        'max_attempts', 'issues_certificate',
        'generated_by_ai', 'ai_model', 'generated_at', 'created_by',
    ];

    protected $casts = [
        'pass_mark'             => 'integer',
        'default_seconds'       => 'integer',
        'default_points'        => 'integer',
        'max_attempts'          => 'integer',
        'wrong_penalty_percent' => 'integer',
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
        'status'                => 'draft',
        'language'              => 'en',
        'pass_mark'             => 70,
        'default_seconds'       => 20,
        'default_points'        => 1000,
        'speed_bonus'           => true,
        'streak_bonus'          => true,
        'wrong_penalty_percent' => 0,
        'shuffle_questions'     => true,
        'shuffle_options'       => true,
        'max_attempts'          => 0,
        'issues_certificate'    => false,
        'generated_by_ai'       => false,
    ];

    /**
     * The token in this quiz's public link, minted if it has none.
     *
     * Lazily rather than in a creating() hook so a quiz that predates the
     * column — or one restored from a backup taken before it — still answers
     * the question. The write is a single indexed update and happens once in
     * the life of a quiz.
     */
    public function shareToken(): string
    {
        if (! $this->share_token) {
            $this->forceFill(['share_token' => \Illuminate\Support\Str::random(16)])->save();
        }

        return $this->share_token;
    }

    /**
     * The URL to print on a poster.
     *
     * Built by hand rather than with route(), for the same reason
     * LabelQrService does it: this is generated in the MANAGER app, on the main
     * domain, where the staff subdomain's route defaults are not bound. route()
     * would produce a main-domain address that sends staff to a sign-in they
     * cannot use.
     */
    public function shareUrl(): string
    {
        $token  = $this->shareToken();
        $domain = config('app.domain');
        $slug   = $this->company?->slug ?? Company::find($this->company_id)?->slug;

        if ($domain && $slug) {
            return 'https://' . $slug . '.' . $domain . '/staff/q/' . $token;
        }

        // Local dev, where the staff app lives on a path instead.
        return url('/staff/q/' . $token);
    }

    /**
     * The YouTube embed to play behind this quiz, or null.
     *
     * REBUILT FROM AN ID RATHER THAN PASSED THROUGH. `music_url` is typed into
     * a form by a merchant, and an iframe src is one of the few places in a
     * Blade template where a string becomes executable — a `javascript:` URL or
     * somebody else's page in a frame is a real answer to "what did you put in
     * the box". So only the id is taken, only if it matches YouTube's own
     * character set, and the URL around it is written here.
     *
     * youtube-nocookie.com for the same reason the consent banners exist: this
     * plays on a staff phone that never agreed to anything.
     */
    public function musicEmbedUrl(): ?string
    {
        $raw = trim((string) $this->music_url);

        if ($raw === '') {
            return null;
        }

        $id       = null;
        $playlist = null;

        if (preg_match('~[?&]list=([A-Za-z0-9_-]{12,})~', $raw, $m)) {
            $playlist = $m[1];
        }

        if (preg_match('~(?:youtu\.be/|v=|/embed/|/shorts/)([A-Za-z0-9_-]{11})~', $raw, $m)) {
            $id = $m[1];
        } elseif (preg_match('~^[A-Za-z0-9_-]{11}$~', $raw)) {
            // Somebody pasted the id on its own, which is a reasonable thing to do.
            $id = $raw;
        }

        $params = [
            'autoplay'       => 1,
            'controls'       => 0,
            'modestbranding' => 1,
            'playsinline'    => 1,
            'enablejsapi'    => 1,
        ];

        if ($playlist) {
            $params['list'] = $playlist;
            $params['loop'] = 1;

            return 'https://www.youtube-nocookie.com/embed/' . ($id ?? 'videoseries')
                . '?' . http_build_query($params);
        }

        if (! $id) {
            return null;
        }

        // A single video loops only when it is also named as the playlist —
        // YouTube's own quirk, and without it the room falls silent after one
        // track and nobody knows why.
        $params['loop']     = 1;
        $params['playlist'] = $id;

        return 'https://www.youtube-nocookie.com/embed/' . $id . '?' . http_build_query($params);
    }

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
