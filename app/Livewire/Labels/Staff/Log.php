<?php

namespace App\Livewire\Labels\Staff;

use App\Models\LabelPrintBatch;
use App\Scopes\CompanyScope;
use Illuminate\Support\Carbon;

/**
 * What this outlet has printed recently.
 *
 * Read-only, and scoped to the staff member's own outlet. Useful on the
 * floor for "did that batch actually go out?" — and it is the same record
 * an auditor sees, so a chef can answer a question without finding a
 * manager.
 *
 * Shows the whole outlet's activity, not just this person's: the question
 * being answered is usually about a label someone else printed.
 */
class Log extends StaffComponent
{
    /** Batches to show. Kept small — this is a phone, not a report. */
    private const LIMIT = 40;

    public int $days = 3;

    public ?int $expandedId = null;

    public function mount(): void
    {
        $this->mountStaffDefaults();
    }

    public function toggle(int $id): void
    {
        $this->expandedId = $this->expandedId === $id ? null : $id;
    }

    public function setDays(int $days): void
    {
        $this->days       = $days;
        $this->expandedId = null;
    }

    public function render()
    {
        $batches = LabelPrintBatch::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $this->staff()->company_id)
            ->where('outlet_id', $this->outletId())
            ->where('printed_at', '>=', Carbon::now()->subDays($this->days))
            ->with(['employee', 'labelSet'])
            ->orderByDesc('printed_at')
            ->limit(self::LIMIT)
            ->get();

        $expanded = $this->expandedId
            ? LabelPrintBatch::withoutGlobalScope(CompanyScope::class)
                ->where('company_id', $this->staff()->company_id)
                ->where('outlet_id', $this->outletId())
                ->with('prints')
                ->find($this->expandedId)
            : null;

        return view('livewire.labels.staff.log', [
            'batches'  => $batches,
            'expanded' => $expanded,
        ])->layout('layouts.labels-staff', $this->shell('Print log'));
    }
}
