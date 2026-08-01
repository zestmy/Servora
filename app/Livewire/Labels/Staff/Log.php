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

    /** 'date' — newest run first, or 'set' — runs stacked under their set. */
    public string $groupBy = 'date';

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

    /**
     * Stack runs under the set they were printed from.
     *
     * Sets come out alphabetically with the ad-hoc runs last. Unlike the
     * expiring screen there is nothing at risk in a log, so a predictable
     * order beats an urgency one — you are looking for a name you already
     * have in mind.
     *
     * @return array<int, array{key: string, name: string, rows: \Illuminate\Support\Collection, labels: int}>
     */
    private function bySet($batches): array
    {
        $groups = [];

        foreach ($batches as $batch) {
            $key = $batch->label_set_id ? (string) $batch->label_set_id : 'none';

            $groups[$key] ??= [
                'key'    => $key,
                'name'   => $batch->labelSet?->name ?? 'Ad-hoc (no set)',
                'rows'   => collect(),
                'labels' => 0,
            ];

            $groups[$key]['rows']->push($batch);
            $groups[$key]['labels'] += (int) $batch->label_count;
        }

        $groups = array_values($groups);

        usort($groups, function ($a, $b) {
            if (($a['key'] === 'none') !== ($b['key'] === 'none')) {
                return $a['key'] === 'none' ? 1 : -1;
            }

            return strcasecmp($a['name'], $b['name']);
        });

        return $groups;
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
            // Same batches either way — the toggle restacks them. Grouped in
            // PHP because the list is already capped at LIMIT and in memory.
            'groups'   => $this->groupBy === 'set' ? $this->bySet($batches) : [],
            'expanded' => $expanded,
        ])->layout('layouts.labels-staff', $this->shell('Print log'));
    }
}
