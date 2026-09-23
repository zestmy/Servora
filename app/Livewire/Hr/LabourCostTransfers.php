<?php

namespace App\Livewire\Hr;

use App\Models\LabourCostTransfer;
use App\Models\Outlet;
use App\Services\Hr\LabourCostTransferCalculator;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
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

    // In the URL so a report can link straight to one outlet's month.
    #[Url] public string $from   = '';
    #[Url] public string $to     = '';
    #[Url] public string $outlet = '';
    #[Url] public string $status = '';

    public function mount(): void
    {
        abort_unless(Auth::user()?->canDo('hr.compensation'), 403);

        $this->from = $this->from ?: now()->startOfMonth()->toDateString();
        $this->to   = $this->to ?: now()->endOfMonth()->toDateString();
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

        return LabourCostTransfer::query()->forPeriod($this->from, $this->to, array_values($ids));
    }

    /**
     * Same rule as the form: a draft is anyone's to delete, a confirmed or
     * cancelled transfer needs hr.compensation.transfers.manage. Only
     * transfers this user can see (forPeriod's outlet scope) are reachable.
     */
    public function deleteTransfer(int $id): void
    {
        abort_unless(Auth::user()?->canDo('hr.compensation'), 403);

        $transfer = LabourCostTransfer::query()
            ->forPeriod(null, null, Auth::user()->accessibleOutletIds())
            ->findOrFail($id);

        abort_unless($transfer->status === 'draft' || Auth::user()->canDo('hr.compensation.transfers.manage'), 403);

        $transfer->delete();
        session()->flash('success', 'Labour cost transfer ' . $transfer->transfer_number . ' deleted.');
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

        $canManage = Auth::user()->canDo('hr.compensation.transfers.manage');

        return view('livewire.hr.labour-cost-transfers', compact('transfers', 'summary', 'outletNames', 'outlets', 'canManage'))
            ->layout(\App\Helpers\WorkspaceLayout::get(), ['title' => 'Labour Cost Transfer']);
    }
}
