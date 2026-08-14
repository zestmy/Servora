<?php

namespace App\Livewire\Training;

use App\Models\TrainingSession;
use App\Services\Training\LiveSessionService;
use App\Traits\RequiresActiveCompany;
use Livewire\Component;

/**
 * The host console — the screen the room is looking at.
 *
 * Polls once a second, which is what makes the lobby fill in front of everyone
 * and the answer counter climb during a question. That is not decoration: the
 * counter is how a host knows whether to reveal now or wait for the two people
 * still reading.
 *
 * The console never decides what question is live — it asks the service to
 * advance and re-reads the row. See LiveSessionService.
 */
class LiveHost extends Component
{
    use RequiresActiveCompany;

    public int $sessionId;

    /** Auto-reveal once the clock runs out, so the host can watch the room. */
    public bool $autoReveal = true;

    public function mount(int $id): void
    {
        $this->requireActiveCompany();

        $this->sessionId = TrainingSession::findOrFail($id)->id;
    }

    private function session(): TrainingSession
    {
        return TrainingSession::with('quiz')->findOrFail($this->sessionId);
    }

    public function start(LiveSessionService $sessions): void
    {
        $this->authorize('training.host');

        $session = $this->session();

        if ($session->players()->count() === 0) {
            session()->flash('error', 'Nobody has joined yet — the PIN is on screen.');

            return;
        }

        $sessions->start($session);
    }

    public function reveal(LiveSessionService $sessions): void
    {
        $this->authorize('training.host');

        $sessions->reveal($this->session());
    }

    public function next(LiveSessionService $sessions): void
    {
        $this->authorize('training.host');

        $sessions->next($this->session());
    }

    public function end(LiveSessionService $sessions): void
    {
        $this->authorize('training.host');

        $sessions->end($this->session());
    }

    /**
     * The poll tick.
     *
     * Auto-reveal happens here rather than on a timer in the browser, because
     * the browser's clock is the one thing in this design that is not shared.
     * The server closed the window; the server says so.
     */
    public function tick(LiveSessionService $sessions): void
    {
        $session = $this->session();

        if ($this->autoReveal && $session->status === 'question' && $session->secondsRemaining() <= 0) {
            $sessions->reveal($session);
        }
    }

    public function render()
    {
        $session  = $this->session();
        $question = $session->currentQuestion();

        $answered = 0;
        $tally    = [];

        if ($question) {
            $attemptIds = $session->attempts()->pluck('id');

            // Plain row count, not a JSON-length filter. While a question is
            // live the only rows that exist are real answers — the zero rows
            // for everyone who missed it are written at reveal — and a
            // json_array_length predicate would be one more thing that behaves
            // differently on SQLite than on MySQL.
            $answered = \App\Models\TrainingAnswer::whereIn('training_attempt_id', $attemptIds)
                ->where('training_question_id', $question->id)
                ->count();

            if ($session->status !== 'question') {
                $tally = app(LiveSessionService::class)->optionTally($session, $question);
            }
        }

        return view('livewire.training.live-host', [
            'session'   => $session,
            'question'  => $question,
            'players'   => $session->players()->get(),
            'answered'  => $answered,
            'tally'     => $tally,
            'remaining' => $session->secondsRemaining($question),
        ])->layout(\App\Helpers\WorkspaceLayout::get(), ['title' => 'Live session']);
    }
}
