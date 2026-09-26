<?php

namespace App\Livewire\Audits;

use App\Models\AuditFinding;
use App\Models\CorrectiveAction;
use App\Models\CorrectiveActionPhoto;
use App\Models\Employee;
use App\Services\Audits\CorrectiveActionService;
use App\Services\ImageStorageService;
use App\Traits\RejectsUnpreviewableUploads;
use App\Traits\RemembersListFilters;
use App\Traits\ScopesToActiveOutlet;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Every non-conformance across the outlets and what is being done about it,
 * grouped by outlet and then by who owns the fix — the screen a QA manager
 * opens on Monday morning.
 *
 * Findings with NO action yet are listed too, at the top of their outlet,
 * because "nobody has taken this up" is the most urgent state there is and a
 * list of actions would hide it.
 */
class Actions extends Component
{
    use ScopesToActiveOutlet, RemembersListFilters, WithFileUploads, RejectsUnpreviewableUploads;

    public string $outletFilter = '';
    public string $statusFilter = 'outstanding';   // outstanding | overdue | unassigned | verified | all
    public string $ownerFilter  = '';
    public string $severityFilter = '';
    public string $search       = '';

    /** @var array<int, mixed> action id => pending photo of the fix */
    public array $evidence = [];

    /** @var array<int, mixed> action id => pending verification photo */
    public array $verification = [];

    protected function rememberedFilters(): array
    {
        return ['outletFilter', 'statusFilter', 'severityFilter'];
    }

    public function mount(): void
    {
        $this->bootRememberedFilters();

        // A deep link (the reminder email, the due strip) beats the remembered filter.
        if (in_array(request('status'), ['outstanding', 'overdue', 'unassigned', 'verified', 'all'], true)) {
            $this->statusFilter = request('status');
        }
    }

    public function resetFilters(): void
    {
        $this->reset(['outletFilter', 'ownerFilter', 'severityFilter', 'search']);
        $this->statusFilter = 'outstanding';
    }

    public function setStatus(int $id, string $status, CorrectiveActionService $actions): void
    {
        abort_unless(Auth::user()?->canDo('audits.actions.manage'), 403);
        $actions->setStatus($this->action($id), $status);
    }

    public function verify(int $id, CorrectiveActionService $actions): void
    {
        abort_unless(Auth::user()?->canDo('audits.actions.manage'), 403);
        $actions->verify($this->action($id), Auth::user());
    }

    public function updatedEvidence($value, $key): void
    {
        $this->storePhoto((int) $key, 'evidence', CorrectiveActionPhoto::KIND_EVIDENCE);
    }

    public function updatedVerification($value, $key): void
    {
        $this->storePhoto((int) $key, 'verification', CorrectiveActionPhoto::KIND_VERIFICATION);
    }

