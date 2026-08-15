<?php

namespace App\Models;

use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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

    /**
     * The word that sweeps across the screen after an answer.
     *
     * Here beside the true/false wording and for the same reason: it is shown
     * in whatever the trainee chose to read the paper in, and a Malay quiz that
     * shouts "CORRECT" at somebody is a quiz that was translated by halves.
     *
     * The wrong-answer word is deliberately not a scolding. "Belum tepat" is
     * "not right yet", and the English matches it — this lands in front of
     * colleagues, on a screen somebody's manager can see, and a training tool
     * that makes being wrong feel humiliating teaches people to stop playing.
     *
     * @return array{0: string, 1: string} [correct, wrong]
     */
    public static function verdictWordsFor(?string $language): array
    {
        return match ($language) {
            'ms'    => ['Betul!', 'Belum tepat'],
            'id'    => ['Benar!', 'Belum tepat'],
            default => ['Correct!', 'Not quite'],
        };
    }

    public function languageLabel(): string
    {
        return self::LANGUAGES[$this->language] ?? self::LANGUAGES['en'];
    }

    protected $fillable = [
        'company_id', 'training_course_id', 'title', 'description', 'language', 'status',
        'pass_mark', 'default_seconds', 'default_points',
        'speed_bonus', 'streak_bonus', 'wrong_penalty_percent', 'auto_advance_seconds',
        'music_url', 'music_path', 'share_token',
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
        'auto_advance_seconds'  => 'integer',
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
        'auto_advance_seconds'  => 0,
        'shuffle_questions'     => true,
        'shuffle_options'       => true,
        'max_attempts'          => 0,
        'issues_certificate'    => false,
        'generated_by_ai'       => false,
    ];

    // ── Languages ─────────────────────────────────────────────────────────

    /**
     * How many of this quiz's questions exist in each language.
     *
     * @return array<string, int> language => questions translated
     */
    public function translationCoverage(): array
    {
        return TrainingQuestionTranslation::query()
            ->whereIn('training_question_id', $this->questions()->select('id'))
            ->selectRaw('language, count(*) as n')
            ->groupBy('language')
            ->pluck('n', 'language')
            ->map(fn ($n) => (int) $n)
            ->all();
    }

    /**
     * The languages a trainee may choose, the quiz's own first.
     *
     * A LANGUAGE IS ONLY OFFERED WHEN EVERY QUESTION HAS IT. Half a paper in
     * Malay is worse than none: somebody who picked Malay because they read
     * Malay would be dropped into English at question five, at speed, for
     * points, having already committed to the attempt. A partial translation is
     * a thing for the author to finish, not for a trainee to discover.
     *
     * @return array<string, string> language key => label
     */
    public function availableLanguages(): array
    {
        $own   = $this->language ?: 'en';
        $total = $this->questions()->count();

        $languages = [$own => self::LANGUAGES[$own] ?? $own];

        if ($total === 0) {
            return $languages;
        }

        foreach ($this->translationCoverage() as $language => $done) {
            if ($done >= $total && $language !== $own && isset(self::LANGUAGES[$language])) {
                $languages[$language] = self::LANGUAGES[$language];
            }
        }

        return $languages;
    }

    /** Whether a trainee is offered a choice at all. */
    public function isMultilingual(): bool
    {
        return count($this->availableLanguages()) > 1;
    }

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
     * The uploaded backing track, as a URL the browser can play.
     *
     * PREFERRED OVER THE YOUTUBE LINK wherever both exist, because a native
     * <audio> element lives in the same document as the Start button and a user
     * gesture belongs to the frame it happened in. That is the whole reason the
     * YouTube embed cannot autoplay on an iPhone and this can.
     */
    /** Audio a browser can hand to an <audio> element without an iframe. */
    public const PLAYABLE_AUDIO = ['mp3', 'm4a', 'aac', 'ogg', 'oga', 'wav'];

    /**
     * The track, as something an <audio> element can play.
     *
     * An uploaded file first — and then, if the link field holds a DIRECT AUDIO
     * URL rather than a YouTube page, that. The distinction is the whole
     * difference between music that plays on an iPhone and music that does not:
     * an audio URL becomes a media element in this document, where the tap on
     * Start authorises it, while a YouTube link can only ever become a
     * cross-origin iframe that the gesture cannot reach into.
     *
     * Somebody who already has the file hosted somewhere — a CDN, their own
     * site, a shared drive with a direct link — should not have to download and
     * re-upload it to get sound on the floor's phones.
     */
    public function musicFileUrl(): ?string
    {
        if ($this->music_path) {
            return \Illuminate\Support\Facades\Storage::disk('public')->url($this->music_path);
        }

        $link = trim((string) $this->music_url);

        if ($link === '' || ! str_starts_with($link, 'https://')) {
            return null;
        }

        $extension = strtolower(pathinfo(parse_url($link, PHP_URL_PATH) ?: '', PATHINFO_EXTENSION));

        return in_array($extension, self::PLAYABLE_AUDIO, true) ? $link : null;
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

    /**
     * The sections this quiz is for. Empty means everybody.
     *
     * A pivot rather than a column, because the real audiences are not single
     * sections: a menu paper is for FOH and Bar but not the kitchen, an
     * allergen paper for the kitchen and FOH but not the office. With one
     * column those get written twice, and two papers drift.
     */
    public function sections(): BelongsToMany
    {
        return $this->belongsToMany(Section::class, 'training_quiz_sections')->orderBy('name');
    }

    /** The outlets this quiz is for. Empty means all of them. */
    public function outlets(): BelongsToMany
    {
        return $this->belongsToMany(Outlet::class, 'training_quiz_outlets')->orderBy('name');
    }

    public function sectionLabel(): string
    {
        $names = $this->sections->pluck('name');

        return $names->isEmpty() ? 'Everyone' : $names->join(' · ');
    }

    public function outletLabel(): string
    {
        $names = $this->outlets->pluck('name');

        return $names->isEmpty() ? 'All outlets' : $names->join(' · ');
    }

    /**
     * Quizzes this person should be offered.
     *
     * EMPTY MEANS EVERYBODY, on both sides. A quiz nobody remembered to tag
     * still reaches the whole floor, which is the forgiving direction to fail:
     * hiding safety material from people looks like nothing is wrong at all.
     *
     * The two narrow EACH OTHER. "The kitchen at Bangsar" is the commonest real
     * audience and neither expresses it alone, so a quiz tagged with both is
     * offered only where both match — the same rule TrainingAssignment already
     * applies to the same pair.
     *
     * Somebody with no section, or posted to no outlet, sees the quizzes that
     * name none — the same rule read from the other side.
     *
     * @param  array<int, int>|int|null  $outletIds
     */
    public function scopeForAudience(Builder $query, ?int $sectionId, $outletIds = null): Builder
    {
        $outletIds = array_values(array_filter(array_map('intval', (array) $outletIds)));

        return $query
            ->where(fn ($q) => $q
                ->whereDoesntHave('sections')
                ->when($sectionId, fn ($m) => $m->orWhereHas(
                    'sections',
                    fn ($s) => $s->where('sections.id', $sectionId),
                )))
            ->where(fn ($q) => $q
                ->whereDoesntHave('outlets')
                ->when($outletIds !== [], fn ($m) => $m->orWhereHas(
                    'outlets',
                    fn ($o) => $o->whereIn('outlets.id', $outletIds),
                )));
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
