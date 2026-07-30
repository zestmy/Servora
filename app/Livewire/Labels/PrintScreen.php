<?php

namespace App\Livewire\Labels;

use App\Models\Employee;
use App\Models\Ingredient;
use App\Models\LabelPrinter;
use App\Models\LabelTemplate;
use App\Models\ProductionRecipe;
use App\Models\Recipe;
use App\Models\ShelfLifeRule;
use App\Services\LabelPrintService;
use App\Services\LabelTemplateService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * The chef-facing print screen.
 *
 * Search, queue up what you're labelling, print once. The queue matters:
 * under kiosk printing the whole batch has to go out as a single document
 * and a single print() call, or the jobs race and come off the roll out of
 * order.
 *
 * Prep items are searched as Recipes, not as their mirrored Ingredients —
 * the mirror would show every prep item twice.
 */
class PrintScreen extends Component
{
    public ?int $printerId = null;

    public ?int $employeeId = null;

    public string $search = '';

    public string $customName = '';

    /** Queued lines, each one item to be labelled. */
    public array $tray = [];

    public function mount(): void
    {
        $companyId = Auth::user()->company_id;

        app(LabelTemplateService::class)->ensureDefaults($companyId);

        $outletId = Auth::user()->activeOutletId();

        $this->printerId = LabelPrinter::active()
            ->when($outletId, fn ($q) => $q->where('outlet_id', $outletId))
            ->value('id')
            ?? LabelPrinter::active()->value('id');
    }

    public function addItem(string $type, int $id): void
    {
        $item = match ($type) {
            'ingredient' => Ingredient::find($id),
            'recipe'     => Recipe::find($id),
            'production' => ProductionRecipe::find($id),
            default      => null,
        };

        if (! $item) {
            return;
        }

        $this->pushLine([
            'type' => $type,
            'id'   => $item->getKey(),
            'name' => $item->name,
        ]);

        $this->search = '';
    }

    /** A freeform line — something not in the system, labelled anyway. */
    public function addCustom(): void
    {
        $name = trim($this->customName);

        if ($name === '') {
            return;
        }

        $this->pushLine(['type' => null, 'id' => null, 'name' => $name]);

        $this->customName = '';
    }

    public function removeLine(int $index): void
    {
        unset($this->tray[$index]);
        $this->tray = array_values($this->tray);
    }

    public function clearTray(): void
    {
        $this->tray = [];
    }

    /**
     * Send the queue to the printer.
     *
     * The rendered document comes back and is dispatched to the browser,
     * which drops it into a hidden iframe and calls print(). Nothing is
     * printed server-side.
     */
    public function printTray(LabelPrintService $service): void
    {
        // Clear the previous attempt's message. Without this, fixing the
        // problem and pressing print again still shows the old complaint.
        $this->resetValidation();

        if (! $this->tray) {
            $this->addError('tray', 'Add something to print first.');

            return;
        }

        $printer = LabelPrinter::find($this->printerId);

        if (! $printer) {
            $this->addError('tray', 'Choose a printer first. Add one under Label Printers if the list is empty.');

            return;
        }

        // Every label has to name who prepped it — that is the whole point
        // of the audit trail.
        if (! $this->employeeId) {
            $this->addError('tray', 'Select who is preparing these under "Prepared by".');

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
            $result = $service->print($lines, $printer, $this->employeeId);
        } catch (\Throwable $e) {
            $this->addError('tray', $e->getMessage());

            return;
        }

        // Only the browser driver hands back something to print. PrintNode
        // has already queued it server-side, and dispatching an empty print
        // event would pop a dialog with nothing in it.
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

        $this->tray = [];
    }

    public function render(LabelPrintService $service)
    {
        $printer = $this->printerId ? LabelPrinter::find($this->printerId) : null;

        return view('livewire.labels.print-screen', [
            'results'   => $this->searchResults(),
            'printers'  => LabelPrinter::active()->with('outlet')->orderBy('name')->get(),
            'employees' => $this->employees($printer),
            'labelTypes' => LabelTemplate::LABEL_TYPES,
            'states'    => ShelfLifeRule::STORAGE_STATES,
            'previews'  => $this->previews($service),
        ])->layout(\App\Helpers\WorkspaceLayout::get(), ['title' => 'Print labels']);
    }

    /** Default a new line's label type and storage state, then queue it. */
    private function pushLine(array $item): void
    {
        $labelType = 'prep';

        $this->tray[] = [
            'type'          => $item['type'],
            'id'            => $item['id'],
            'name'          => $item['name'],
            'label_type'    => $labelType,
            'storage_state' => LabelTemplate::DEFAULT_STORAGE_STATE[$labelType] ?? 'chill',
            'copies'        => 1,
            'end_at'        => '',
        ];
    }

    /**
     * Three groups, deliberately not merged: a chef looking for "chicken"
     * wants to see whether they're grabbing the raw ingredient or the
     * prepped recipe, and a flat list hides that.
     */
    private function searchResults(): array
    {
        if (strlen(trim($this->search)) < 2) {
            return [];
        }

        $term = '%' . trim($this->search) . '%';

        return array_filter([
            'Market List' => Ingredient::where('is_prep', false)
                ->where('name', 'like', $term)->orderBy('name')->limit(6)
                ->get()->map(fn ($i) => ['type' => 'ingredient', 'id' => $i->id, 'name' => $i->name])->all(),
            'Recipes & Prep Items' => Recipe::where('name', 'like', $term)
                ->orderBy('name')->limit(6)
                ->get()->map(fn ($r) => ['type' => 'recipe', 'id' => $r->id, 'name' => $r->name])->all(),
            'Production Recipes' => ProductionRecipe::where('name', 'like', $term)
                ->orderBy('name')->limit(6)
                ->get()->map(fn ($p) => ['type' => 'production', 'id' => $p->id, 'name' => $p->name])->all(),
        ]);
    }

    private function employees(?LabelPrinter $printer)
    {
        return Employee::where('is_active', true)
            ->when($printer?->outlet_id, fn ($q, $outletId) => $q->where(function ($inner) use ($outletId) {
                $inner->where('outlet_id', $outletId)->orWhereNull('outlet_id');
            }))
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /**
     * Use-by preview per queued line, so the date is visible before a label
     * is committed rather than discovered on the roll.
     */
    private function previews(LabelPrintService $service): array
    {
        $out = [];

        foreach ($this->tray as $index => $line) {
            $item = match ($line['type']) {
                'ingredient' => Ingredient::find($line['id']),
                'recipe'     => Recipe::find($line['id']),
                'production' => ProductionRecipe::find($line['id']),
                default      => null,
            };

            $out[$index] = $service->previewUseBy($item, $line['storage_state']);
        }

        return $out;
    }
}
