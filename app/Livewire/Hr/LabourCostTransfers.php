<?php

namespace App\Livewire\Hr;

use App\Models\LabourCostTransfer;
use App\Models\Outlet;
use App\Services\Hr\LabourCostTransferCalculator;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Every labour cost transfer in a period, and what they add up to per outlet.
 *
 * The period summary counts CONFIRMED transfers only: a draft has not been
 * agreed and a cancelled one never will be, the same rule the stock transfer
 * figures follow for drafts.
 */
class LabourCostTransfers extends Component
{
    use WithPagination;

    public string $from   = '';
    public string $to     = '';
    public string $outlet = '';
    public string $status = '';

    public function mount(): void
    {
        abort_unless(Auth::user()?->canDo('hr.compensation'), 403);

        $this->from = now()->startOfMonth()->toDateString();
        $this->to   = now()->endOfMonth()->toDateString();
    }

    public function updating($name): void
    {
        if (in_array($name, ['from', 'to', 'outlet', 'status'], true)) {
            $this->resetPage();
        }
    }

    /** Transfers touching an outlet this user can see, at either end. */
    private function query()
    {
        $ids = $this->outlet !== '' ? array_intersect([(int) $this->outlet], Auth::user()->accessibleOutletIds())
                                    : Auth::user()->accessibleOutletIds();

        return LabourCostTransfer::query()
            ->when($this->from, fn ($q) => $q->whereDate('transfer_date', '>=', $this->from))
            ->when($this->to, fn ($q) => $q->whereDate('transfer_date', '<=', $this->to))
            ->where(fn ($q) => $q->whereIn('to_outlet_id', $ids ?: [0])
                ->orWhereHas('lines', fn ($l) => $l->whereIn('from_outlet_id', $ids ?: [0])));
    }

    public function render()
    {
        $transfers = $this->query()
            ->when($this->status, fn ($q) => $q->where('status', $this->status))
            ->with('toOutlet')
            ->withCount('lines')
            ->withSum('lines', 'total_amount')
            ->orderByDesc('transfer_date')
            ->orderByDesc('id')
            ->paginate(20);

        $confirmed = $this->query()->where('status', 'confirmed')->with('lines')->get();
        $summary   = LabourCostTransferCalculator::summaryByOutlet(LabourCostTransferCalculator::rowsFromTransfers($confirmed));

        $outletNames = Outlet::withoutGlobalScopes()->whereIn('id', array_column($summary, 'outlet_id'))->pluck('name', 'id');
        $outlets     = Outlet::whereIn('id', Auth::user()->accessibleOutletIds())->orderBy('name')->get();

        return view('livewire.hr.labour-cost-transfers', compact('transfers', 'summary', 'outletNames', 'outlets'))
            ->layout(\App\Helpers\WorkspaceLayout::get(), ['title' => 'Labour Cost Transfer']);
    }
}
