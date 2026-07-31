<?php

namespace App\Livewire\Labels\Staff;

use App\Models\LabelPrint;
use App\Scopes\CompanyScope;
use App\Services\LabelExpiryService;
use Illuminate\Support\Carbon;

/**
 * What's about to go off, for the staff member's outlet.
 *
 * Full parity with the main app, wasting included. Every action records
 * the signed-in employee, so "who binned it" is answered by the PIN rather
 * than left blank.
 */
class Expiring extends StaffComponent
{
    public ?int $wastingId = null;

    public string $wasteQuantity = '1';

    public function mount(): void
    {
        $this->mountStaffDefaults();
    }

    public function markUsed(int $id, LabelExpiryService $service): void
    {
        if ($print = $this->find($id)) {
            $service->markUsed($print, $this->staff()->id);
            session()->flash('success', $print->printedName() . ' marked used.');
        }
    }

    public function markDiscarded(int $id, LabelExpiryService $service): void
    {
        if ($print = $this->find($id)) {
            $service->markDiscarded($print, $this->staff()->id);
            session()->flash('success', $print->printedName() . ' discarded.');
        }
    }

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
        $print = $this->wastingId ? $this->find($this->wastingId) : null;

        if (! $print) {
            $this->wastingId = null;

            return;
        }

        if (! is_numeric($this->wasteQuantity) || (float) $this->wasteQuantity <= 0) {
            $this->addError('wasteQuantity', 'Enter a quantity greater than zero.');

            return;
        }

        $record = $service->markWasted($print, (float) $this->wasteQuantity, $this->staff()->id);

        session()->flash('success', $record
            ? $print->printedName() . ' recorded as wastage.'
            : $print->printedName() . ' discarded (nothing priceable to cost it against).');

        $this->wastingId = null;
    }

    public function render(LabelExpiryService $service)
    {
        $now      = Carbon::now();
        $endToday = $now->copy()->endOfDay();
        $endTmrw  = $now->copy()->addDay()->endOfDay();

        $base = fn () => LabelPrint::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $this->staff()->company_id)
            ->where('outlet_id', $this->outletId())
            ->whereNull('resolved_at')
            ->whereNotNull('end_at')
            ->with('labelable')
            ->orderBy('end_at');

        $expired  = $base()->where('end_at', '<', $now)->limit(60)->get();
        $today    = $base()->whereBetween('end_at', [$now, $endToday])->limit(60)->get();
        $tomorrow = $base()->whereBetween('end_at', [$endToday, $endTmrw])->limit(60)->get();

        $costable = [];
        foreach ($expired->concat($today)->concat($tomorrow) as $print) {
            $costable[$print->id] = $service->costingFor($print);
        }

        return view('livewire.labels.staff.expiring', [
            'expired'  => $expired,
            'today'    => $today,
            'tomorrow' => $tomorrow,
            'costable' => $costable,
            'wasting'  => $this->wastingId ? $this->find($this->wastingId) : null,
        ])->layout('layouts.labels-staff', $this->shell('Expiring'));
    }

    /** Unresolved labels from this staff member's own outlet only. */
    private function find(int $id): ?LabelPrint
    {
        return LabelPrint::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $this->staff()->company_id)
            ->where('outlet_id', $this->outletId())
            ->whereNull('resolved_at')
            ->find($id);
    }
}