    /** Same rules as the finding card: previewable, under 8 MB, six per kind. */
    private function storePhoto(int $id, string $property, string $kind): void
    {
        abort_unless(Auth::user()?->canDo('audits.actions.manage'), 403);

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

    private function action(int $id): CorrectiveAction
    {
        $action = CorrectiveAction::findOrFail($id);
        abort_unless(Auth::user()->canAccessOutlet($action->outlet_id), 403);

        return $action;
    }

    public function render()
    {
        $outletIds = $this->availableOutletIds();

        $actions = CorrectiveAction::query()
            ->with(['owner', 'finding.audit', 'outlet', 'photos'])
            ->when($outletIds, fn ($q) => $q->whereIn('outlet_id', $outletIds))
            ->when($this->outletFilter, fn ($q) => $q->where('outlet_id', (int) $this->outletFilter))
            ->when($this->ownerFilter, fn ($q) => $q->where('owner_employee_id', (int) $this->ownerFilter))
            ->when($this->severityFilter, fn ($q) => $q->whereHas('finding', fn ($f) => $f->where('severity', $this->severityFilter)))
            ->when($this->search !== '', function ($q) {
                $term = '%' . $this->search . '%';
                $q->where(fn ($w) => $w->where('description', 'like', $term)
                    ->orWhereHas('finding', fn ($f) => $f->where('item_label', 'like', $term)));
            });

        switch ($this->statusFilter) {
            case 'overdue':     $actions->overdue(); break;
            case 'verified':    $actions->where('status', CorrectiveAction::STATUS_VERIFIED); break;
            case 'all':         break;
            case 'unassigned':  $actions->whereRaw('1 = 0'); break; // only the finding list below applies
            default:            $actions->outstanding();
        }

        $actions = $actions->orderBy('due_date')->orderBy('id')->get();

        // Findings nobody has raised an action against yet, on submitted audits.
        $unassigned = collect();
        if (in_array($this->statusFilter, ['outstanding', 'overdue', 'unassigned'], true)) {
            $unassigned = AuditFinding::query()
                ->with(['audit', 'outlet'])
                ->doesntHave('actions')
                ->whereHas('audit', fn ($a) => $a->where('status', '!=', 'draft'))
                ->when($outletIds, fn ($q) => $q->whereIn('outlet_id', $outletIds))
                ->when($this->outletFilter, fn ($q) => $q->where('outlet_id', (int) $this->outletFilter))
                ->when($this->severityFilter, fn ($q) => $q->where('severity', $this->severityFilter))
                ->when($this->search !== '', fn ($q) => $q->where('item_label', 'like', '%' . $this->search . '%'))
                ->when($this->ownerFilter, fn ($q) => $q->whereRaw('1 = 0'))
                ->orderByDesc('id')
                ->get();
        }

        // outlet => [ 'unassigned' => [...], 'owners' => [ designation/name => [actions] ] ]
        $groups = [];

        foreach ($unassigned as $finding) {
            $groups[$finding->outlet_id]['outlet']       = $finding->outlet;
            $groups[$finding->outlet_id]['unassigned'][] = $finding;
        }

        foreach ($actions as $action) {
            $ownerKey = $action->owner
                ? trim(($action->owner->designation ?: 'Staff') . ' · ' . $action->owner->name)
                : 'Unassigned owner';

            $groups[$action->outlet_id]['outlet'] = $action->outlet;
            $groups[$action->outlet_id]['owners'][$ownerKey][] = $action;
        }

        uasort($groups, fn ($a, $b) => strcmp($a['outlet']?->name ?? '', $b['outlet']?->name ?? ''));

        $counts = [
            'outstanding' => CorrectiveAction::query()->when($outletIds, fn ($q) => $q->whereIn('outlet_id', $outletIds))->outstanding()->count(),
            'overdue'     => CorrectiveAction::query()->when($outletIds, fn ($q) => $q->whereIn('outlet_id', $outletIds))->overdue()->count(),
            'unassigned'  => AuditFinding::query()->doesntHave('actions')
                ->whereHas('audit', fn ($a) => $a->where('status', '!=', 'draft'))
                ->when($outletIds, fn ($q) => $q->whereIn('outlet_id', $outletIds))->count(),
        ];

        return view('livewire.audits.actions', [
            'groups'    => $groups,
            'counts'    => $counts,
            'outlets'   => $this->filterableOutlets(),
            'owners'    => Employee::query()
                ->when($outletIds, fn ($q) => $q->whereIn('outlet_id', $outletIds))
                ->when($this->outletFilter, fn ($q) => $q->where('outlet_id', (int) $this->outletFilter))
                ->whereIn('id', CorrectiveAction::query()->whereNotNull('owner_employee_id')->select('owner_employee_id'))
                ->orderBy('name')->get(['id', 'name', 'designation']),
            'canManage' => Auth::user()->canDo('audits.actions.manage'),
            'statuses'  => CorrectiveAction::STATUSES,
        ])->layout(\App\Helpers\WorkspaceLayout::get(), ['title' => 'Corrective Actions']);
    }
}
