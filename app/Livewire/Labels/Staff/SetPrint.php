<?php

namespace App\Livewire\Labels\Staff;

use App\Models\LabelSet;
use App\Models\LabelSetLine;
use App\Models\LabelTemplate;
use App\Scopes\CompanyScope;
use App\Services\LabelPrintService;

/**
 * The review checklist for one set, on a phone.
 *
 * Same rule as the desktop version: a set never prints blind, because half
 * a chiller's contents won't have been prepped on any given morning and
 * printing the lot burns a roll. Lines start ticked — unticking two beats
 * ticking twelve.
 */
class SetPrint extends StaffComponent
{
    use SearchesLabelItems;

    public int $setId;

    public array $selected = [];

    public array $copies = [];

    /** Manual use-by, only where no shelf life rule resolves. */
    public array $endAt = [];

    /** Editing is behind a toggle so a mis-tap can't reorganise a station. */
    public bool $editing = false;

    public string $search = '';

    public string $customName = '';

    public function mount(int $set): void
    {
        $this->mountStaffDefaults();

        $model = $this->set($set);

        $this->setId = $model->id;

        foreach ($model->lines as $line) {
            $this->selected[$line->id] = true;
            $this->copies[$line->id]   = $line->copies;
        }
    }

    public function selectAll(bool $state): void
    {
        foreach (array_keys($this->selected) as $id) {
            $this->selected[$id] = $state;
        }
    }

    // ── Editing what's in the set ─────────────────────────────────────────

    public function toggleEditing(): void
    {
        $this->editing = ! $this->editing;
        $this->search  = '';
        $this->resetValidation();
    }

    public function addItem(string $type, int $id): void
    {
        $item = $this->findLabelItem($type, $id);

        if (! $item) {
            return;
        }

        $this->appendLine($item::class, $item->getKey(), null);
        $this->search = '';
    }

    public function addCustomItem(): void
    {
        $name = \App\Services\Labels\LabelName::normalise($this->customName);

        if ($name === '') {
            return;
        }

        $this->appendLine(null, null, $name);
        $this->customName = '';
    }

    /**
     * Remove a line from the set.
     *
     * This edits the set for the whole outlet, not just this shift — worth
     * knowing, and why the UI says so before anyone taps it.
     */
    public function removeItem(int $lineId): void
    {
        $line = $this->line($lineId);

        if (! $line) {
            return;
        }

        $name = $line->displayName();
        $line->delete();

        // Keep the checklist state in step, or a deleted line's stale entry
        // would linger and be counted on the next print.
        unset($this->selected[$lineId], $this->copies[$lineId], $this->endAt[$lineId]);

        session()->flash('success', $name . ' removed from this set.');
    }

    private function appendLine(?string $type, ?int $id, ?string $customName): void
    {
        $set       = $this->set($this->setId);
        $labelType = 'prep';

        $line = LabelSetLine::create([
            'label_set_id'   => $set->id,
            // Onto the end: order is physical, and a chef adding something
            // mid-shift is adding it to the end of their walk.
            'sort_order'     => (int) $set->lines()->max('sort_order') + 1,
            'labelable_type' => $type,
            'labelable_id'   => $id,
            'custom_name'    => $customName,
            'label_type'     => $labelType,
            'storage_state'  => LabelTemplate::DEFAULT_STORAGE_STATE[$labelType] ?? 'chill',
            'copies'         => 1,
        ]);

        $this->selected[$line->id] = true;
        $this->copies[$line->id]   = 1;

        session()->flash('success', $line->displayName() . ' added.');
    }

    /** A line belonging to THIS set, or nothing. */
    private function line(int $lineId): ?LabelSetLine
    {
        return LabelSetLine::where('label_set_id', $this->setId)->find($lineId);
    }

    public function print(LabelPrintService $service): void
    {
        $this->resetValidation();

        $printer = $this->resolvePrinter();

        if (! $printer) {
            $this->addError('print', 'No printer set up for this outlet. Ask your manager.');

            return;
        }

        $lines = [];

        foreach ($this->set($this->setId)->lines as $line) {
            if (empty($this->selected[$line->id])) {
                continue;
            }

            $lines[] = [
                'labelable_type'   => $line->labelable_type,
                'labelable_id'     => $line->labelable_id,
                'custom_name'      => $line->custom_name,
                'label_type'       => $line->label_type,
                'storage_state'    => $line->storage_state,
                'shelf_life_value' => $line->shelf_life_value,
                'shelf_life_unit'  => $line->shelf_life_unit,
                'copies'           => max(1, (int) ($this->copies[$line->id] ?? $line->copies)),
                'quantity'         => $line->quantity,
                'uom_label'        => $line->uom?->code ?? $line->uom?->name,
                'template_id'      => $line->template_id,
                'end_at'           => $this->endAt[$line->id] ?? null,
            ];
        }

        if (! $lines) {
            $this->addError('print', 'Tick at least one item.');

            return;
        }

        try {
            $result = $service->print($lines, $printer, $this->staff()->id, $this->setId);
        } catch (\Throwable $e) {
            $this->addError('print', $e->getMessage());

            return;
        }

        if ($result['document']) {
            $this->dispatch('label-print', document: $result['document']);
        }

        session()->flash('success', $result['labels'] . ' label' . ($result['labels'] === 1 ? '' : 's')
            . ($result['document'] ? ' printing' : ' queued') . '.');
    }

    public function render(LabelPrintService $service)
    {
        $set   = $this->set($this->setId);
        $lines = $set->lines()->with(['labelable', 'uom'])->get();

        $previews = [];

        foreach ($lines as $line) {
            $previews[$line->id] = $service->previewUseBy(
                $line->labelable,
                $line->storage_state,
                null,
                $this->staff()->company_id,
                $line->shelfLifeOverride(),
            );
        }

        return view('livewire.labels.staff.set-print', [
            'set'      => $set,
            'lines'    => $lines,
            'previews' => $previews,
            'printers' => $this->printers(),
            'results'  => $this->editing ? $this->labelSearchResults($this->search) : [],
        ])->layout('layouts.labels-staff', $this->shell($set->name));
    }

    /**
     * A set from this staff member's own outlet, or nothing.
     *
     * Route-model binding would happily hand over another outlet's set, and
     * sets are outlet-owned — printing one here would produce labels for a
     * kitchen this person doesn't work in.
     */
    private function set(int $id): LabelSet
    {
        $set = LabelSet::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $this->staff()->company_id)
            ->where('outlet_id', $this->outletId())
            ->find($id);

        abort_unless($set, 404);

        return $set;
    }
}
