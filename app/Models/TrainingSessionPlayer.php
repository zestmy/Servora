<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Somebody in the room.
 *
 * Reachable only through its session, which is company-scoped, so no scope of
 * its own — same reasoning as TrainingQuestion.
 */
class TrainingSessionPlayer extends Model
{
    protected $fillable = [
        'training_session_id', 'employee_id', 'nickname',
        'score', 'streak', 'best_streak', 'correct_count', 'answered_count',
        'joined_at', 'last_seen_at',
    ];

    protected $casts = [
        'score'          => 'integer',
        'streak'         => 'integer',
        'best_streak'    => 'integer',
        'correct_count'  => 'integer',
        'answered_count' => 'integer',
        'joined_at'      => 'datetime',
        'last_seen_at'   => 'datetime',
    ];

    /**
     * Mirrors the column defaults, and this is load-bearing rather than tidy.
     *
     * Eloquent does not read DB-side defaults back after an insert, so a
     * just-created player has NULL counters in memory until it is reloaded —
     * and the first thing that happens to a player is that their first answer
     * is scored against those counters. `max(null, 0)` returns null in PHP,
     * because null and 0 compare equal and max() keeps the first, so a player
     * whose opening answer was WRONG wrote a null best_streak straight back
     * into a NOT NULL column. Same trap LabelSet documents.
     */
    protected $attributes = [
        'score'          => 0,
        'streak'         => 0,
        'best_streak'    => 0,
        'correct_count'  => 0,
        'answered_count' => 0,
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(TrainingSession::class, 'training_session_id');
    }

    /** Null for a nickname-only player — see the migration note on the table. */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function attempt(): HasOne
    {
        return $this->hasOne(TrainingAttempt::class, 'training_session_player_id');
    }

    /**
     * Seen within the last half-minute.
     *
     * Players poll every second or two, so half a minute is many missed polls
     * — long enough that a phone locking itself for a moment does not drop
     * somebody off the lobby list mid-game.
     */
    public function isActive(): bool
    {
        return $this->last_seen_at !== null && $this->last_seen_at->gt(now()->subSeconds(30));
    }
}
