<?php

namespace App\Livewire\Training;

use App\Models\Outlet;
use App\Models\TrainingQuiz;
use App\Models\TrainingSession;
use App\Services\Training\LiveSessionService;
use App\Traits\RequiresActiveCompany;
use App\Traits\ValidatesCompanyOutlet;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Open a live room, or look at one that has already run.
 *
 * The history matters more than it looks: "we ran the allergen quiz at the
 * Bangsar briefing on Tuesday and six of nine got question 4 wrong" is the
 * thing that changes what a manager teaches next, and it is gone by Thursday
 * unless it was written down.
 */
class Sessions extends Component
{
    use RequiresActiveCompany;
    use ValidatesCompanyOutlet;
    use WithPagination;

    public ?int $quizId = null;
    public ?int $outletId = null;
    public string $sessionName = '';

    /** 'room' — everyone together now; 'challenge' — open for a window. */
    public string $kind = 'room';
    public ?string $opensAt = null;
    public ?string $closesAt = null;

    public function mount(): void
    {
        $this->requireActiveCompany();
        $this->outletId = Auth::user()->activeOutletId();
    }

    public function host(LiveSessionService $sessions)
    {
        $this->authorize('training.host');

        $this->validate([
            'quizId'      => ['required', 'integer'],
            'outletId'    => ['nullable', $this->outletExistsRule()],
            'sessionName' => ['nullable', 'string', 'max:120'],
            'opensAt'     => ['nullable', 'date'],
            'closesAt'    => ['nullable', 'date', 'after_or_equal:opensAt'],
        ], [], ['quizId' => 'quiz', 'closesAt' => 'closing date', 'opensAt' => 'opening date']);

        $quiz = TrainingQuiz::withCount('questions')->findOrFail($this->quizId);

        try {
            $session = $this->kind === 'challenge'
                ? $sessions->schedule(
                    $quiz,
                    $this->outletId,
                    Auth::id(),
                    $this->opensAt ? \Illuminate\Support\Carbon::parse($this->opensAt) : null,
                    // End of the chosen day. "Closes Friday" means the end of
                    // Friday to everybody who reads it, and taking the date at
                    // midnight would shut it before Friday had started.
                    $this->closesAt ? \Illuminate\Support\Carbon::parse($this->closesAt)->endOfDay() : null,
                    $this->sessionName ?: null,
                )
                : $sessions->open($quiz, $this->outletId, Auth::id(), $this->sessionName ?: null);
        } catch (\RuntimeException $e) {
            session()->flash('error', $e->getMessage());

            return null;
        }

        if ($this->kind === 'challenge') {
            session()->flash('success', 'Challenge scheduled. Staff will see it in the Staff Portal.');

            $this->reset(['opensAt', 'closesAt', 'sessionName']);

            return null;
        }

        return redirect()->route('training.live.host', $session->id);
    }

    /**
     * Close a room somebody walked away from.
     *
     * Live sessions have no timeout of their own — the host device going flat
     * mid-round should not throw the room out — so an abandoned one stays in
     * 'lobby' until a human ends it. This is that human.
     */
    public function endStale(int $id, LiveSessionService $sessions): void
    {
        $this->authorize('training.host');

        $sessions->end(TrainingSession::findOrFail($id));

        session()->flash('success', 'Session closed.');
    }

    public function render()
    {
        $companyId = Auth::user()->company_id;

        /*
         * whereHas, not withCount()->having().
         *
         * `questions_count` is a select-list subquery, and HAVING against one
         * with no GROUP BY is a MySQL extension: SQLite rejects it outright
         * ("HAVING clause on a non-aggregate query"), so the screen worked in
         * production and 500'd in every test. Same class of trap as raw
         * DATE_FORMAT — the fix is to say it in SQL both engines agree on.
         * withCount stays, because the option labels show the count.
         */
        $quizzes = TrainingQuiz::query()
            ->where('status', 'published')
            ->whereHas('questions')
            ->withCount('questions')
            ->orderBy('title')
            ->get();

        $live = TrainingSession::query()
            ->live()
            ->with(['quiz:id,title', 'outlet:id,name'])
            ->withCount('players')
            ->latest('id')
            ->get();

        // Scheduled challenges, open or waiting to open. Counted by ATTEMPTS
        // rather than players: nobody joins a challenge, they just take it.
        $challenges = TrainingSession::query()
            ->where('mode', TrainingSession::MODE_SCHEDULED)
            ->where('status', '!=', 'ended')
            ->with(['quiz:id,title', 'outlet:id,name'])
            ->withCount(['attempts as taken_count' => fn ($q) => $q->whereNotNull('completed_at')])
            ->orderBy('closes_at')
            ->get();

        $past = TrainingSession::query()
            ->where('status', 'ended')
            ->with(['quiz:id,title', 'outlet:id,name', 'host:id,name'])
            ->withCount('players')
            ->latest('id')
            ->paginate(15);

        $outlets = Outlet::where('company_id', $companyId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('livewire.training.sessions', compact('quizzes', 'live', 'challenges', 'past', 'outlets'))
            ->layout(\App\Helpers\WorkspaceLayout::get(), ['title' => 'Live sessions']);
    }
}
