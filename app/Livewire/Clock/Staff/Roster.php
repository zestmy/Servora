<?php

namespace App\Livewire\Clock\Staff;

use App\Models\Roster as RosterWeek;
use App\Models\RosterEntry;
use App\Scopes\CompanyScope;
use Carbon\Carbon;

/**
 * The employee's own duty roster — the shifts they are down for.
 *
 * The question this answers is "am I working tomorrow", which is currently
 * answered by a photograph of a printed sheet in a WhatsApp group. Two weeks
 * forward and the current week back, because "what was I down for on Tuesday"
 * comes up when a punch is queried.
 *
 * ONLY APPROVED ROSTERS. A draft is a manager still moving people around, and
 * showing it would have staff turning up for a shift that was reconsidered an
 * hour later — worse than showing nothing.
 */
class Roster extends StaffComponent
{
    /** Weeks either side of today. Forward is what people ask; back is proof. */
    private const WEEKS_AHEAD = 3;
    private const WEEKS_BACK  = 1;

    public function render()
    {
        $staff = $this->staff();

        $from = now()->startOfWeek()->subWeeks(self::WEEKS_BACK);
        $to   = now()->startOfWeek()->addWeeks(self::WEEKS_AHEAD)->endOfWeek();

        // Scoped by hand to this employee and their company: there is no
        // authenticated web user here, so CompanyScope has nothing to work
        // from and a mistake would show somebody else's shifts.
        $approvedRosterIds = RosterWeek::withoutGlobalScopes()
            ->where('company_id', $staff->company_id)
            ->where('status', 'approved')
            ->whereDate('week_end_date', '>=', $from->toDateString())
            ->whereDate('week_start_date', '<=', $to->toDateString())
            ->pluck('id');

        $entries = RosterEntry::withoutGlobalScopes()
            ->whereIn('roster_id', $approvedRosterIds)
            ->where('employee_id', $staff->id)
            ->whereBetween('day_date', [$from->toDateString(), $to->toDateString()])
            ->with('station:id,name')
            ->orderBy('day_date')
            ->get();

        // Grouped by week so the shape matches the printed roster people are
        // used to, rather than one long list of dates.
        $weeks = $entries->groupBy(fn ($e) => Carbon::parse($e->day_date)->startOfWeek()->toDateString());

        return view('livewire.clock.staff.roster', [
            'weeks'     => $weeks,
            'today'     => now()->toDateString(),
            'hasRoster' => $approvedRosterIds->isNotEmpty(),
        ])->layout('layouts.clock-staff', $this->shell('My roster'));
    }
}
