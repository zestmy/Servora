<?php

namespace App\Models;

use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A live room.
 *
 * The row is the shared clock — see the migration note. Everything a player's
 * screen needs to decide what to draw comes from `status`, `current_index` and
 * `question_started_at`, and nothing else, so a phone that missed three polls
 * catches up correctly rather than resuming where it left off.
 */
class TrainingSession extends Model
{
    public const STATUSES = [
        'lobby'    => 'Waiting for players',
        'question' => 'Question live',
        'reveal'   => 'Showing the answer',
        'ended'    => 'Finished',
    ];

    /**
     * Grace added to every question's own limit before the server stops
     * accepting an answer.
     *
     * A player on a bad kitchen wifi connection can have their tap in flight
     * when the clock hits zero. Rejecting it would score them zero for a
     * question they answered in time, and the poll interval alone can eat a
     * second — so the server is a little more generous than the countdown
     * everybody is watching. It does not affect the score: points are computed
     * off the real elapsed seconds, so a late-but-accepted answer is simply
     * worth very little.
     */
    public const ANSWER_GRACE_SECONDS = 2;

    /** A room a host drives, or a window staff take in their own time. */
    public const MODE_LIVE      = 'live';
    public const MODE_SCHEDULED = 'scheduled';

    protected $fillable = [
        'company_id', 'training_quiz_id', 'outlet_id', 'host_user_id',
        'pin', 'name', 'mode', 'opens_at', 'closes_at',
        'status', 'current_index', 'question_started_at',
        'question_order', 'started_at', 'ended_at',
    ];

    protected $casts = [
        'current_index'       => 'integer',
        'question_order'      => 'array',
        'question_started_at' => 'datetime',
        'opens_at'            => 'datetime',
        'closes_at'           => 'datetime',
        'started_at'          => 'datetime',
        'ended_at'            => 'datetime',
    ];

    protected $attributes = [
        'mode'          => self::MODE_LIVE,
        'status'        => 'lobby',
        'current_index' => 0,
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

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    public function host(): BelongsTo
    {
        return $this->belongsTo(User::class, 'host_user_id');
    }

    public function players(): HasMany
    {
        return $this->hasMany(TrainingSessionPlayer::class)->orderByDesc('score')->orderBy('id');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(TrainingAttempt::class);
    }

    public function scopeLive(Builder $query): Builder
    {
        return $query->where('mode', self::MODE_LIVE)
            ->whereIn('status', ['lobby', 'question', 'reveal']);
    }

    public function isScheduled(): bool
    {
        return $this->mode === self::MODE_SCHEDULED;
    }

    /**
     * Challenges taking answers right now.
     *
     * A null `opens_at` means "already open" and a null `closes_at` means "no
     * end", so a challenge created with neither is simply on until somebody
     * ends it. That is the forgiving reading, and the alternative — treating a
     * missing bound as closed — would make an incompletely-filled form produce
     * a challenge that silently reaches nobody.
     */
    public function scopeOpenNow(Builder $query): Builder
    {
        return $query->where('mode', self::MODE_SCHEDULED)
            ->where('status', '!=', 'ended')
            ->where(fn ($q) => $q->whereNull('opens_at')->orWhere('opens_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('closes_at')->orWhere('closes_at', '>=', now()));
    }

    public function isOpen(): bool
    {
        return $this->isScheduled()
            && $this->status !== 'ended'
            && ($this->opens_at === null || $this->opens_at->isPast())
            && ($this->closes_at === null || $this->closes_at->isFuture());
    }

    public function hasClosed(): bool
    {
        return $this->isScheduled()
            && ($this->status === 'ended'
                || ($this->closes_at !== null && $this->closes_at->isPast()));
    }

    /** "Closes in 3 days", or why it is not open. */
    public function windowLabel(): string
    {
        return match (true) {
            $this->status === 'ended'                                  => 'Ended',
            $this->closes_at?->isPast() ?? false                       => 'Closed ' . $this->closes_at->diffForHumans(),
            ($this->opens_at?->isFuture() ?? false)                    => 'Opens ' . $this->opens_at->diffForHumans(),
            $this->closes_at !== null                                  => 'Closes ' . $this->closes_at->diffForHumans(),
            default                                                    => 'Open',
        };
    }

    /**
     * A room number that is not already in use.
     *
     * Only live rooms are checked, because a PIN is a number shouted across a
     * kitchen rather than a credential — see the migration note. Falls back to
     * a longer number rather than looping forever in the (impossible in
     * practice, embarrassing in production) case where every six-digit PIN is
     * taken.
     */
    public static function generatePin(): string
    {
        for ($i = 0; $i < 40; $i++) {
            $pin = (string) random_int(100000, 999999);

            $taken = static::withoutGlobalScope(CompanyScope::class)
                ->where('pin', $pin)->live()->exists();

            if (! $taken) {
                return $pin;
            }
        }

        return (string) random_int(1000000, 9999999);
    }

    /** The question ids this room plays, in order. */
    public function orderedQuestionIds(): array
    {
        return array_map('intval', (array) ($this->question_order ?? []));
    }

    public function currentQuestion(): ?TrainingQuestion
    {
        $ids = $this->orderedQuestionIds();
        $id  = $ids[$this->current_index] ?? null;

        return $id ? TrainingQuestion::find($id) : null;
    }

    public function questionCount(): int
    {
        return count($this->orderedQuestionIds());
    }

    public function isLastQuestion(): bool
    {
        return $this->current_index >= $this->questionCount() - 1;
    }

    /**
     * Seconds left on the current question, from the SERVER's stamp.
     *
     * Never negative, so a template can divide by it, and zero once the window
     * has closed — which is the signal the host console uses to auto-reveal.
     */
    public function secondsRemaining(?TrainingQuestion $question = null): int
    {
        if ($this->status !== 'question' || ! $this->question_started_at) {
            return 0;
        }

        $question ??= $this->currentQuestion();

        if (! $question) {
            return 0;
        }

        $limit   = $question->secondsValue($this->quiz);
        $elapsed = $this->question_started_at->diffInSeconds(Carbon::now());

        return (int) max(0, $limit - $elapsed);
    }

    /** Whether an answer arriving now is still inside the window. */
    public function acceptsAnswers(?TrainingQuestion $question = null): bool
    {
        if ($this->status !== 'question' || ! $this->question_started_at) {
            return false;
        }

        $question ??= $this->currentQuestion();

        if (! $question) {
            return false;
        }

        $elapsed = $this->question_started_at->diffInSeconds(Carbon::now());

        return $elapsed <= $question->secondsValue($this->quiz) + self::ANSWER_GRACE_SECONDS;
    }
}
