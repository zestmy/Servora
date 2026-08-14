<?php

namespace App\Livewire\Staff\Training;

use App\Livewire\Clock\Staff\StaffComponent;
use App\Services\Training\ReportCardService;

/**
 * An employee's own report card.
 *
 * The same service the manager's screen uses, so the two cannot disagree — being
 * told one average by your phone and another at your review is the sort of thing
 * that ends trust in the whole module.
 *
 * The weak-topic list is shown here as well as to the manager, phrased as what
 * to look at next rather than as a deficiency. It is the most useful thing on
 * the page.
 */
class MyProgress extends StaffComponent
{
    public function render(ReportCardService $reportCards)
    {
        $employee = $this->staff();

        return view('livewire.staff.training.my-progress', [
            'card'     => $reportCards->for($employee),
            'employee' => $employee,
        ])->layout('layouts.clock-staff', $this->shell('My progress'));
    }
}
