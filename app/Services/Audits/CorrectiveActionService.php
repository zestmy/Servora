<?php

namespace App\Services\Audits;

use App\Models\AuditFinding;
use App\Models\CorrectiveAction;
use App\Models\Employee;
use App\Models\User;
use App\Services\ImageStorageService;
use Illuminate\Validation\ValidationException;

/**
 * Corrective actions: raise one against a finding, move it along, verify it.
 *
 * Shared by the audit screen (actions raised while reading the findings) and
 * the Corrective Actions summary (actions updated from the list), so the two
 * cannot disagree about what a status change means for the finding and the
 * audit above it.
 */
class CorrectiveActionService
{
    public function __construct(private AuditService $audits)
    {
    }

    public function create(AuditFinding $finding, User $by, array $data): CorrectiveAction
    {
        $owner = null;

        if (! empty($data['owner_employee_id'])) {
            $owner = Employee::where('outlet_id', $finding->outlet_id)->find((int) $data['owner_employee_id']);

            if (! $owner) {
                throw ValidationException::withMessages(['owner_employee_id' => 'Pick someone who works at this outlet.']);
            }
        }

        $action = CorrectiveAction::create([
            'company_id'        => $finding->company_id,
            'outlet_id'         => $finding->outlet_id,
            'audit_finding_id'  => $finding->id,
            'owner_employee_id' => $owner?->id,
            'description'       => trim($data['description']),
            'due_date'          => $data['due_date'] ?: null,
            'status'            => CorrectiveAction::STATUS_OPEN,
            'created_by'        => $by->id,
        ]);

        $this->audits->reviewClosure($finding->fresh());

        return $action;
    }

    /**
     * open ⇄ in_progress ⇄ done. Verified is reached only through verify(),
     * and leaving verified goes through unverify(), because those two are the
     * auditor's calls and the others are the outlet's.
     */
    public function setStatus(CorrectiveAction $action, string $status, ?string $note = null): CorrectiveAction
    {
        abort_unless(in_array($status, [
            CorrectiveAction::STATUS_OPEN, CorrectiveAction::STATUS_IN_PROGRESS, CorrectiveAction::STATUS_DONE,
        ], true), 422);

        abort_if($action->isVerified(), 422, 'Un-verify this action before changing it.');

        $action->forceFill([
            'status'          => $status,
            'completed_at'    => $status === CorrectiveAction::STATUS_DONE ? ($action->completed_at ?? now()) : null,
            'completion_note' => $note !== null ? (trim($note) ?: null) : $action->completion_note,
        ])->save();

        $this->audits->reviewClosure($action->finding()->withoutGlobalScopes()->first());

        return $action;
    }

    public function verify(CorrectiveAction $action, User $by, ?string $note = null): CorrectiveAction
    {
        $action->forceFill([
            'status'            => CorrectiveAction::STATUS_VERIFIED,
            'completed_at'      => $action->completed_at ?? now(),
            'verified_by'       => $by->id,
            'verified_at'       => now(),
            'verification_note' => trim((string) $note) ?: null,
        ])->save();

        $this->audits->reviewClosure($action->finding()->withoutGlobalScopes()->first());

        return $action;
    }

    public function unverify(CorrectiveAction $action): CorrectiveAction
    {
        $action->forceFill([
            'status'            => CorrectiveAction::STATUS_DONE,
            'verified_by'       => null,
            'verified_at'       => null,
            'verification_note' => null,
        ])->save();

        $this->audits->reviewClosure($action->finding()->withoutGlobalScopes()->first());

        return $action;
    }

    public function update(CorrectiveAction $action, array $data): CorrectiveAction
    {
        if (array_key_exists('owner_employee_id', $data)) {
            $ownerId = $data['owner_employee_id'] ? (int) $data['owner_employee_id'] : null;

            if ($ownerId && ! Employee::where('outlet_id', $action->outlet_id)->whereKey($ownerId)->exists()) {
                throw ValidationException::withMessages(['owner_employee_id' => 'Pick someone who works at this outlet.']);
            }

            $action->owner_employee_id = $ownerId;
        }

        if (array_key_exists('description', $data) && trim((string) $data['description']) !== '') {
            $action->description = trim($data['description']);
        }

        if (array_key_exists('due_date', $data)) {
            $action->due_date = $data['due_date'] ?: null;
        }

        $action->save();

        return $action;
    }

    /** A photo of the fix, stored the same way as the finding's photos. */
    public function attachEvidence(CorrectiveAction $action, $upload): CorrectiveAction
    {
        $replaced = $action->evidence_path;

        $action->forceFill([
            'evidence_path' => ImageStorageService::storeCompressed(
                $upload, 'audit-photos/' . $action->company_id . '/evidence', 'public'
            ),
        ])->save();

        if ($replaced) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($replaced);
        }

        return $action;
    }

    public function delete(CorrectiveAction $action): void
    {
        $finding = $action->finding()->withoutGlobalScopes()->first();

        $action->delete();

        if ($finding) {
            $this->audits->reviewClosure($finding);
        }
    }
}
