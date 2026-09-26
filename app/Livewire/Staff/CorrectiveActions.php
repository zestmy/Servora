<?php

namespace App\Livewire\Staff;

use App\Livewire\Clock\Staff\StaffComponent;
use App\Models\CorrectiveAction;
use App\Services\Audits\CorrectiveActionService;
use App\Services\ImageStorageService;
use App\Traits\RejectsUnpreviewableUploads;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * "Audit fixes" on the Staff Portal: the corrective actions from outlet
 * audits that name this employee as owner, and everything open at their
 * outlet for context.
 *
 * A Manager or Chef marks their own work in progress or done from their
 * phone, with a note and a photo of the fix. VERIFYING IS NOT OFFERED HERE:
 * that is the auditor's call, made from the web app — the whole point of the
 * two-step close is that the person who did the work is not the person who
 * signs it off.
 *
 * Runs on the PIN session with no web user, so every query names the company
 * and the employee explicitly — see StaffComponent.
 */
class CorrectiveActions extends StaffComponent
{
    use WithFileUploads, RejectsUnpreviewableUploads;

    public string $tab = 'mine';   // mine | outlet | done

    /** @var array<int, string> action id => note typed before a status change */
    public array $notes = [];

    /** @var array<int, mixed> action id => pending photo of the fix */
    public array $evidence = [];

    private function mine(int $id): CorrectiveAction
    {
        $employee = $this->staff();

        return CorrectiveAction::withoutGlobalScopes()
            ->where('company_id', $employee->company_id)
            ->where('owner_employee_id', $employee->id)
            ->findOrFail($id);
    }

    public function setStatus(int $id, string $status, CorrectiveActionService $actions): void
    {
        $action = $this->mine($id);

        abort_if($action->isVerified(), 403);
        abort_unless(in_array($status, [CorrectiveAction::STATUS_IN_PROGRESS, CorrectiveAction::STATUS_DONE], true), 422);

        $actions->setStatus($action, $status, $this->notes[$id] ?? null);
        unset($this->notes[$id]);

        session()->flash('success', $status === CorrectiveAction::STATUS_DONE
            ? 'Marked done. The auditor will verify it.'
            : 'Marked in progress.');
    }

    public function updatedEvidence($value, $key): void
    {
        $id     = (int) $key;
        $action = $this->mine($id);
        $file   = $this->keepPreviewableUpload($this->evidence[$id] ?? null, "evidence.{$id}");
        unset($this->evidence[$id]);

        if (! $file) {
            return;
        }

        try {
            validator(['photo' => $file], ['photo' => ImageStorageService::uploadRule(8192)], [
                'photo.mimes' => ImageStorageService::uploadMessage(),
                'photo.max'   => 'The photo may not be larger than 8 MB.',
            ])->validate();
        } catch (ValidationException $e) {
            $this->addError("evidence.{$id}", $e->validator->errors()->first());
            return;
        }

        try {
            app(CorrectiveActionService::class)->attachPhoto($action, $file, \App\Models\CorrectiveActionPhoto::KIND_EVIDENCE, $this->staff());
        } catch (ValidationException $e) {
            $this->addError("evidence.{$id}", $e->validator->errors()->first());
        }
    }

    /** Only the owner's own evidence photos, and only while the action is not yet verified. */
    public function removePhoto(int $photoId): void
    {
        $photo = \App\Models\CorrectiveActionPhoto::where('kind', \App\Models\CorrectiveActionPhoto::KIND_EVIDENCE)->findOrFail($photoId);
        $action = $this->mine((int) $photo->corrective_action_id);

        abort_if($action->isVerified(), 403);

        app(CorrectiveActionService::class)->removePhoto($photo);
    }

    public function render()
    {
        $employee = $this->staff();

        $base = CorrectiveAction::withoutGlobalScopes()
            ->with(['finding.audit', 'owner', 'photos'])
            ->where('company_id', $employee->company_id);

        $mine = (clone $base)
            ->where('owner_employee_id', $employee->id)
            ->outstanding()
            ->orderByRaw("case when due_date is null then 1 else 0 end")
            ->orderBy('due_date')
            ->get();

        $outlet = (clone $base)
            ->where('outlet_id', $employee->outlet_id)
            ->where(fn ($q) => $q->whereNull('owner_employee_id')->orWhere('owner_employee_id', '!=', $employee->id))
            ->outstanding()
            ->orderBy('due_date')
            ->get();

        $done = (clone $base)
            ->where('owner_employee_id', $employee->id)
            ->where('status', CorrectiveAction::STATUS_VERIFIED)
            ->latest('verified_at')
            ->limit(20)
            ->get();

        return view('livewire.staff.corrective-actions', [
            'employee' => $employee,
            'mine'     => $mine,
            'outlet'   => $outlet,
            'done'     => $done,
            'rows'     => match ($this->tab) { 'outlet' => $outlet, 'done' => $done, default => $mine },
        ])->layout('layouts.clock-staff', $this->shell('Audit fixes'));
    }
}
