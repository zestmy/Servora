<?php

namespace App\Livewire\Audits;

use App\Models\AuditFinding;
use App\Models\CorrectiveAction;
use App\Models\Employee;
use App\Services\Audits\CorrectiveActionService;
use App\Services\ImageStorageService;
use App\Traits\RejectsUnpreviewableUploads;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * The corrective actions under one finding: raise one, move it along,
 * verify it. Nested under the audit's summary so a finding card is one
 * component whatever screen shows it.
 */
class FindingActions extends Component
{
    use WithFileUploads, RejectsUnpreviewableUploads;

    #[Locked]
    public int $findingId;

    public bool   $adding      = false;
    public string $ownerId     = '';
    public string $description = '';
    public string $dueDate     = '';

    /** @var array<int, string> action id => note typed before a status change */
    public array $notes = [];

    /** @var array<int, mixed> action id => pending photo of the fix (owner's evidence) */
    public array $evidence = [];

    /** @var array<int, mixed> action id => pending verification photo (the auditor's) */
    public array $verification = [];

    public function mount(int $findingId): void
    {
        $this->findingId = $findingId;
    }

    private function finding(): AuditFinding
    {
        return AuditFinding::findOrFail($this->findingId);
    }

    private function action(int $id): CorrectiveAction
    {
        return CorrectiveAction::where('audit_finding_id', $this->findingId)->findOrFail($id);
    }

    private function requireManage(): void
    {
        abort_unless(Auth::user()?->canDo('audits.actions.manage'), 403);
    }

    public function startAdding(): void
    {
        $this->requireManage();
        $this->resetErrorBag();
        $this->adding  = true;
        $this->dueDate = now()->addDays(7)->toDateString();
    }

    public function cancelAdding(): void
    {
        $this->reset(['adding', 'ownerId', 'description', 'dueDate']);
    }

    public function add(CorrectiveActionService $actions): void
    {
        $this->requireManage();

        $this->validate([
            'ownerId'     => 'nullable|integer',
            'description' => 'required|string|max:1000',
            'dueDate'     => 'nullable|date',
        ], [
            'description.required' => 'Say what will be done.',
        ]);

        try {
            $actions->create($this->finding(), Auth::user(), [
                'owner_employee_id' => $this->ownerId ?: null,
                'description'       => $this->description,
                'due_date'          => $this->dueDate ?: null,
            ]);
        } catch (ValidationException $e) {
            $this->addError('ownerId', $e->validator->errors()->first());
            return;
        }

        $this->cancelAdding();
        $this->dispatch('audit-actions-changed');
    }

    public function setStatus(int $id, string $status, CorrectiveActionService $actions): void
    {
        $this->requireManage();
        $actions->setStatus($this->action($id), $status, $this->notes[$id] ?? null);
        unset($this->notes[$id]);
        $this->dispatch('audit-actions-changed');
    }

    public function verify(int $id, CorrectiveActionService $actions): void
    {
        $this->requireManage();
        $actions->verify($this->action($id), Auth::user(), $this->notes[$id] ?? null);
        unset($this->notes[$id]);
        $this->dispatch('audit-actions-changed');
    }

    public function unverify(int $id, CorrectiveActionService $actions): void
    {
        $this->requireManage();
        $actions->unverify($this->action($id));
        $this->dispatch('audit-actions-changed');
    }

    public function setOwner(int $id, string $ownerId, CorrectiveActionService $actions): void
    {
        $this->requireManage();

        try {
            $actions->update($this->action($id), ['owner_employee_id' => $ownerId ?: null]);
        } catch (ValidationException $e) {
            $this->addError('owner.' . $id, $e->validator->errors()->first());
        }
    }

    public function setDueDate(int $id, string $date, CorrectiveActionService $actions): void
    {
        $this->requireManage();
        $actions->update($this->action($id), ['due_date' => $date ?: null]);
    }

    public function updatedEvidence($value, $key): void
    {
        $this->storePhoto((int) $key, 'evidence', \App\Models\CorrectiveActionPhoto::KIND_EVIDENCE);
    }

    public function updatedVerification($value, $key): void
    {
        $this->storePhoto((int) $key, 'verification', \App\Models\CorrectiveActionPhoto::KIND_VERIFICATION);
    }

    private function storePhoto(int $id, string $property, string $kind): void
    {
        $this->requireManage();

        $file = $this->keepPreviewableUpload($this->{$property}[$id] ?? null, "{$property}.{$id}");
        unset($this->{$property}[$id]);

        if (! $file) return;

        try {
            validator(['photo' => $file], ['photo' => ImageStorageService::uploadRule(8192)], [
                'photo.mimes' => ImageStorageService::uploadMessage(),
                'photo.max'   => 'The photo may not be larger than 8 MB.',
            ])->validate();

            app(CorrectiveActionService::class)->attachPhoto($this->action($id), $file, $kind, Auth::user());
        } catch (ValidationException $e) {
            $this->addError("{$property}.{$id}", $e->validator->errors()->first());
        }
    }

    public function removePhoto(int $photoId, CorrectiveActionService $actions): void
    {
        $this->requireManage();

        $photo = \App\Models\CorrectiveActionPhoto::whereHas('action', fn ($q) => $q->where('audit_finding_id', $this->findingId))
            ->findOrFail($photoId);

        $actions->removePhoto($photo);
    }

    public function delete(int $id, CorrectiveActionService $actions): void
    {
        $this->requireManage();
        $actions->delete($this->action($id));
        $this->dispatch('audit-actions-changed');
    }

    public function render()
    {
        $finding = AuditFinding::with(['actions.owner', 'actions.verifiedBy', 'actions.photos'])->findOrFail($this->findingId);

        return view('livewire.audits.finding-actions', [
            'finding'   => $finding,
            'actions'   => $finding->actions,
            'employees' => Employee::where('outlet_id', $finding->outlet_id)->where('is_active', true)
                ->orderBy('name')->get(['id', 'name', 'designation']),
            'canManage' => Auth::user()->canDo('audits.actions.manage'),
            'statuses'  => CorrectiveAction::STATUSES,
        ]);
    }
}
