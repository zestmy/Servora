<?php

namespace App\Livewire\Lms;

use App\Services\Training\LeaderboardService;
use App\Services\Training\ReportCardService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * A trainee's own report card, and the board they are on.
 *
 * Same service the manager's screen uses, so the two cannot disagree — a staff
 * member being told one average by their phone and another at their review is
 * the sort of thing that ends trust in the whole module.
 *
 * The weak-topic list is shown to the trainee as well as the manager, phrased
 * as what to look at next rather than as a deficiency. It is the single most
 * useful thing on the page.
 */
class MyProgress extends Component
{
    #[Url(as: 'period', except: 'month')]
    public string $period = 'month';

    public function render(ReportCardService $reportCards, LeaderboardService $leaderboard)
    {
        $trainee = Auth::guard('lms')->user();

        $card = $reportCards->for($trainee);

        $board = $leaderboard->board($trainee->company_id, $this->period, null, null, 20);

        $position = $leaderboard->positionOf($trainee->company_id, $trainee->id, $this->period);

        return view('livewire.lms.my-progress', compact('card', 'board', 'position', 'trainee'))
            ->layout('layouts.lms', ['title' => 'My progress']);
    }
}
