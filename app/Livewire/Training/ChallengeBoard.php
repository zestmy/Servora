<?php

namespace App\Livewire\Training;

use App\Models\TrainingSession;
use App\Services\Training\LiveSessionService;
use App\Traits\RequiresActiveCompany;
use Livewire\Component;

/**
 * The board for a scheduled challenge — the screen a host watches.
 *
 * Deliberately NOT the live console. A room console exists to drive a state
 * machine: reveal, next, end. A challenge has none of that, because the whole
 * point of scheduling one is that nobody has to run it — staff take it on their
 * own phones over a window of days. What is left is the thing a manager
 * actually wanted from the console anyway: who has taken it, who scored what,
 * and who has not turned up yet.
 *
 * Polls every ten seconds rather than every one. A live room's counter climbs
 * inside a thirty-second question; a challenge's board changes when somebody
 * finishes a quiz, which is a thing that happens a few times an hour at most.
 */
class ChallengeBoard extends Component
{
    use RequiresActiveCompany;

    public int $sessionId;

    public function mount(int $id): void
    {
        $this->requireActiveCompany();

        $session = TrainingSession::findOrFail($id);

        // A live room reaching this URL is sent to its own console rather than
        // shown an empty board — the two are one id apart in the address bar.
        if (! $session->isScheduled()) {
            $this->redirectRoute('training.live.host', $session->id, navigate: true);

            return;
        }

        $this->sessionId = $session->id;
    }

    /** Close it early. Scores already taken are kept. */
    public function close(LiveSessionService $sessions): void
    {
        $this->authorize('training.host');

        $session = TrainingSession::findOrFail($this->sessionId);

        $session->forceFill([
            'status'   => 'ended',
            'ended_at' => $session->ended_at ?? now(),
        ])->save();

        session()->flash('success', 'Challenge closed. Everything scored so far is kept.');
    }

    public function render(LiveSessionService $sessions)
    {
        $session = TrainingSession::with(['quiz:id,title,pass_mark,language', 'outlet:id,name', 'host:id,name'])
            ->findOrFail($this->sessionId);

        $standings = $sessions->standings($session);

        return view('livewire.training.challenge-board', [
            'session'   => $session,
            'standings' => $standings,
            'finished'  => $standings->where('finished', true),
        ])->layout(\App\Helpers\WorkspaceLayout::get(), ['title' => 'Challenge']);
    }
}
