<?php

namespace App\Livewire\Labels;

use App\Models\LabelPrintBatch;
use App\Models\LabelSet;
use App\Models\Outlet;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * The audit trail — every label ever printed, grouped by print run.
 *
 * Reads the frozen payload on each line, never the live item. A recipe
 * renamed or re-dated since printing must not rewrite what the label said.
 */
class PrintLog extends Component
{
    use WithPagination;

    public ?int $outletId = null;

    public string $from = '';

    public string $to = '';

    /**
     * Print set filter. '' is every set, 'none' is ad-hoc runs, anything
     * else is a set id. Same convention as the expiring screen.
     */
    public string $setFilter = '';

    /** 'date' — newest run first, or 'set' — runs stacked under their set. */
    public string $groupBy = 'date';

    public ?int $expandedId = null;

    public function mount(): void
    {
        $this->outletId = Auth::user()->activeOutletId();
        $this->from     = Carbon::now()->subDays(7)->format('Y-m-d');
        $this->to       = Carbon::now()->format('Y-m-d');
    }

    public function updatedOutletId(): void
    {
        // Sets are outlet-owned, so a held filter stops meaning anything.
        $this->setFilter = '';
        $this->resetPage();
    }

    public function updatedSetFilter(): void
    {
        $this->resetPage();
    }

    public function updatedGroupBy(): void
    {
        $this->resetPage();
    }

    public function updatedFrom(): void
    {
        $this->resetPage();
    }

    public function updatedTo(): void
    {
        $this->resetPage();
    }

    public function toggle(int $id): void
    {
        $this->expandedId = $this->expandedId === $id ? null : $id;
    }

    public function render()
    {
        $batches = LabelPrintBatch::query()
            ->select('label_print_batches.*')
            ->with(['outlet', 'employee', 'labelSet', 'user'])
            // Columns are table-qualified throughout: grouping by set joins
            // label_sets, which also has outlet_id, and an unqualified
            // reference is an ambiguous-column error the moment that join is on.
            ->when($this->outletId, fn ($q) => $q->where('label_print_batches.outlet_id', $this->outletId))
            ->when($this->from !== '', fn ($q) => $q->whereDate('label_print_batches.printed_at', '>=', $this->from))
            ->when($this->to !== '', fn ($q) => $q->whereDate('label_print_batches.printed_at', '<=', $this->to))
            ->when($this->setFilter === 'none', fn ($q) => $q->whereNull('label_print_batches.label_set_id'))
            ->when($this->setFilter !== '' && $this->setFilter !== 'none',
                fn ($q) => $q->where('label_print_batches.label_set_id', (int) $this->setFilter))
            // Grouping by set keeps the pagination rather than pulling the
            // whole range into memory: an audit log is unbounded, so ordering
            // the query is what makes each page's groups contiguous. A group
            // spanning a page boundary simply repeats its heading.
            ->when($this->groupBy === 'set', fn ($q) => $q
                ->leftJoin('label_sets', 'label_sets.id', '=', 'label_print_batches.label_set_id')
                // Ad-hoc runs sort last here, unlike on the expiring screen:
                // nothing is at risk in a log, so alphabetical order with the
                // unnamed group at the end is easier to scan than urgency.
                ->orderByRaw('label_sets.name IS NULL')
                ->orderBy('label_sets.name')
                ->orderByDesc('label_print_batches.printed_at'))
            ->when($this->groupBy !== 'set', fn ($q) => $q->orderByDesc('label_print_batches.printed_at'))
            ->paginate(20);

        $expanded = $this->expandedId
            ? LabelPrintBatch::with('prints')->find($this->expandedId)
            : null;

        return view('livewire.labels.print-log', [
            'batches'  => $batches,
            'expanded' => $expanded,
            'outlets'  => Outlet::where('company_id', Auth::user()->company_id)->orderBy('name')->get(),
            'sets'     => LabelSet::query()
                ->when($this->outletId, fn ($q) => $q->where('outlet_id', $this->outletId))
                ->orderBy('name')->get(),
        ])->layout(\App\Helpers\WorkspaceLayout::get(), ['title' => 'Label print log']);
    }
}
