<?php

namespace App\Livewire\Training;

use App\Models\Outlet;
use App\Models\TrainingQuiz;
use App\Services\Training\LeaderboardService;
use App\Traits\RequiresActiveCompany;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * The company board.
 *
 * Filterable by outlet on purpose: a fifteen-branch group has one person who is
 * always top, and a board nobody local can win stops being a reason to play.
 * Branch managers run their own.
 */
class Leaderboard extends Component
{
    use RequiresActiveCompany;

    #[Url(as: 'period', except: 'month')]
    public string $period = 'month';

    #[Url(as: 'outlet', except: '')]
    public string $outletId = '';

    #[Url(as: 'quiz', except: '')]
    public string $quizId = '';

    public function mount(): void
    {
        $this->requireActiveCompany();
    }

    public function render(LeaderboardService $leaderboard)
    {
        $companyId = Auth::user()->company_id;

        $board = $leaderboard->board(
            $companyId,
            $this->period,
            $this->outletId ? (int) $this->outletId : null,
            $this->quizId ? (int) $this->quizId : null,
        );

        $outlets = Outlet::where('company_id', $companyId)->where('is_active', true)
            ->orderBy('name')->get(['id', 'name']);

        $quizzes = TrainingQuiz::orderBy('title')->get(['id', 'title']);

        /*
         * Who has not started, for the person whose job it is to chase them.
         *
         * Only when no single quiz is selected: "has not taken ANY quiz" is a
         * statement about a person, and filtering the board to one paper would
         * turn it into "has not taken this one", which is a different list
         * wearing the same heading. See LeaderboardService::notStarted.
         */
        $notStarted = $this->quizId
            ? collect()
            : $leaderboard->notStarted(
                $companyId,
                $this->period,
                $this->outletId ? (int) $this->outletId : null,
            );

        return view('livewire.training.leaderboard', compact('board', 'outlets', 'quizzes', 'notStarted'))
            ->layout(\App\Helpers\WorkspaceLayout::get(), ['title' => 'Leaderboard']);
    }
}
