<?php

namespace App\Livewire\Lms;

use App\Models\TrainingAnswer;
use App\Models\TrainingSession;
use App\Models\TrainingSessionPlayer;
use App\Scopes\CompanyScope;
use App\Services\Training\LiveSessionService;
use Illuminate\Support\Facades\Auth;
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

    /** Option indexes tapped but not yet sent (multi-select only). */
    public array $chosen = [];

    public string $joinError = '';

    public function mount(?string $pin = null): void
    {
        $this->pin = trim((string) ($pin ?? request()->query('pin', '')));

        $trainee = Auth::guard('lms')->user();

        if ($trainee) {
            $this->nickname = $trainee->name;
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

        $trainee = Auth::guard('lms')->user();

        // A trainee may only join their own company's room. A PIN is a room
        // number, not an invitation across tenants.
        if ($trainee && $trainee->company_id !== $session->company_id) {
            $this->joinError = 'That PIN belongs to another company.';

            return;
        }

        if (trim($this->nickname) === '') {
            $this->joinError = 'Pick a name so the leaderboard can show you.';

            return;
        }

        $player = $sessions->join($session, $this->nickname, $trainee);

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

        $current = $this->session()?->currentQuestion();

        if ($current && $this->answeredQuestionId !== $current->id) {
            $this->chosen = [];
        }
    }

    public function render()
    {
        $session = $this->session();
        $player  = $this->player();

        if (! $session || ! $player) {
            return view('livewire.lms.live-join', ['joinError' => $this->joinError])
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

        return view('livewire.lms.live-play', [
            'session'         => $session,
            'player'          => $player,
            'question'        => $question,
            'remaining'       => $session->secondsRemaining($question),
            'alreadyAnswered' => $alreadyAnswered,
            'podium'          => $session->players()->limit(5)->get(),
            'rank'            => $session->players()->pluck('id')->search($player->id) + 1,
            'total'           => $session->players()->count(),
        ])->layout('layouts.live', ['title' => 'Live session']);
    }
}
