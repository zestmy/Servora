<?php

namespace App\Livewire\Labels;

use App\Models\LabelPrint;
use App\Models\Outlet;
use App\Services\LabelExpiryService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * What's about to go off.
 *
 * Reads unresolved labels off label_prints — which is only possible because
 * every print writes its computed use-by. This is the payoff for keeping
 * the print log: the same rows that satisfy an auditor also tell a chef
 * what to use first.
 *
 * Every row must be closable, or the screen silts up with last month's
 * expired labels and stops being looked at.
 */
class Expiring extends Component
{
    public ?int $outletId = null;

    /** Label being binned, while the quantity is confirmed. */
    public ?int $wastingId = null;

    public string $wasteQuantity = '1';

    public function mount(): void
    {
        $this->outletId = Auth::user()->activeOutletId();
    }

    public function markUsed(int $id, LabelExpiryService $service): void
    {
        $print = LabelPrint::unresolved()->find($id);

        if ($print) {
            $service->markUsed($print);
            session()->flash('success', $print->printedName() . ' marked used.');
        }
    }

    public function markDiscarded(int $id, LabelExpiryService $service): void
    {
        $print = LabelPrint::unresolved()->find($id);

        if ($print) {
            $service->markDiscarded($print);
            session()->flash('success', $print->printedName() . ' discarded.');
        }
    }

    /** Costable items get a quantity prompt before they become wastage. */
    public function openWaste(int $id): void
    {
        $this->wastingId     = $id;
        $this->wasteQuantity = '1';
        $this->resetValidation();
    }

    public function closeWaste(): void
    {
        $this->wastingId = null;
    }

    public function confirmWaste(LabelExpiryService $service): void
    {
        $print = LabelPrint::unresolved()->find($this->wastingId);

        if (! $print) {
            $this->wastingId = null;

            return;
        }

        if (! is_numeric($this->wasteQuantity) || (float) $this->wasteQuantity <= 0) {
            $this->addError('wasteQuantity', 'Enter a quantity greater than zero.');

            return;
        }

        $record = $service->markWasted($print, (float) $this->wasteQuantity);

        session()->flash('success', $record
            ? $print->printedName() . ' recorded as wastage on ' . $record->reference_number . '.'
            : $print->printedName() . ' discarded (nothing priceable to cost it against).');

        $this->wastingId = null;
    }

    public function render(LabelExpiryService $service)
    {
        $now      = Carbon::now();
        $endToday = $now->copy()->endOfDay();
        $endTmrw  = $now->copy()->addDay()->endOfDay();

        $base = fn () => LabelPrint::unresolved()
            ->whereNotNull('end_at')
            ->when($this->outletId, fn ($q) => $q->where('outlet_id', $this->outletId))
            ->with(['labelable', 'batch.employee'])
            ->orderBy('end_at');

        $expired  = $base()->where('end_at', '<', $now)->limit(100)->get();
        $today    = $base()->whereBetween('end_at', [$now, $endToday])->limit(100)->get();
        $tomorrow = $base()->whereBetween('end_at', [$endToday, $endTmrw])->limit(100)->get();

        // Which rows can actually become costed wastage — drives whether a
        // row offers "Wasted" or only "Discarded".
        $costable = [];
        foreach ($expired->concat($today)->concat($tomorrow) as $print) {
            $costable[$print->id] = $service->costingFor($print);
        }

        return view('livewire.labels.expiring', [
            'expired'  => $expired,
            'today'    => $today,
            'tomorrow' => $tomorrow,
            'costable' => $costable,
            'outlets'  => Outlet::where('company_id', Auth::user()->company_id)->orderBy('name')->get(),
            'wasting'  => $this->wastingId ? LabelPrint::find($this->wastingId) : null,
        ])->layout(\App\Helpers\WorkspaceLayout::get(), ['title' => 'Expiring']);
    }
}
