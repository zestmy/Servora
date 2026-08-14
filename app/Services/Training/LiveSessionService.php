<?php

namespace App\Services\Training;

use App\Models\LmsUser;
use App\Models\TrainingAnswer;
use App\Models\TrainingAttempt;
use App\Models\TrainingQuestion;
use App\Models\TrainingQuiz;
use App\Models\TrainingSession;
use App\Models\TrainingSessionPlayer;
use App\Scopes\CompanyScope;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The live room's state machine.
 *
 * Every transition writes to the session row, because the session row is the
 * shared clock every phone in the room reads — see the migration note. Nothing
 * here consults the caller's idea of what question is live; the host console
 * asks for "next" and the server decides what that means, so two taps on a
 * laggy host screen advance one question, not two.
 */
class LiveSessionService
{
    public function __construct(
        private ScoringService $scoring,
        private CertificateService $certificates,
    ) {
    }

    // ── Hosting ───────────────────────────────────────────────────────────

    /**
     * Open a room.
     *
     * The question order is resolved NOW rather than at first question, so a
     * player who joins during question three still sees the same sequence as
     * everybody else — see the migration note.
     */
    public function open(TrainingQuiz $quiz, ?int $outletId, ?int $hostUserId, ?string $name = null): TrainingSession
    {
        $quiz->loadMissing('questions');

        if ($quiz->questions->isEmpty()) {
            throw new \RuntimeException('This quiz has no questions yet — add or generate some before hosting it.');
        }

        $ids = $quiz->questions->pluck('id')->map(fn ($id) => (int) $id);

        if ($quiz->shuffle_questions) {
            $ids = $ids->shuffle();
        }

        return TrainingSession::create([
            'company_id'       => $quiz->company_id,
            'training_quiz_id' => $quiz->id,
            'outlet_id'        => $outletId,
            'host_user_id'     => $hostUserId,
            'pin'              => TrainingSession::generatePin(),
            'name'             => $name,
            'status'           => 'lobby',
            'current_index'    => 0,
            'question_order'   => $ids->values()->all(),
        ]);
    }

    /** Start the first question. */
    public function start(TrainingSession $session): TrainingSession
    {
        return $this->openQuestion($session, 0, markStarted: true);
    }

    /**
     * Stop taking answers and show the result.
     *
     * Players who never answered get a zero-point, zero-choice row so the
     * per-question breakdown counts them as having been asked. Without it, a
     * question everybody skipped looks like a question nobody was asked.
     */
    public function reveal(TrainingSession $session): TrainingSession
    {
        if ($session->status !== 'question') {
            return $session;
        }

        $question = $session->currentQuestion();

        if ($question) {
            $this->recordNonAnswers($session, $question);
        }

        $session->forceFill(['status' => 'reveal'])->save();

        return $session;
    }

    /**
     * Move to the next question, or finish if there isn't one.
     *
     * Reveals first when the host skips straight from a live question, so the
     * timed-out rows are still written.
     */
    public function next(TrainingSession $session): TrainingSession
    {
        if ($session->status === 'question') {
            $this->reveal($session);
        }

        if ($session->isLastQuestion()) {
            return $this->end($session);
        }

        return $this->openQuestion($session, $session->current_index + 1);
    }

    /** Close the room and write everybody's attempt. */
    public function end(TrainingSession $session): TrainingSession
    {
        if ($session->status === 'question') {
            $this->reveal($session);
        }

        DB::transaction(function () use ($session) {
            $session->forceFill([
                'status'   => 'ended',
                'ended_at' => $session->ended_at ?? now(),
            ])->save();

            $quiz = $session->quiz;

            foreach ($session->players()->with('attempt')->get() as $player) {
                $attempt = $player->attempt;

                if (! $attempt) {
                    continue;
                }

                $attempt->forceFill(['question_count' => $session->questionCount()])->save();
                $this->scoring->finalise($attempt->fresh('answers'), $quiz);
                $this->certificates->issueFor($attempt->fresh(['quiz.course', 'trainee']));
            }
        });

        return $session;
    }

    private function openQuestion(TrainingSession $session, int $index, bool $markStarted = false): TrainingSession
    {
        $session->forceFill([
            'status'              => 'question',
            'current_index'       => $index,
            'question_started_at' => now(),
        ] + ($markStarted ? ['started_at' => $session->started_at ?? now()] : []))->save();

        return $session;
    }

    /**
     * A zero row for everyone who did not answer the question that just closed.
     *
     * insertOrIgnore against the unique (attempt, question) index rather than a
     * read-then-write: the host revealing and a player's last-moment answer can
     * land in the same instant, and the index is the only thing that can settle
     * that race correctly. The player who answered keeps their row.
     */
    private function recordNonAnswers(TrainingSession $session, TrainingQuestion $question): void
    {
        $attempts = TrainingAttempt::withoutGlobalScope(CompanyScope::class)
            ->where('training_session_id', $session->id)
            ->pluck('id');

        if ($attempts->isEmpty()) {
            return;
        }

        $now  = now();
        $rows = $attempts->map(fn ($attemptId) => [
            'training_attempt_id'  => $attemptId,
            'training_question_id' => $question->id,
            'chosen'               => '[]',
            'is_correct'           => false,
            'points_awarded'       => 0,
            'seconds_taken'        => $question->secondsValue($session->quiz),
            'seconds_allowed'      => $question->secondsValue($session->quiz),
            'created_at'           => $now,
            'updated_at'           => $now,
        ])->all();

        foreach (array_chunk($rows, 200) as $chunk) {
            DB::table('training_answers')->insertOrIgnore($chunk);
        }

        // The scoreboard has to agree with the rows: a player who missed the
        // question has broken their streak.
        TrainingSessionPlayer::where('training_session_id', $session->id)
            ->whereRaw('answered_count < ?', [$session->current_index + 1])
            ->update(['streak' => 0, 'answered_count' => $session->current_index + 1]);
    }

