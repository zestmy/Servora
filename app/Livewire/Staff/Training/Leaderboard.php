<?php

namespace App\Livewire\Staff\Training;

use App\Livewire\Clock\Staff\StaffComponent;
use App\Models\TrainingQuiz;
use App\Services\Training\LeaderboardService;
use Livewire\Attributes\Url;

/**
 * The board, and the first thing the staff app opens on.
 *
 * LANDING PAGE BY DECISION, not by accident — /staff used to redirect straight
 * to the clock-in camera. The trade is real and worth writing down: clocking in
 * is now one tap further away for everybody, every shift, and that is a cost
 * paid by people standing in a doorway. It buys the thing the module exists for
 * — the board is only motivating if it is SEEN, and a screen nobody opens
 * motivates nobody. The clock is the first tab in the bar, so the extra tap is
 * exactly one and always in the same place.
 *
 * Defaults to this employee's own outlet rather than the company. A
 * fifteen-branch board has one person who is always top and stops being a
 * reason to play; the branch board is one a team can actually win. The company
 * view is one tap away for anyone who wants it.
 */
class Leaderboard extends StaffComponent
{
    #[Url(as: 'period', except: 'month')]
    public string $period = 'month';

    /** 'outlet' — this person's branch; 'company' — everybody. */
    #[Url(as: 'scope', except: 'outlet')]
    public string $scope = 'outlet';

    /**
     * One quiz, or all of them.
     *
     * "Who is best overall" and "who did best on the allergen paper" are
     * different questions, and only the second is any use when a manager is
     * following up one piece of training.
     */
    #[Url(as: 'quiz', except: '')]
    public string $quizId = '';

    public function render(LeaderboardService $leaderboard)
    {
        $employee = $this->staff();

        $outletId = $this->scope === 'outlet' ? $employee?->outlet_id : null;

        // Re-checked against what this person may actually be offered, so a
        // quiz id typed into the URL cannot name a paper for another section.
        $quizzes = TrainingQuiz::query()
            ->where('company_id', $employee->company_id)
            ->published()
            ->forAudience($employee->section_id, $employee->trainingOutletIds())
            ->whereHas('questions')
            ->orderBy('title')
            ->get(['id', 'title']);

        $quizId = $quizzes->contains('id', (int) $this->quizId) ? (int) $this->quizId : null;

        $board = $leaderboard->board(
            (int) $employee->company_id,
            $this->period,
            $outletId,
            $quizId,
            50,
        );

        $me = $board->firstWhere('employee_id', $employee->id);

        // Off the end of the board is still a position worth knowing: "63rd"
        // tells somebody where they stand, "not in the top 50" tells them
        // nothing about whether they are close.
        $position = $me
            ? ['rank' => $me['rank'], 'of' => $board->count()]
            : $leaderboard->positionOf((int) $employee->company_id, $employee->id, $this->period, $outletId);

        return view('livewire.staff.training.leaderboard', [
            'board'      => $board,
            'quizzes'    => $quizzes,
            'employee'   => $employee,
            'me'         => $me,
            'position'   => $position,
            /*
             * Who has not started. The board answers "who is winning"; on a
             * floor of fifty-odd people the more useful question is "who has
             * not begun", and it cannot be read off a list of the people who
             * have. See LeaderboardService::notStarted for why this is a nudge
             * list rather than a wall of shame, and what keeps it that way.
             */
            // Hidden when the board is filtered to one paper: "has not taken
            // ANY quiz" is a statement about a person, and the same list under
            // a one-quiz filter would be a different claim under the same
            // heading.
            'notStarted' => $quizId
                ? collect()
                : $leaderboard->notStarted((int) $employee->company_id, $this->period, $outletId),
        ])->layout('layouts.clock-staff', $this->shell('Leaderboard'));
    }
}
