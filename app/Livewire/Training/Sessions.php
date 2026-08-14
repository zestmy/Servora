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
        ], [], ['quizId' => 'quiz']);

        $quiz = TrainingQuiz::withCount('questions')->findOrFail($this->quizId);

        try {
            $session = $sessions->open($quiz, $this->outletId, Auth::id(), $this->sessionName ?: null);
        } catch (\RuntimeException $e) {
            session()->flash('error', $e->getMessage());

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

        return view('livewire.training.sessions', compact('quizzes', 'live', 'past', 'outlets'))
            ->layout(\App\Helpers\WorkspaceLayout::get(), ['title' => 'Live sessions']);
    }
}