    // ── Playing ───────────────────────────────────────────────────────────

    /** The live room with this PIN, across companies — a player has no company yet. */
    public function findByPin(string $pin): ?TrainingSession
    {
        return TrainingSession::withoutGlobalScope(CompanyScope::class)
            ->where('pin', trim($pin))
            ->live()
            ->latest('id')
            ->first();
    }

    /**
     * Put somebody in the room.
     *
     * A signed-in trainee re-joining gets their existing player row back rather
     * than a second one — a phone that locked itself mid-round must not cost
     * somebody their score.
     *
     * AN ANONYMOUS PLAYER IS NOT MATCHED ON THEIR NICKNAME, and that is the
     * whole difference. It is tempting, because it looks like the same rejoin
     * problem — but two people typing "Chef" into a kitchen briefing is
     * commonplace, and matching on the name silently hands the second one the
     * first one's score. Their rejoin is already solved elsewhere and better:
     * App\Livewire\Lms\LivePlay stores the player id in the browser session, so
     * a reload returns to the same row without anyone having to type their name
     * identically. A genuinely new "Chef" simply becomes "Chef 2".
     */
    public function join(TrainingSession $session, string $nickname, ?LmsUser $trainee = null): TrainingSessionPlayer
    {
        $nickname = trim($nickname) !== '' ? trim(mb_substr($nickname, 0, 60)) : ($trainee?->name ?? 'Player');

        $existing = $trainee
            ? $session->players()->where('lms_user_id', $trainee->id)->first()
            : null;

        if ($existing) {
            $existing->forceFill(['last_seen_at' => now()])->save();

            return $existing;
        }

        // Two people called "Chef" is not an error worth stopping the room for.
        $taken = $session->players()->pluck('nickname')->all();
        $base  = $nickname;
        $n     = 2;
        while (in_array($nickname, $taken, true)) {
            $nickname = mb_substr($base, 0, 56) . ' ' . $n++;
        }

        return DB::transaction(function () use ($session, $nickname, $trainee) {
            $player = $session->players()->create([
                'lms_user_id'  => $trainee?->id,
                'nickname'     => $nickname,
                'joined_at'    => now(),
                'last_seen_at' => now(),
            ]);

            TrainingAttempt::create([
                'company_id'                 => $session->company_id,
                'training_quiz_id'           => $session->training_quiz_id,
                'lms_user_id'                => $trainee?->id,
                'outlet_id'                  => $session->outlet_id ?? $trainee?->outlet_id,
                'training_session_id'        => $session->id,
                'training_session_player_id' => $player->id,
                'mode'                       => 'live',
                'started_at'                 => now(),
                'question_order'             => $session->question_order,
                'question_count'             => $session->questionCount(),
                'max_score'                  => $session->quiz?->maxScore() ?? 0,
            ]);

            return $player;
        });
    }

    /**
     * Take a player's answer to the live question.
     *
     * Returns null when the window has closed, which the player's screen shows
     * as "too slow" rather than as an error — being late is part of the game,
     * not a fault.
     *
     * @param  array<int, int>  $chosen
     */
    public function answer(TrainingSession $session, TrainingSessionPlayer $player, array $chosen): ?TrainingAnswer
    {
        $question = $session->currentQuestion();

        if (! $question || ! $session->acceptsAnswers($question)) {
            return null;
        }

        $attempt = $player->attempt;

        if (! $attempt) {
            return null;
        }

        // Already answered this question — a second tap is ignored rather than
        // rescored, or a player could re-answer at leisure once they saw a
        // neighbour's screen.
        $already = TrainingAnswer::where('training_attempt_id', $attempt->id)
            ->where('training_question_id', $question->id)
            ->exists();

        if ($already) {
            return null;
        }

        // From the SERVER's stamp, so a slow poll costs nothing.
        $seconds = max(0.0, (float) $session->question_started_at->diffInMilliseconds(Carbon::now()) / 1000);

        $answer = $this->scoring->recordAnswer(
            $attempt,
            $question,
            $session->quiz,
            $chosen,
            $seconds,
            $player->streak + 1,
        );

        $this->scoring->applyToPlayer($player, $answer);

        return $answer;
    }

    /**
     * The room's board, highest first.
     *
     * @return Collection<int, TrainingSessionPlayer>
     */
    public function podium(TrainingSession $session, int $limit = 20): Collection
    {
        return $session->players()->limit($limit)->get();
    }

    /**
     * How the room answered the question that just closed, per option.
     *
     * @return array<int, int> option index => how many chose it
     */
    public function optionTally(TrainingSession $session, TrainingQuestion $question): array
    {
        $attemptIds = TrainingAttempt::withoutGlobalScope(CompanyScope::class)
            ->where('training_session_id', $session->id)
            ->pluck('id');

        $tally = array_fill(0, count($question->optionList()), 0);

        TrainingAnswer::whereIn('training_attempt_id', $attemptIds)
            ->where('training_question_id', $question->id)
            ->pluck('chosen')
            ->each(function ($chosen) use (&$tally) {
                foreach ((array) $chosen as $index) {
                    if (isset($tally[(int) $index])) {
                        $tally[(int) $index]++;
                    }
                }
            });

        return $tally;
    }
}
