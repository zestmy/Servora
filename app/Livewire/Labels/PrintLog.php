<?php

namespace App\Livewire\Labels;

use App\Models\LabelPrintBatch;
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

    public ?int $expandedId = null;

    public function mount(): void
    {
        $this->outletId = Auth::user()->activeOutletId();
        $this->from     = Carbon::now()->subDays(7)->format('Y-m-d');
        $this->to       = Carbon::now()->format('Y-m-d');
    }

    public function updatedOutletId(): void
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
        $batches = LabelPrintBatch::with(['outlet', 'employee', 'labelSet', 'user'])
            ->when($this->outletId, fn ($q) => $q->where('outlet_id', $this->outletId))
            ->when($this->from !== '', fn ($q) => $q->whereDate('printed_at', '>=', $this->from))
            ->when($this->to !== '', fn ($q) => $q->whereDate('printed_at', '<=', $this->to))
            ->orderByDesc('printed_at')
            ->paginate(20);

        $expanded = $this->expandedId
            ? LabelPrintBatch::with('prints')->find($this->expandedId)
            : null;

        return view('livewire.labels.print-log', [
            'batches'  => $batches,
            'expanded' => $expanded,
            'outlets'  => Outlet::where('company_id', Auth::user()->company_id)->orderBy('name')->get(),
        ])->layout(\App\Helpers\WorkspaceLayout::get(), ['title' => 'Label print log']);
    }
}
