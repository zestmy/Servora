<?php

namespace App\Livewire\Staff\Training;

use App\Livewire\Clock\Staff\StaffComponent;
use App\Models\TrainingCertificate;

/**
 * Everything this person has earned, in one place.
 *
 * A SCREEN OF ITS OWN rather than a block inside the report card, and the
 * reason is what a certificate is FOR. The report card is diagnostic — what you
 * scored, what to work on — and it is read when somebody is deciding what to do
 * next. A certificate is the opposite: it is the thing you show a manager, a
 * new employer or an auditor, and it is looked for months after the quiz, by
 * somebody who remembers passing and not where the result went. Burying it
 * under a heading about weak topics is asking them to walk past it.
 *
 * REVOKED ONES ARE STILL LISTED, marked and without a download. A certificate
 * that quietly vanishes leaves somebody certain they earned it and unable to
 * find it; one that says it was withdrawn is a fact they can go and ask about.
 */
class MyCertificates extends StaffComponent
{
    public function render()
    {
        $employee = $this->staff();

        $certificates = TrainingCertificate::query()
            ->where('employee_id', $employee->id)
            ->with('course:id,title')
            // Newest first: the one somebody has come looking for is almost
            // always the one they just earned.
            ->orderByDesc('issued_at')
            ->orderByDesc('id')
            ->get();

        return view('livewire.staff.training.my-certificates', [
            'employee'     => $employee,
            'certificates' => $certificates,
            'valid'        => $certificates->filter(
                fn (TrainingCertificate $c) => ! $c->isRevoked() && ! $c->isExpired(),
            ),
        ])->layout('layouts.clock-staff', $this->shell('My certificates'));
    }
}
