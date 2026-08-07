<?php

namespace App\Livewire\Clock\Staff;

use App\Models\ClockEvent;
use App\Scopes\CompanyScope;

/**
 * The employee's own punch history for the last fortnight.
 *
 * Exists so somebody can check what was recorded against them BEFORE payday
 * rather than after it. A deduction discovered on a payslip is an argument;
 * the same deduction visible the same afternoon is a conversation.
 *
 * Rejected punches are shown too, greyed out. Hiding them would mean a
 * manager could void an arrival and the person it happened to would have no
 * way of knowing.
 */
class History extends StaffComponent
{
    private const DAYS = 14;


    public function render()
    {
        $events = ClockEvent::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $this->staff()->company_id)
            ->where('employee_id', $this->staff()->id)
            ->where('work_date', '>=', now()->subDays(self::DAYS)->toDateString())
            // locationLabel() reads both of these. Left lazy they are two
            // queries per PUNCH on a screen that lists a fortnight of them —
            // sixty of each on a phone, over a kitchen's 4G.
            ->with(['outlet:id,name', 'device:id,name'])
            ->orderByDesc('work_date')
            ->orderByDesc('happened_at')
            ->get()
            ->groupBy(fn ($e) => $e->work_date->format('Y-m-d'));

        return view('livewire.clock.staff.history', [
            'days' => $events,
        ])->layout('layouts.clock-staff', $this->shell('My punches'));
    }
}
