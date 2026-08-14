<?php

namespace App\Livewire\Staff\Training;

use App\Models\Employee;
use App\Models\TrainingAnswer;
use App\Models\TrainingQuestion;
use App\Models\TrainingSession;
use App\Models\TrainingSessionPlayer;
use App\Scopes\CompanyScope;
use App\Services\Staff\StaffSession;
use App\Services\Training\LiveSessionService;
use Livewire\Component;

/**
 * The player's phone during a live session.
 *
 * Joins with a PIN, then follows the room. Everything on screen is derived from
 * the session row on each poll — see TrainingSession — so a phone that missed
 * three polls catches up rather than resuming where it left off.
 *
 * The player id is held in the SESSION, not in a component property. A property
 * round-trips through the browser, and somebody who edited it would be
 * answering as a colleague.
 */
class LivePlay extends Component
{
    public string $pin = '';
    public string $nickname = '';

    public ?int $sessionId = null;
    public ?int $playerId = null;

    /** Which question the phone has already answered, so it stops asking. */
    public ?int $answeredQuestionId = null;

    /**
     * Which reveal this phone has already played a sound for.
     *
     * The room polls once a second and a reveal lasts as long as the host wants
     * it to, so without this the ding fires every second until they press next
     * — which is funny once and unbearable immediately afterwards.
     */
    public ?int $celebratedQuestionId = null;

    /** Option indexes tapped but not yet sent (multi-select only). */
    public array $chosen = [];

    public string $joinError = '';

    public function mount(?string $pin = null): void
    {
        $this->pin = trim((string) ($pin ?? request()->query('pin', '')));

        $employee = $this->employee();

        if ($employee) {
            $this->nickname = $employee->name;
        }

        // Rejoin whatever room this browser was already in.
        $stored = session('training_live');

        if (is_array($stored) && isset($stored['session'], $stored['player'])) {
            $session = TrainingSession::withoutGlobalScope(CompanyScope::class)
                ->live()->find($stored['session']);

            if ($session) {
                $this->sessionId = $session->id;
                $this->playerId  = $stored['player'];
            } else {
                session()->forget('training_live');
            }
        }
    }

    public function join(LiveSessionService $sessions): void
    {
        $this->joinError = '';

        $session = $sessions->findByPin($this->pin);

        if (! $session) {
            $this->joinError = 'No live session with that PIN. Check the screen and try again.';

            return;
        }

        $employee = $this->employee();

        // A trainee may only join their own company's room. A PIN is a room
        // number, not an invitation across tenants.
        if ($employee && $employee->company_id !== $session->company_id) {
            $this->joinError = 'That PIN belongs to another company.';

            return;
        }

        if (trim($this->nickname) === '') {
            $this->joinError = 'Pick a name so the leaderboard can show you.';

            return;
        }

        $player = $sessions->join($session, $this->nickname, $employee);

        $this->sessionId = $session->id;
        $this->playerId  = $player->id;

        session(['training_live' => ['session' => $session->id, 'player' => $player->id]]);
    }

    public function leave(): void
    {
        session()->forget('training_live');

        $this->sessionId = null;
        $this->playerId  = null;
    }

    /**
     * The signed-in employee, or null for a walk-up player.
     *
     * Resolved from the container each time rather than held on the component:
     * a Livewire property round-trips through the browser, and a learner
     * identity that can be edited client-side is one somebody can answer as.
     *
     * Null is a normal answer here. A live round at a shift briefing is most of
     * the point, and the casuals in that room have no account yet — they type a
     * nickname and play. They just do not get it onto a report card.
     */
    private function employee(): ?Employee
    {
        return app(StaffSession::class)->employee();
    }

    private function session(): ?TrainingSession
    {
        return $this->sessionId
            ? TrainingSession::withoutGlobalScope(CompanyScope::class)->with('quiz')->find($this->sessionId)
            : null;
    }

    private function player(): ?TrainingSessionPlayer
    {
        return $this->playerId
            ? TrainingSessionPlayer::where('training_session_id', $this->sessionId)->find($this->playerId)
            : null;
    }

