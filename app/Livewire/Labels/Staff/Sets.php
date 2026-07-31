<?php

namespace App\Livewire\Labels\Staff;

use App\Models\LabelSet;
use App\Scopes\CompanyScope;

/**
 * Print sets for the staff member's outlet.
 *
 * Read-only: staff print sets, managers build them. Editing a set on a
 * phone mid-shift is not a thing anyone wants to do, and a mis-tap would
 * change what the whole outlet prints tomorrow.
 */
class Sets extends StaffComponent
{
    public function mount(): void
    {
        $this->mountStaffDefaults();
    }

    public function render()
    {
        $sets = LabelSet::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $this->staff()->company_id)
            ->where('outlet_id', $this->outletId())
            ->where('is_active', true)
            ->withCount('lines')
            ->orderBy('sort_order')->orderBy('name')
            ->get();

        return view('livewire.labels.staff.sets', [
            'sets' => $sets,
        ])->layout('layouts.labels-staff', $this->shell('Print sets'));
    }
}
