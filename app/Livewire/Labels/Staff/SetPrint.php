<?php

namespace App\Livewire\Labels\Staff;

use App\Models\LabelSet;
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
    public int $setId;

    public array $selected = [];

    public array $copies = [];

    /** Manual use-by, only where no shelf life rule resolves. */
    public array $endAt = [];

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
                'labelable_type' => $line->labelable_type,
                'labelable_id'   => $line->labelable_id,
                'custom_name'    => $line->custom_name,
                'label_type'     => $line->label_type,
                'storage_state'  => $line->storage_state,
                'copies'         => max(1, (int) ($this->copies[$line->id] ?? $line->copies)),
                'quantity'       => $line->quantity,
                'uom_label'      => $line->uom?->code ?? $line->uom?->name,
                'template_id'    => $line->template_id,
                'end_at'         => $this->endAt[$line->id] ?? null,
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
            );
        }

        return view('livewire.labels.staff.set-print', [
            'set'      => $set,
            'lines'    => $lines,
            'previews' => $previews,
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
