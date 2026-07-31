<?php

namespace App\Livewire\Labels\Staff;

use App\Models\Ingredient;
use App\Models\LabelTemplate;
use App\Models\ProductionRecipe;
use App\Models\Recipe;
use App\Models\ShelfLifeRule;
use App\Scopes\CompanyScope;
use App\Services\LabelPrintService;

/**
 * The staff print screen.
 *
 * Same pipeline as the desktop screen, fewer decisions. "Prepared by" is
 * not a field here at all — it is whoever entered their PIN, which is the
 * real payoff of per-employee PINs: the mandatory attribution stops being
 * something a chef can get wrong or skip.
 */
class PrintLabels extends StaffComponent
{
    use SearchesLabelItems;

    public string $search = '';

    public string $customName = '';

    public array $tray = [];

    public function mount(): void
    {
        $this->mountStaffDefaults();
    }

    public function addItem(string $type, int $id): void
    {
        $item = $this->findLabelItem($type, $id);

        if (! $item) {
            return;
        }

        $this->pushLine($type, $item->getKey(), $item->name);
        $this->search = '';
    }

    public function addCustom(): void
    {
        $name = trim($this->customName);

        if ($name === '') {
            return;
        }

        $this->pushLine(null, null, $name);
        $this->customName = '';
    }

    public function removeLine(int $index): void
    {
        unset($this->tray[$index]);
        $this->tray = array_values($this->tray);
    }

    public function adjustCopies(int $index, int $delta): void
    {
        if (! isset($this->tray[$index])) {
            return;
        }

        $this->tray[$index]['copies'] = max(1, min(99, (int) $this->tray[$index]['copies'] + $delta));
    }

    public function clearTray(): void
    {
        $this->tray = [];
    }

    public function printTray(LabelPrintService $service): void
    {
        $this->resetValidation();

        if (! $this->tray) {
            $this->addError('tray', 'Add something to print first.');

            return;
        }

        $printer = $this->resolvePrinter();

        if (! $printer) {
            $this->addError('tray', 'No printer set up for ' . ($this->outletName() ?: 'this outlet') . '. Ask your manager.');

            return;
        }

        $lines = array_map(fn ($line) => [
            'labelable_type' => $line['type'],
            'labelable_id'   => $line['id'],
            'custom_name'    => $line['name'],
            'label_type'     => $line['label_type'],
            'storage_state'  => $line['storage_state'],
            'copies'         => (int) $line['copies'],
            'end_at'         => $line['end_at'] ?: null,
        ], $this->tray);

        try {
            $result = $service->print($lines, $printer, $this->staff()->id);
        } catch (\Throwable $e) {
            $this->addError('tray', $e->getMessage());

            return;
        }

        if ($result['document']) {
            $this->dispatch('label-print', document: $result['document']);
        }

        session()->flash('success', $result['labels'] . ' label' . ($result['labels'] === 1 ? '' : 's')
            . ($result['document'] ? ' printing' : ' queued') . '.');

        $this->tray = [];
    }

    public function render(LabelPrintService $service)
    {
        return view('livewire.labels.staff.print-labels', [
            'results'    => $this->labelSearchResults($this->search),
            'previews'   => $this->previews($service),
            'labelTypes' => LabelTemplate::LABEL_TYPES,
            'states'     => ShelfLifeRule::STORAGE_STATES,
            'printers'   => $this->printers(),
        ])->layout('layouts.labels-staff', $this->shell('Print labels'));
    }

    private function pushLine(?string $type, ?int $id, string $name): void
    {
        $labelType = 'prep';

        $this->tray[] = [
            'type'          => $type,
            'id'            => $id,
            'name'          => $name,
            'label_type'    => $labelType,
            'storage_state' => LabelTemplate::DEFAULT_STORAGE_STATE[$labelType] ?? 'chill',
            'copies'        => 1,
            'end_at'        => '',
        ];
    }

    private function previews(LabelPrintService $service): array
    {
        $out = [];

        foreach ($this->tray as $index => $line) {
            $item = $line['type'] ? $this->findLabelItem($line['type'], (int) $line['id']) : null;

            $out[$index] = $service->previewUseBy(
                $item,
                $line['storage_state'],
                null,
                $this->staff()->company_id,
            );
        }

        return $out;
    }
}
