<?php

namespace App\Livewire\Audits;

use App\Models\Audit;
use App\Models\AuditSchedule;
use App\Models\AuditTemplate;
use App\Traits\HasQuickDateRanges;
use App\Traits\RemembersListFilters;
use App\Traits\ScopesToActiveOutlet;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Every outlet audit, newest first, with its score and how many findings are
 * still open. The score column reads the cached percentage — nothing here
 * touches audit lines.
 */
class Index extends Component
{
    use WithPagination, ScopesToActiveOutlet, RemembersListFilters, HasQuickDateRanges;

    public string $search         = '';
    public string $outletFilter   = '';
    public string $templateFilter = '';
    public string $statusFilter   = '';
    public string $outcomeFilter  = '';
    public string $dateFrom       = '';
    public string $dateTo         = '';

    protected function rememberedFilters(): array
    {
        return ['outletFilter', 'templateFilter', 'statusFilter', 'outcomeFilter', 'quickRange', 'dateFrom', 'dateTo'];
    }

    protected function defaultQuickRange(): string
    {
        return 'all';
    }

    public function mount(): void
    {
        $this->bootRememberedFilters();
        $this->bootQuickRange();
    }

    public function updatedSearch(): void         { $this->resetPage(); }
    public function updatedOutletFilter(): void   { $this->resetPage(); }
    public function updatedTemplateFilter(): void { $this->resetPage(); }
    public function updatedStatusFilter(): void   { $this->resetPage(); }
    public function updatedOutcomeFilter(): void  { $this->resetPage(); }
    public function updatedDateFrom(): void       { $this->quickRange = ''; $this->resetPage(); }
    public function updatedDateTo(): void         { $this->quickRange = ''; $this->resetPage(); }

    public function resetFilters(): void
    {
        $this->reset(['search', 'outletFilter', 'templateFilter', 'statusFilter', 'outcomeFilter']);
        $this->setQuickRange($this->defaultQuickRange());
    }

    public function delete(int $id): void
    {
        abort_unless(Auth::user()?->canDo('audits.delete'), 403);

        $audit = Audit::findOrFail($id);
        abort_unless(Auth::user()->canAccessOutlet($audit->outlet_id), 403);

        $audit->delete();

        session()->flash('success', 'Audit deleted.');
    }

    public function render()
    {
        $query = Audit::query()
            ->with(['outlet', 'auditor'])
            ->withCount(['findings as open_findings_count' => fn ($q) => $q->where('status', 'open')]);

        $this->scopeByOutlet($query);

        if ($this->outletFilter) {
            $query->where('outlet_id', (int) $this->outletFilter);
        }
        if ($this->templateFilter) {
            $query->where('audit_template_id', (int) $this->templateFilter);
        }
        if ($this->statusFilter) {
            $query->where('status', $this->statusFilter);
        }
        if ($this->outcomeFilter === 'reaudit_due') {
            $query->reauditOutstanding();
        } elseif ($this->outcomeFilter) {
            $query->where('status', '!=', Audit::STATUS_DRAFT)->where('outcome', $this->outcomeFilter);
        }
        if ($this->dateFrom) {
            $query->whereDate('audit_date', '>=', $this->dateFrom);
        }
        if ($this->dateTo) {
            $query->whereDate('audit_date', '<=', $this->dateTo);
        }
        if ($this->search !== '') {
            $term = '%' . $this->search . '%';
            $query->where(function ($q) use ($term) {
                $q->where('template_name', 'like', $term)
                  ->orWhere('reference_number', 'like', $term)
                  ->orWhereHas('outlet', fn ($o) => $o->where('name', 'like', $term));
            });
        }

        // The due strip. Two counts, not the rows — the Schedule screen has those.
        $due = AuditSchedule::query();
        $this->scopeByOutlet($due);

        // Conditional passes waiting for their re-audit, in the same strip.
        $reaudit = Audit::query();
        $this->scopeByOutlet($reaudit);

        return view('livewire.audits.index', [
            'overdue'   => (clone $due)->overdue()->count(),
            'dueSoon'   => (clone $due)->dueSoon()->count(),
            'reauditOverdue' => (clone $reaudit)->reauditOverdue()->count(),
            'reauditDue'     => (clone $reaudit)->reauditOutstanding()->count(),
            'audits'    => $query->orderByDesc('audit_date')->orderByDesc('id')->paginate(25),
            'outlets'   => $this->filterableOutlets(),
            'templates' => AuditTemplate::ordered()->get(['id', 'name', 'code']),
            'statuses'  => Audit::STATUSES,
            'outcomes'  => Audit::OUTCOMES,
            'quickRangeOptions' => static::quickRangeOptions(),
        ])->layout(\App\Helpers\WorkspaceLayout::get(), ['title' => 'Audits']);
    }
}
