<?php

namespace App\Livewire\Labels\Staff;

use App\Models\LabelSet;
use App\Models\Outlet;
use App\Scopes\CompanyScope;
use App\Services\Labels\LabelSetImport;

/**
 * Print sets for the staff member's outlet.
 *
 * Staff print sets; managers build them. Editing a set on a phone mid-shift
 * is not a thing anyone wants to do, and a mis-tap would change what the
 * whole outlet prints tomorrow.
 *
 * The ONE write this screen allows is importing another outlet's set — a
 * copy, not an edit. A new branch's opening morning is exactly the moment
 * nobody is at a desk: the chef who transferred in knows their old outlet's
 * "Chiller 1" is right, and copying it cannot damage the outlet it came
 * from. Importing again later tops the copy up rather than doubling it.
 */
class Sets extends StaffComponent
{
    public bool $showImport = false;

    public ?int $importOutletId = null;

    public ?int $importSetId = null;

    public function mount(): void
    {
        $this->mountStaffDefaults();
    }

    public function openImport(): void
    {
        $this->importOutletId = null;
        $this->importSetId    = null;
        $this->resetValidation();
        $this->showImport = true;
    }

    public function closeImport(): void
    {
        $this->showImport = false;
    }

    /** A different outlet means a different list of sets to choose from. */
    public function updatedImportOutletId(): void
    {
        $this->importSetId = null;
    }

    public function importSet(LabelSetImport $import): void
    {
        // No outlet, nowhere to import into. The screen never offers the
        // button in that state; this is for the stale-session replay.
        abort_unless($this->outletId(), 403);

        $this->validate(
            [
                'importOutletId' => 'required|integer',
                'importSetId'    => 'required|integer',
            ],
            [
                'importOutletId.required' => 'Choose an outlet first.',
                'importSetId.required'    => 'Choose a set to import.',
            ]
        );

        // The dropdown only offers other outlets of this company, but a
        // Livewire property is client-supplied like any request field — so
        // the same fence is rebuilt here. Own outlet included in the ban:
        // "import" means "from somewhere else", and a same-outlet copy would
        // just duplicate the set in place.
        $validSource = $this->importOutletId !== $this->outletId()
            && Outlet::where('company_id', $this->staff()->company_id)
                ->whereKey($this->importOutletId)
                ->exists();

        if (! $validSource) {
            $this->addError('importOutletId', 'Choose an outlet first.');

            return;
        }

        $source = LabelSet::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $this->staff()->company_id)
            ->where('outlet_id', $this->importOutletId)
            ->where('is_active', true)
            ->find($this->importSetId);

        if (! $source) {
            $this->addError('importSetId', 'That set is no longer available.');

            return;
        }

        /*
         * Idempotent on purpose: a set of the same name already here becomes
         * the target and is topped up, instead of a second "Chiller 1"
         * appearing beside the first — the likeliest outcome of a double tap
         * on a phone in a kitchen.
         */
        $existing = LabelSet::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $this->staff()->company_id)
            ->where('outlet_id', $this->outletId())
            ->where('is_active', true)
            ->where('name', $source->name)
            ->first();

        if ($existing) {
            [$added, $skipped, $missing] = $import->mergeLines($source, $existing);
            $target = $existing;
        } else {
            // created_by is a users id and an employee is not a user, so the
            // copy carries no author. The source set keeps its own.
            [$target, $added, $missing] = $import->copyToOutlet($source, (int) $this->outletId());
            $skipped = 0;
        }

        $this->showImport     = false;
        $this->importOutletId = null;
        $this->importSetId    = null;

        session()->flash('success', $import->summary($source, $target, $added, $skipped, $missing));
    }

    public function render()
    {
        $companyId = $this->staff()->company_id;

        $sets = LabelSet::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $companyId)
            ->where('outlet_id', $this->outletId())
            ->where('is_active', true)
            ->withCount('lines')
            ->orderBy('sort_order')->orderBy('name')
            ->get();

        // Only outlets that actually have a set to offer — an empty outlet
        // in this list is a dead end three taps deep.
        $sourceOutletIds = LabelSet::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->where('outlet_id', '!=', $this->outletId())
            ->distinct()
            ->pluck('outlet_id');

        $importOutlets = Outlet::whereIn('id', $sourceOutletIds)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $importSets = $this->importOutletId
            ? LabelSet::withoutGlobalScope(CompanyScope::class)
                ->where('company_id', $companyId)
                ->where('outlet_id', $this->importOutletId)
                ->where('is_active', true)
                ->withCount('lines')
                ->orderBy('sort_order')->orderBy('name')
                ->get()
            : collect();

        return view('livewire.labels.staff.sets', [
            'sets'          => $sets,
            'importOutlets' => $importOutlets,
            'importSets'    => $importSets,
        ])->layout('layouts.labels-staff', $this->shell('Print sets'));
    }
}
