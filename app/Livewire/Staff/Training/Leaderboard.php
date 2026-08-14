<?php

namespace App\Livewire\Staff\Training;

use App\Livewire\Clock\Staff\StaffComponent;
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

    public function render(LeaderboardService $leaderboard)
    {
        $employee = $this->staff();

        $outletId = $this->scope === 'outlet' ? $employee?->outlet_id : null;

        $board = $leaderboard->board(
            (int) $employee->company_id,
            $this->period,
            $outletId,
            null,
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
            'board'    => $board,
            'employee' => $employee,
            'me'       => $me,
            'position' => $position,
        ])->layout('layouts.clock-staff', $this->shell('Leaderboard'));
    }
}
