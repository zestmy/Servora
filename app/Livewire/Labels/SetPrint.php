<?php

namespace App\Livewire\Labels;

use App\Models\Employee;
use App\Models\LabelPrinter;
use App\Models\LabelSet;
use App\Services\LabelPrintService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * The review checklist a print set opens into.
 *
 * A set never prints blind. Half the items in "Chiller 1" won't have been
 * prepped on any given morning, and printing the lot would burn most of a
 * roll on labels that go straight in the bin.
 *
 * Lines start ticked because the common case is prepping most of a station;
 * unticking two is faster than ticking twelve.
 */
class SetPrint extends Component
{
    public LabelSet $set;

    public ?int $printerId = null;

    public ?int $employeeId = null;

    /** [line_id => bool] */
    public array $selected = [];

    /** [line_id => int] */
    public array $copies = [];

    /** [line_id => 'Y-m-d\TH:i'] manual use-by, only where no rule resolves. */
    public array $endAt = [];

    public function mount(LabelSet $set): void
    {
        // Route-model binding bypasses the outlet check, and sets are
        // outlet-owned — a set from another outlet has no business printing
        // to this one's printer.
        abort_unless($set->company_id === Auth::user()->company_id, 404);

        $this->set = $set;

        $this->printerId = LabelPrinter::active()
            ->where('outlet_id', $set->outlet_id)
            ->value('id');

        foreach ($set->lines as $line) {
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
        // Stale errors from a previous attempt would otherwise survive the fix.
        $this->resetValidation();

        $printer = LabelPrinter::find($this->printerId);

        if (! $printer) {
            $this->addError('print', 'Choose a printer first.');

            return;
        }

        if (! $this->employeeId) {
            $this->addError('print', 'Select who is preparing these under "Prepared by".');

            return;
        }

        $lines = [];

        foreach ($this->set->lines as $line) {
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
            $result = $service->print($lines, $printer, $this->employeeId, $this->set->id);
        } catch (\Throwable $e) {
            $this->addError('print', $e->getMessage());

            return;
        }

        // PrintNode queues server-side and returns nothing to print here.
        if ($result['document']) {
            $this->dispatch('label-print', document: $result['document']);
        }

        session()->flash('success', sprintf(
            '%d label%s %s %s.',
            $result['labels'],
            $result['labels'] === 1 ? '' : 's',
            $result['document'] ? 'sent to' : 'queued on',
            $printer->name
        ));
    }

    public function render(LabelPrintService $service)
    {
        $lines = $this->set->lines()->with(['labelable', 'uom'])->get();

        $previews = [];

        foreach ($lines as $line) {
            $previews[$line->id] = $service->previewUseBy($line->labelable, $line->storage_state);
        }

        return view('livewire.labels.set-print', [
            'lines'     => $lines,
            'previews'  => $previews,
            'printers'  => LabelPrinter::active()->where('outlet_id', $this->set->outlet_id)->get(),
            // Staff of the set's own outlet, and nobody else. This also
            // admitted anyone with NO outlet, which put an unassigned employee
            // on every outlet's list at once; the employee form has required
            // an outlet throughout, so that arm only caught records created
            // some other way.
            'employees' => Employee::where('is_active', true)
                ->where('outlet_id', $this->set->outlet_id)
                ->orderBy('name')->get(['id', 'name']),
        ])->layout(\App\Helpers\WorkspaceLayout::get(), ['title' => $this->set->name]);
    }
}