    /**
     * Tap an option. Single-answer types send immediately; multi waits.
     *
     * Named pick() rather than tap(): Livewire\Component already has a tap(),
     * and overriding it with a different signature is a fatal error at class
     * load, not a runtime one — the whole app fails to boot.
     */
    public function pick(int $optionIndex, bool $multi, LiveSessionService $sessions): void
    {
        if (! $multi) {
            $this->chosen = [$optionIndex];
            $this->send($sessions);

            return;
        }

        $key = array_search($optionIndex, array_map('intval', $this->chosen), true);

        if ($key === false) {
            $this->chosen[] = $optionIndex;
        } else {
            unset($this->chosen[$key]);
            $this->chosen = array_values($this->chosen);
        }
    }

    public function send(LiveSessionService $sessions): void
    {
        $session = $this->session();
        $player  = $this->player();

        if (! $session || ! $player || $this->chosen === []) {
            return;
        }

        $question = $session->currentQuestion();

        $sessions->answer($session, $player, array_map('intval', $this->chosen));

        // Recorded either way. A rejected answer — too late, or already in —
        // must still stop the phone offering the buttons again.
        $this->answeredQuestionId = $question?->id;
    }

    /**
     * Poll tick: keeps the player on the lobby list and clears the local
     * "already answered" flag when the room moves on.
     */
    public function heartbeat(): void
    {
        $player = $this->player();

        if (! $player) {
            return;
        }

        $player->forceFill(['last_seen_at' => now()])->save();

        $session = $this->session();
        $current = $session?->currentQuestion();

        if ($current && $this->answeredQuestionId !== $current->id) {
            $this->chosen = [];
        }

        /*
         * The verdict lands when the HOST reveals, not when the answer was
         * sent. Playing it at send time would be simpler and would also tell
         * the room what the answer was while everyone else is still choosing —
         * which is the one thing a live round cannot allow.
         */
        if ($session?->status === 'reveal' && $current && $this->celebratedQuestionId !== $current->id) {
            $this->celebratedQuestionId = $current->id;

            $answer = $this->myAnswer($current);

            if ($answer) {
                [$right, $wrong] = \App\Models\TrainingQuiz::verdictWordsFor($session->quiz?->language);

                $this->dispatch(
                    'answer-scored',
                    correct: (bool) $answer->is_correct,
                    points: (int) $answer->points_awarded,
                    label: $answer->is_correct ? $right : $wrong,
                );
            }
        }
    }

    /** This player's row for a question, if they answered it. */
    private function myAnswer(?TrainingQuestion $question): ?TrainingAnswer
    {
        $attempt = $this->player()?->attempt;

        if (! $question || ! $attempt) {
            return null;
        }

        return TrainingAnswer::where('training_attempt_id', $attempt->id)
            ->where('training_question_id', $question->id)
            ->first();
    }

    public function render()
    {
        $session = $this->session();
        $player  = $this->player();

        if (! $session || ! $player) {
            return view('livewire.staff.training.live-join', ['joinError' => $this->joinError])
                ->layout('layouts.live', ['title' => 'Join a live session']);
        }

        $question = $session->currentQuestion();

        // Asked from the ROWS rather than the local flag, so a phone that
        // reloaded mid-question does not get a second go.
        $alreadyAnswered = false;

        if ($question && $player->attempt) {
            $alreadyAnswered = TrainingAnswer::where('training_attempt_id', $player->attempt->id)
                ->where('training_question_id', $question->id)
                ->exists();
        }

        return view('livewire.staff.training.live-play', [
            'session'         => $session,
            'player'          => $player,
            'question'        => $question,
            'myAnswer'        => $session->status === 'reveal' ? $this->myAnswer($question) : null,
            'remaining'       => $session->secondsRemaining($question),
            'alreadyAnswered' => $alreadyAnswered,
            'podium'          => $session->players()->limit(5)->get(),
            'rank'            => $session->players()->pluck('id')->search($player->id) + 1,
            'total'           => $session->players()->count(),
        ])->layout('layouts.live', ['title' => 'Live session']);
    }
}
