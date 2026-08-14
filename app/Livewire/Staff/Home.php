<?php

namespace App\Livewire\Staff;

use App\Livewire\Clock\Staff\StaffComponent;
use App\Models\TrainingAttempt;
use App\Services\Hr\PunchState;
use App\Services\Training\LeaderboardService;
use App\Services\Training\ReportCardService;

/**
 * The Staff Portal's front door.
 *
 * WHY THIS EXISTS RATHER THAN A REDIRECT. /staff used to go straight to the
 * clock-in camera, which made the app a single-purpose tool; then it went
 * straight to the leaderboard, which put the board in front of people at the
 * exact moment they were trying to clock in. Neither is a home screen. This is:
 * it answers "am I clocked in", "what do I owe", and "where am I on the board"
 * in one glance, and it keeps CLOCKING IN ONE TAP AWAY — the primary button on
 * the card at the top, so the doorway case costs a tap and nothing more.
 *
 * The clock state comes from PunchState, the same service the punch screen
 * itself uses. Reimplementing "are they in or out" here would be a second
 * answer to a question that must only ever have one.
 *
 * Everything below the clock degrades to nothing rather than to an error: a
 * company with no training published, an employee with no attempts and no
 * assignments, somebody with no outlet — each of those is a normal state on a
 * first run, and a home screen that 500s on a fresh install is worse than one
 * that is briefly empty.
 */
class Home extends StaffComponent
{
    public function render(
        PunchState $punches,
        LeaderboardService $leaderboard,
        ReportCardService $reportCards,
    ) {
        $employee = $this->staff();

        // ── Clock ─────────────────────────────────────────────────────────
        $open = $punches->openPunch($employee);
        $last = $punches->lastPunch($employee);

        // ── Learning ──────────────────────────────────────────────────────
        // Scoped to their own branch, matching the board's own default: a
        // fifteen-outlet rank is not a number anybody can act on.
        $position = $leaderboard->positionOf(
            (int) $employee->company_id,
            $employee->id,
            'month',
            $employee->outlet_id,
        );

        ['start' => $start, 'end' => $end] = $leaderboard->range('month');

        $mine = TrainingAttempt::query()
            ->where('employee_id', $employee->id)
            ->completed()
            ->when($start, fn ($q) => $q->where('completed_at', '>=', $start))
            ->when($end, fn ($q) => $q->where('completed_at', '<=', $end))
            ->get();

        // Best per quiz, so the headline number matches how the board scores —
        // two figures for the same month that disagree is worse than one.
        $best = $mine->groupBy('training_quiz_id')
            ->map(fn ($attempts) => $attempts->sortByDesc('score')->first());

        $questions = (int) $best->sum('question_count');
        $correct   = (int) $best->sum('correct_count');

        return view('livewire.staff.home', [
            'employee'    => $employee,
            'open'        => $open,
            'last'        => $last,
            'onBreak'     => $punches->onBreak($employee),
            'position'    => $position,
            'points'      => (int) $best->sum('score'),
            'accuracy'    => $questions > 0 ? round($correct / $questions * 100) : null,
            'passed'      => $best->where('passed', true)->count(),
            'outstanding' => $reportCards->outstanding($employee),
            'top'         => $leaderboard->board((int) $employee->company_id, 'month', $employee->outlet_id, null, 3),
        ])->layout('layouts.clock-staff', $this->shell('Home'));
    }
}
