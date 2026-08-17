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
use App\Traits\ScopesToActiveOutlet;
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
    use ScopesToActiveOutlet;

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

        /*
         * A printer AT THIS WORKSPACE, with no company-wide fallback.
         *
         * This defaulted to the active outlet's printer and then fell back to
         * any printer in the company, so a Central Kitchen with none of its
         * own silently pre-selected a printer in a restaurant — and the list
         * beside it offered every other outlet's too. Labels print on paper in
         * a room; the wrong room is not a lesser version of the right one.
         */
        $this->printerId = $this->printersHere()->value('id');
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
        // Normalised here as well as on the way to the printer, so the tray
        // shows exactly what will be on the label rather than what was typed.
        $name = \App\Services\Labels\LabelName::normalise($this->customName);

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

        // Re-resolved through the SAME scope the dropdown was built from,
        // because a Livewire property is client-supplied whatever the <select>
        // offered — and printing is a physical act in a specific room.
        $printer = $this->printersHere()->find($this->printerId);

        if (! $printer) {
            $this->addError('tray', 'Choose a printer first. Add one under Label Printers if the list is empty.');

            return;
        }

        // Every label has to name who prepped it — that is the whole point
        // of the audit trail. And it has to name SOMEBODY WHO WORKS THERE:
        // the id was checked for presence only, so a value that was never in
        // the dropdown would have signed the label in another outlet's name.
        $preparedBy = $this->employees($printer)->firstWhere('id', (int) $this->employeeId);

        if (! $preparedBy) {
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
            'printers'  => $this->printersHere()->with('outlet')->get(),
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

    /** Delegated: one search implementation for every label screen. */
    private function searchResults(): array
    {
        return app(\App\Services\Labels\LabelItemSearch::class)
            ->groups(Auth::user()->company_id, $this->search);
    }

    /**
     * Printers this workspace may print on.
     *
     * availableOutletIds() is the existing answer to "which outlets is this
     * screen about": in Central Kitchen mode it collapses to the kitchen's own
     * outlet, and otherwise it is the outlets the user can reach.
     */
    private function printersHere()
    {
        return LabelPrinter::active()
            ->whereIn('outlet_id', $this->availableOutletIds() ?: [0])
            ->orderBy('name');
    }

    /**
     * Who can be named as having prepared the label.
     *
     * Staff of the outlet the chosen printer stands in — the label says who
     * made the food, and the food was made where the printer is.
     *
     * The old query also admitted anyone with NO outlet, which put an
     * unassigned employee on every outlet's list at once. The employee form
     * has required an outlet throughout, so that arm only ever caught records
     * created some other way; nobody on production is in that state.
     *
     * Bounded by this workspace's outlets as well, so a printerId that was
     * never in the dropdown cannot widen the list.
     */
    private function employees(?LabelPrinter $printer)
    {
        $allowed = $this->availableOutletIds() ?: [0];

        if (! $printer?->outlet_id || ! in_array((int) $printer->outlet_id, $allowed, true)) {
            return Employee::whereRaw('1 = 0')->get(['id', 'name', 'photo_path']);
        }

        // photo_path selected or the picker's avatar silently falls back to
        // initials — the food-handler chips shipped exactly that bug.
        return Employee::where('is_active', true)
            ->where('outlet_id', $printer->outlet_id)
            ->orderBy('name')
            ->get(['id', 'name', 'photo_path']);
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
