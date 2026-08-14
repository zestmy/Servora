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

        return view('livewire.training.leaderboard', compact('board', 'outlets', 'quizzes'))
            ->layout(\App\Helpers\WorkspaceLayout::get(), ['title' => 'Leaderboard']);
    }
}
