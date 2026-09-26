<?php

namespace App\Livewire\Audits;

use App\Models\Audit;
use App\Models\AuditSchedule;
use App\Models\AuditTemplate;
use App\Models\User;
use App\Traits\ScopesToActiveOutlet;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * The audit calendar: which outlet is due which form, and when.
 *
 * Sorted by due date with the overdue ones on top, because that is the only
 * order in which this list is ever read. "Start now" goes straight into a new
 * audit and rolls the row forward — see AuditService::startFromSchedule.
 */
class Schedules extends Component
{
    use ScopesToActiveOutlet;

    public string $filter = 'active';   // active | overdue | all

    // Editor
    public bool   $showForm    = false;
    public ?int   $editingId   = null;
    public string $templateId  = '';
    public string $outletId    = '';
    public string $frequency   = 'quarterly';
    public string $nextDueOn   = '';
    public string $assigneeId  = '';
    public string $notes       = '';
    public bool   $isActive    = true;

    /** The Audits due strip and the reminder email both deep-link to the overdue view. */
    public function mount(): void
    {
        if (in_array(request('filter'), ['active', 'overdue', 'all'], true)) {
            $this->filter = request('filter');
        }
    }

    private function requireManage(): void
    {
        abort_unless(Auth::user()?->canDo('audits.manage'), 403);
    }

    public function openCreate(): void
    {
        $this->requireManage();
        $this->reset(['editingId', 'templateId', 'outletId', 'assigneeId', 'notes']);
        $this->frequency = 'quarterly';
        $this->nextDueOn = now()->addMonth()->startOfMonth()->toDateString();
        $this->isActive  = true;
        $this->resetErrorBag();
        $this->showForm = true;

        $templates = AuditTemplate::active()->ordered()->get(['id']);
        if ($templates->count() === 1) {
            $this->templateId = (string) $templates->first()->id;
        }
    }

    public function openEdit(int $id): void
    {
        $this->requireManage();

        $s = $this->schedule($id);

        $this->editingId  = $s->id;
        $this->templateId = (string) $s->audit_template_id;
        $this->outletId   = (string) $s->outlet_id;
        $this->frequency  = $s->frequency;
        $this->nextDueOn  = $s->next_due_on->toDateString();
        $this->assigneeId = (string) ($s->assigned_user_id ?? '');
        $this->notes      = $s->notes ?? '';
        $this->isActive   = $s->is_active;
        $this->resetErrorBag();
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->requireManage();

        $this->validate([
            'templateId' => 'required|integer',
            'outletId'   => 'required|integer',
            'frequency'  => 'in:' . implode(',', array_keys(AuditSchedule::FREQUENCIES)),
            'nextDueOn'  => 'required|date',
            'assigneeId' => 'nullable|integer',
            'notes'      => 'nullable|string|max:500',
        ], [
            'templateId.required' => 'Choose the audit form.',
            'outletId.required'   => 'Choose the outlet.',
            'nextDueOn.required'  => 'When is it next due?',
        ]);

        abort_unless(in_array((int) $this->outletId, $this->availableOutletIds(), true), 403);
        AuditTemplate::findOrFail((int) $this->templateId);

        $assignee = $this->assigneeId
            ? User::where('company_id', Auth::user()->company_id)->find((int) $this->assigneeId)
            : null;

        $data = [
            'audit_template_id' => (int) $this->templateId,
            'outlet_id'         => (int) $this->outletId,
            'frequency'         => $this->frequency,
            'next_due_on'       => $this->nextDueOn,
            'assigned_user_id'  => $assignee?->id,
            'notes'             => trim($this->notes) ?: null,
            'is_active'         => $this->isActive,
        ];

        if ($this->editingId) {
            $this->schedule($this->editingId)->update($data);
        } else {
            AuditSchedule::create($data + [
                'company_id' => Auth::user()->company_id,
                'created_by' => Auth::id(),
            ]);
        }

        $this->showForm = false;
        session()->flash('success', 'Schedule saved.');
    }

    public function delete(int $id): void
    {
        abort_unless(Auth::user()?->canDo('audits.delete'), 403);
        $this->schedule($id)->delete();
        session()->flash('success', 'Schedule removed. Audits already conducted from it are untouched.');
    }

    /** Straight into a new audit from this row; the Start screen does the roll. */
    public function startNow(int $id)
    {
        abort_unless(Auth::user()?->canDo('audits.conduct'), 403);
        $this->schedule($id);

        return $this->redirect(route('audits.start', ['schedule' => $id]), navigate: true);
    }

    private function schedule(int $id): AuditSchedule
    {
        $s = AuditSchedule::findOrFail($id);
        abort_unless(Auth::user()->canAccessOutlet($s->outlet_id), 403);

        return $s;
    }

    public function render()
    {
        $query = AuditSchedule::with(['template', 'outlet', 'assignee', 'lastAudit']);
        $this->scopeByOutlet($query);

        match ($this->filter) {
            'overdue' => $query->overdue(),
            'all'     => null,
            default   => $query->active(),
        };

        $schedules = $query->orderBy('next_due_on')->get();

        /*
         * Re-audits owed by conditional passes sit in the same list, because
         * "what is due at which outlet" is one question. They are not
         * schedules — they have no frequency and roll nothing forward — so
         * they are rows of their own kind, settled by submitting the follow-up.
         */
        $reauditQuery = Audit::with(['outlet', 'auditor'])->reauditOutstanding();
        $this->scopeByOutlet($reauditQuery);

        if ($this->filter === 'overdue') {
            $reauditQuery->whereDate('reaudit_due_on', '<', now()->toDateString());
        }

        $reaudits = $reauditQuery->orderBy('reaudit_due_on')->get();

        $rows = $schedules->map(fn ($s) => ['kind' => 'schedule', 'due' => $s->next_due_on, 'row' => $s])
            ->concat($reaudits->map(fn ($a) => ['kind' => 'reaudit', 'due' => $a->reaudit_due_on, 'row' => $a]))
            ->sortBy(fn ($r) => $r['due']->toDateString() . ($r['kind'] === 'reaudit' ? '-0' : '-1'))
            ->values();

        $overdueSchedules = AuditSchedule::query()->tap(fn ($q) => $this->scopeByOutlet($q))->overdue()->count();
        $overdueReaudits  = Audit::query()->tap(fn ($q) => $this->scopeByOutlet($q))->reauditOverdue()->count();

        return view('livewire.audits.schedules', [
            'rows'        => $rows,
            'schedules'   => $schedules,
            'templates'   => AuditTemplate::active()->ordered()->get(['id', 'name', 'code']),
            'outlets'     => \App\Models\Outlet::whereIn('id', $this->availableOutletIds())->orderBy('name')->get(['id', 'name']),
            'auditors'    => User::where('company_id', Auth::user()->company_id)->orderBy('name')->get(['id', 'name']),
            'frequencies' => AuditSchedule::FREQUENCIES,
            'canManage'   => Auth::user()->canDo('audits.manage'),
            'canConduct'  => Auth::user()->canDo('audits.conduct'),
            'overdueCount' => $overdueSchedules + $overdueReaudits,
            'reauditCount' => $reaudits->count(),
        ])->layout(\App\Helpers\WorkspaceLayout::get(), ['title' => 'Audit Schedule']);
    }
}
