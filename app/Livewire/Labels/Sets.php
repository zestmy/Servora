<?php

namespace App\Livewire\Labels;

use App\Models\FormTemplate;
use App\Models\Ingredient;
use App\Models\LabelSet;
use App\Models\LabelSetLine;
use App\Models\LabelTemplate;
use App\Models\Outlet;
use App\Models\ProductionRecipe;
use App\Models\Recipe;
use App\Models\ShelfLifeRule;
use App\Services\LabelPrintService;
use App\Services\Labels\LabelQrService;
use App\Traits\ValidatesCompanyOutlet;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Print sets — "Chiller 1", "Grill Station" — and their contents.
 *
 * Sets are outlet-owned, so this screen always works within one outlet.
 *
 * Line order is physical: labels peel off the roll in this order and get
 * applied walking down the shelf, so move up/down is a real feature rather
 * than a tidiness one.
 */
class Sets extends Component
{
    use ValidatesCompanyOutlet;

    public ?int $outletId = null;

    public ?int $editingSetId = null;

    public string $search = '';

    public string $customName = '';

    // Set create/rename modal
    public bool $showModal = false;

    public ?int $modalSetId = null;

    public string $name = '';

    public string $description = '';

    public ?int $qrSetId = null;

    /*
     * Importing a Form Template as a set.
     *
     * A stock-take or order form IS a walk down the shelf already written out —
     * the same items in the same order a chef would label them in. Retyping one
     * into a print set is transcription, and transcription is where items go
     * missing.
     */
    public bool $showImport = false;

    public ?int $importTemplateId = null;

    public string $importName = '';

    /** 'new' or 'current' — only offered when a set is open. */
    public string $importTarget = 'new';

    /*
     * Importing another outlet's set.
     *
     * The outlets of one company mostly prep the same food, so a new branch's
     * "Chiller 1" is usually an existing branch's "Chiller 1" retyped — and
     * retyping is where items go missing. Same argument as the form import,
     * one outlet further along.
     */
    public bool $showSetImport = false;

    public ?int $importOutletId = null;

    public ?int $importSetId = null;

    public string $importSetName = '';

    /** 'new' or 'current' — only offered when a set is open. */
    public string $importSetTarget = 'new';

    /**
     * Line ids ticked for a bulk edit.
     *
     * Client-supplied, so every action that reads it re-scopes to the set
     * being edited — see selectedLinesInSet().
     *
     * @var array<int, int|string>
     */
    public array $selectedLines = [];

    /**
     * Bulk edit form. Every field starts on "no change" ('') so one Apply can
     * change a single column without touching the others.
     *
     * Shelf life needs a mode as well as a value, because clearing it is a
     * real choice ("follow the rules") and has to be told apart from leaving
     * it alone: '' = no change, 'auto' = back to the rules, 'set' = the value.
     */
    public string $bulkLabelType      = '';
    public string $bulkStorageState   = '';
    public string $bulkCopies         = '';
    public string $bulkShelfLifeMode  = '';
    public string $bulkShelfLifeValue = '';
    public string $bulkShelfLifeUnit  = 'days';

    public bool $showStorage = true;

    /** Empty means "work it out from the items in the set". */
    public array $storageStates = [];

    public function mount(): void
    {
        // As with printers: unset rather than an arbitrary branch. Sets are
        // outlet-owned, so the wrong one puts a station's labels elsewhere.
        $this->outletId = Auth::user()->activeOutletId();
    }

    public function updatedOutletId(): void
    {
        $this->editingSetId = null;
    }

    // ── Sets ──────────────────────────────────────────────────────────────

    public function openCreate(): void
    {
        $this->modalSetId    = null;
        $this->name          = '';
        $this->description   = '';
        $this->showStorage   = true;
        $this->storageStates = [];
        $this->resetValidation();
        $this->showModal = true;
    }

    public function openRename(int $id): void
    {
        $set = LabelSet::whereIn('outlet_id', Auth::user()->accessibleOutletIds())->findOrFail($id);

        $this->modalSetId    = $set->id;
        $this->name          = $set->name;
        $this->description   = (string) $set->description;
        $this->showStorage   = (bool) $set->show_storage;
        $this->storageStates = array_values(array_filter($set->storage_states ?? []));
        $this->resetValidation();
        $this->showModal = true;
    }

    public function saveSet(): void
    {
        $this->validate([
            'name'        => 'required|string|max:100',
            'description' => 'nullable|string|max:200',
            'outletId'    => ['required', 'integer', $this->outletExistsRule()],
        ]);

        // Only states we actually know about, and null rather than an empty
        // array so "automatic" is one representation instead of two.
        $states = array_values(array_intersect(
            $this->storageStates,
            array_keys(ShelfLifeRule::STORAGE_STATES)
        ));

        $storage = [
            'show_storage'   => $this->showStorage,
            'storage_states' => $states ?: null,
        ];

        if ($this->modalSetId) {
            LabelSet::whereIn('outlet_id', Auth::user()->accessibleOutletIds())->findOrFail($this->modalSetId)->update([
                'name'        => $this->name,
                'description' => $this->description ?: null,
            ] + $storage);
            session()->flash('success', 'Set updated.');
        } else {
            $set = LabelSet::create([
                'company_id'  => Auth::user()->company_id,
                'outlet_id'   => $this->outletId,
                'name'        => $this->name,
                'description' => $this->description ?: null,
                'created_by'  => Auth::id(),
            ] + $storage);
            $this->editingSetId = $set->id;
            session()->flash('success', 'Set created. Add items to it below.');
        }

        $this->showModal = false;
    }

    // ── Import from a Form Template ───────────────────────────────────────

    public function openImport(): void
    {
        $this->importTemplateId = null;
        $this->importName       = '';
        $this->importTarget     = $this->editingSetId ? 'current' : 'new';
        $this->resetValidation();
        $this->showImport = true;
    }

    public function closeImport(): void
    {
        $this->showImport = false;
    }

    /** Name the set after the template unless the person has typed their own. */
    public function updatedImportTemplateId($value): void
    {
        $template = $value ? FormTemplate::find($value) : null;

        if ($template && trim($this->importName) === '') {
            $this->importName = $template->name;
        }
    }

    /**
     * Copy a template's items into a print set.
     *
     * Order is carried over deliberately: a form template is already sequenced
     * the way the person walks the store, and that sequence is exactly what a
     * print set's order means.
     *
     * QUANTITIES ARE NOT CARRIED. A template line's default quantity is how much
     * to order or count; a print set's is how much is in the container being
     * labelled, and the template has no unit attached to disambiguate. Importing
     * the number would print a confident wrong figure on food.
     *
     * Items already in the target set are skipped rather than duplicated, so
     * importing the same template twice is safe and re-importing after the
     * template grew adds only what is new.
     */
    public function importTemplate(): void
    {
        $this->validate(
            [
                'importTemplateId' => 'required|integer|exists:form_templates,id',
                'importName'       => 'required_if:importTarget,new|nullable|string|max:100',
                'outletId'         => ['required', 'integer', $this->outletExistsRule()],
            ],
            ['importTemplateId.required' => 'Choose a template to import.']
        );

        $template = FormTemplate::with('lines')->findOrFail($this->importTemplateId);

        $set = $this->importTarget === 'current' && $this->editingSet()
            ? $this->editingSet()
            : LabelSet::create([
                'company_id'  => Auth::user()->company_id,
                'outlet_id'   => $this->outletId,
                'name'        => $this->importName,
                'description' => 'Imported from ' . $template->name,
                'created_by'  => Auth::id(),
            ]);

        $existing = $set->lines()
            ->whereNotNull('labelable_type')
            ->get()
            ->map(fn ($l) => $l->labelable_type . ':' . $l->labelable_id)
            ->all();

        $added = 0;
        $skipped = 0;
        $missing = 0;

        foreach ($template->lines as $line) {
            [$type, $id] = $line->item_type === 'recipe'
                ? [Recipe::class, $line->recipe_id]
                : [Ingredient::class, $line->ingredient_id];

            if (! $id) {
                $missing++;
                continue;
            }

            // The template outlives the items on it, so a line pointing at a
            // deleted ingredient is normal rather than exceptional.
            if (! $type::find($id)) {
                $missing++;
                continue;
            }

            if (in_array($type . ':' . $id, $existing, true)) {
                $skipped++;
                continue;
            }

            $this->createLine($set, $type, (int) $id, null);
            $existing[] = $type . ':' . $id;
            $added++;
        }

        $this->editingSetId = $set->id;
        $this->showImport   = false;

        session()->flash('success', $this->importSummary($template->name, $set->name, $added, $skipped, $missing));
    }

    /** Says what happened to every line, including the ones that did nothing. */
    private function importSummary(string $template, string $set, int $added, int $skipped, int $missing): string
    {
        if ($added === 0 && $skipped === 0 && $missing === 0) {
            return '“' . $template . '” has no items to import.';
        }

        $parts = [sprintf('%d item%s added to “%s” from “%s”', $added, $added === 1 ? '' : 's', $set, $template)];

        if ($skipped) {
            $parts[] = sprintf('%d already there', $skipped);
        }

        if ($missing) {
            $parts[] = sprintf('%d skipped — the item no longer exists', $missing);
        }

        return implode('. ', $parts) . '.';
    }

    // ── Import from another outlet ────────────────────────────────────────

    public function openSetImport(): void
    {
        $this->importOutletId  = null;
        $this->importSetId     = null;
        $this->importSetName   = '';
        $this->importSetTarget = $this->editingSetId ? 'current' : 'new';
        $this->resetValidation();
        $this->showSetImport = true;
    }

    public function closeSetImport(): void
    {
        $this->showSetImport = false;
    }

    /** A different outlet means a different list of sets to choose from. */
    public function updatedImportOutletId(): void
    {
        $this->importSetId   = null;
        $this->importSetName = '';
    }

    /** Name the copy after the set unless the person has typed their own. */
    public function updatedImportSetId($value): void
    {
        $set = $value ? LabelSet::find($value) : null;

        if ($set && trim($this->importSetName) === '') {
            $this->importSetName = $set->name;
        }
    }

    public function importSet(\App\Services\Labels\LabelSetImport $import): void
    {
        $this->validate(
            [
                'importOutletId' => ['required', 'integer', $this->outletExistsRule()],
                'importSetId'    => 'required|integer',
                'importSetName'  => 'required_if:importSetTarget,new|nullable|string|max:100',
                'outletId'       => ['required', 'integer', $this->outletExistsRule()],
            ],
            [
                'importOutletId.required' => 'Choose an outlet to import from.',
                'importSetId.required'    => 'Choose a set to import.',
            ]
        );

        // Scoped find: CompanyScope keeps this inside the company, and the
        // outlet match keeps a crafted set id from reaching across outlets
        // the dropdown never offered.
        $source = LabelSet::forOutlet((int) $this->importOutletId)->findOrFail($this->importSetId);

        if ($this->importSetTarget === 'current' && $this->editingSet()) {
            $target = $this->editingSet();
            [$added, $skipped, $missing] = $import->mergeLines($source, $target);
        } else {
            [$target, $added, $missing] = $import->copyToOutlet(
                $source, (int) $this->outletId, $this->importSetName, Auth::id()
            );
            $skipped = 0;
        }

        $this->editingSetId  = $target->id;
        $this->showSetImport = false;

        session()->flash('success', $import->summary($source, $target, $added, $skipped, $missing));
    }

    public function deleteSet(int $id): void
    {
        // CompanyScope covers other companies; a set also belongs to ONE
        // outlet, and deleting it is only for someone who can reach that one.
        LabelSet::whereIn('outlet_id', Auth::user()->accessibleOutletIds())->findOrFail($id)->delete();

        if ($this->editingSetId === $id) {
            $this->editingSetId = null;
        }

        session()->flash('success', 'Set deleted.');
    }

    /**
     * Only the ticked lines that actually belong to the set on screen.
     *
     * $selectedLines is a Livewire property and therefore whatever the browser
     * posted. Without this, a line id from another outlet's set could be
     * shelf-life edited from here — and a wrong shelf life is a wrong use-by
     * date on a food-safety label.
     *
     * @return \Illuminate\Support\Collection<int, LabelSetLine>
     */
    private function selectedLinesInSet()
    {
        $set = $this->editingSet();

        if (! $set || $this->selectedLines === []) {
            return collect();
        }

        return LabelSetLine::where('label_set_id', $set->id)
            ->whereIn('id', array_map('intval', $this->selectedLines))
            ->get();
    }

    /** Tick everything in the set, or clear the lot if it is already all ticked. */
    public function toggleAllLines(): void
    {
        $set = $this->editingSet();

        if (! $set) {
            return;
        }

        $ids = LabelSetLine::where('label_set_id', $set->id)->orderBy('sort_order')->pluck('id')->all();

        $this->selectedLines = count($this->selectedLines) === count($ids) ? [] : $ids;
    }

    public function clearLineSelection(): void
    {
        $this->selectedLines = [];
    }

    /**
     * Apply the bulk form to every ticked line — label type, storage state,
     * copies and shelf life, each only when it was changed from "no change".
     *
     * The point of the screen: a chiller set is a dozen items that were all
     * made this morning, all use-by, all chilled, all lasting three days, and
     * setting that one line at a time is how it gets left on the defaults.
     */
    public function applyBulk(): void
    {
        $lines = $this->selectedLinesInSet();

        if ($lines->isEmpty()) {
            session()->flash('error', 'Tick the items you want to change first.');

            return;
        }

        $update  = [];
        $changes = [];

        if ($this->bulkLabelType !== '') {
            if (! array_key_exists($this->bulkLabelType, LabelTemplate::LABEL_TYPES)) {
                return;
            }
            $update['label_type'] = $this->bulkLabelType;
            $changes[] = 'label type';

            // Same rule as updateLine(): a new type brings its default storage
            // state, or a defrost label keeps a chilled state and prints the
            // wrong date. An explicit storage choice below still wins.
            if (isset(LabelTemplate::DEFAULT_STORAGE_STATE[$this->bulkLabelType])) {
                $update['storage_state'] = LabelTemplate::DEFAULT_STORAGE_STATE[$this->bulkLabelType];
            }
        }

        if ($this->bulkStorageState !== '') {
            if (! array_key_exists($this->bulkStorageState, ShelfLifeRule::STORAGE_STATES)) {
                return;
            }
            $update['storage_state'] = $this->bulkStorageState;
            $changes[] = 'storage';
        }

        if (trim($this->bulkCopies) !== '') {
            $update['copies'] = min(99, max(1, (int) $this->bulkCopies));
            $changes[] = 'copies';
        }

        if ($this->bulkShelfLifeMode === 'auto') {
            // Clearing the value clears the unit too, so a line never carries
            // a unit with nothing to measure.
            $update['shelf_life_value'] = null;
            $update['shelf_life_unit']  = null;
            $changes[] = 'shelf life (back to Auto)';
        } elseif ($this->bulkShelfLifeMode === 'set') {
            $value = trim($this->bulkShelfLifeValue) === '' ? 0.0 : (float) $this->bulkShelfLifeValue;

            // Zero is not "Auto": it would be a use-by identical to the
            // prepared time, which is never what was meant.
            if ($value <= 0) {
                session()->flash('error', 'A shelf life has to be more than zero. Choose Auto to go back to the rules.');

                return;
            }

            if (! array_key_exists($this->bulkShelfLifeUnit, Recipe::SHELF_LIFE_UNITS)) {
                return;
            }

            $update['shelf_life_value'] = $value;
            $update['shelf_life_unit']  = $this->bulkShelfLifeUnit;
            $changes[] = 'shelf life';
        }

        if ($update === []) {
            session()->flash('error', 'Choose at least one thing to change.');

            return;
        }

        // Per model, not one query, so model events (auditing) still fire.
        foreach ($lines as $line) {
            $line->update($update);
        }

        $count = $lines->count();

        session()->flash('success', "Updated {$count} " . ($count === 1 ? 'item' : 'items') . ': ' . implode(', ', $changes) . '.');

        $this->selectedLines = [];
        $this->resetBulkForm();
    }

    private function resetBulkForm(): void
    {
        $this->bulkLabelType      = '';
        $this->bulkStorageState   = '';
        $this->bulkCopies         = '';
        $this->bulkShelfLifeMode  = '';
        $this->bulkShelfLifeValue = '';
        $this->bulkShelfLifeUnit  = 'days';
    }

    public function editLines(int $id): void
    {
        // A tick list carried across from another set would apply the next
        // bulk edit to lines nobody can see.
        $this->selectedLines = [];

        $this->editingSetId = $id;
        $this->search = '';
    }

    // ── QR ────────────────────────────────────────────────────────────────

    /** Show one set's QR so it can be printed and stuck on the station. */
    public function showQr(int $id): void
    {
        $this->qrSetId = $id;
    }

    public function closeQr(): void
    {
        $this->qrSetId = null;
    }

    // ── Lines ─────────────────────────────────────────────────────────────

    public function addLine(string $type, int $id): void
    {
        $set = $this->editingSet();

        if (! $set) {
            return;
        }

        $item = match ($type) {
            'ingredient' => Ingredient::find($id),
            'recipe'     => Recipe::find($id),
            'production' => ProductionRecipe::find($id),
            default      => null,
        };

        if (! $item) {
            return;
        }

        $this->createLine($set, $item::class, $item->getKey(), null);
        $this->search = '';
    }

    public function addCustomLine(): void
    {
        $set  = $this->editingSet();
        $name = \App\Services\Labels\LabelName::normalise($this->customName);

        if (! $set || $name === '') {
            return;
        }

        $this->createLine($set, null, null, $name);
        $this->customName = '';
    }

    public function removeLine(int $lineId): void
    {
        $line = $this->lineInEditingSet($lineId);

        if (! $line) {
            return;
        }

        $line->delete();
    }

    /** Swap this line with its neighbour — order is how labels come off the roll. */
    public function moveLine(int $lineId, int $direction): void
    {
        $line = LabelSetLine::findOrFail($lineId);
        $set  = $this->editingSet();

        if (! $set || $line->label_set_id !== $set->id) {
            return;
        }

        $lines = $set->lines()->get();
        $index = $lines->search(fn ($l) => $l->id === $line->id);
        $swap  = $lines->get($index + $direction);

        if (! $swap) {
            return;
        }

        // Positions may collide or be sparse, so rewrite both from the
        // list index rather than trusting the stored values.
        $line->update(['sort_order' => $index + $direction]);
        $swap->update(['sort_order' => $index]);
    }

    public function updateLine(int $lineId, string $field, $value): void
    {
        $fields = ['label_type', 'storage_state', 'copies', 'custom_name', 'shelf_life_value', 'shelf_life_unit'];

        if (! in_array($field, $fields, true)) {
            return;
        }

        $line = $this->lineInEditingSet($lineId);

        if (! $line) {
            return;
        }

        if ($field === 'label_type' && ! array_key_exists($value, LabelTemplate::LABEL_TYPES)) {
            return;
        }

        if ($field === 'storage_state' && ! array_key_exists($value, ShelfLifeRule::STORAGE_STATES)) {
            return;
        }

        if ($field === 'copies') {
            $value = max(1, (int) $value);
        }

        // A line-level shelf life makes the use-by print automatically —
        // most useful on freeform items, which no rule can ever cover.
        // Clearing the value clears the unit too, so "follow the rules
        // again" is one action rather than a value-less unit left behind.
        if ($field === 'shelf_life_value') {
            $value = (float) $value > 0 ? (float) $value : null;

            $line->update([
                'shelf_life_value' => $value,
                'shelf_life_unit'  => $value === null ? null : ($line->shelf_life_unit ?: 'days'),
            ]);

            return;
        }

        if ($field === 'shelf_life_unit') {
            if (! array_key_exists($value, Recipe::SHELF_LIFE_UNITS)) {
                return;
            }

            $line->update(['shelf_life_unit' => $value]);

            return;
        }

        // Only a freeform line has a name of its own to fix. A linked line
        // takes its name from the item, and letting it be typed over here
        // would silently detach the label from what it is labelling.
        if ($field === 'custom_name') {
            if ($line->labelable_type || \App\Services\Labels\LabelName::normalise($value) === '') {
                return;
            }
        }

        $update = [$field => $value];

        // Changing the label type re-points the storage state to that type's
        // default, otherwise a defrost label keeps a chilled state and prints
        // the wrong date.
        if ($field === 'label_type' && isset(LabelTemplate::DEFAULT_STORAGE_STATE[$value])) {
            $update['storage_state'] = LabelTemplate::DEFAULT_STORAGE_STATE[$value];
        }

        $line->update($update);
    }

    public function render(LabelPrintService $service, LabelQrService $qr)
    {
        $set = $this->editingSet();

        $qrSet = $this->qrSetId ? LabelSet::find($this->qrSetId) : null;

        return view('livewire.labels.sets', [
            'qrSet'    => $qrSet,
            'qrImage'  => $qrSet ? $qr->svgFor($qrSet) : null,
            'qrUrl'    => $qrSet ? $qr->urlFor($qrSet) : null,
            'sets'       => LabelSet::forOutlet((int) $this->outletId)->ordered()->withCount('lines')->get(),
            'outlets'    => Outlet::where('company_id', Auth::user()->company_id)->orderBy('name')->get(),
            'set'        => $set,
            'lines'      => $set ? $set->lines()->with(['labelable', 'uom'])->get() : collect(),
            'results'    => $this->searchResults(),
            'labelTypes' => LabelTemplate::LABEL_TYPES,
            // Company-wide, unlike sets — the same stock-take form is a print
            // set at every branch that uses it. Offered under this screen's own
            // ability rather than the Form Templates one: a template is a list
            // of ingredients and recipes, every one of which the search box two
            // panels over already finds, so requiring purchasing admin to build
            // a print set would gate nothing and block a chef.
            'formTemplates' => FormTemplate::active()->ordered()->withCount('lines')->get(),
            // Sets of whichever outlet the import modal has picked. Company
            // scope applies; the empty state belongs to the modal.
            'importSets' => $this->importOutletId
                ? LabelSet::forOutlet((int) $this->importOutletId)->ordered()->withCount('lines')->get()
                : collect(),
            'states'     => ShelfLifeRule::STORAGE_STATES,
            'shelfLifeUnits' => Recipe::SHELF_LIFE_UNITS,
            // This company's ranges, so the picker shows what will actually
            // print rather than the built-in defaults.
            'temperatures' => \App\Models\LabelSetting::temperaturesFor(Auth::user()->company_id),
        ])->layout(\App\Helpers\WorkspaceLayout::get(), ['title' => 'Print sets']);
    }

    private function editingSet(): ?LabelSet
    {
        return $this->editingSetId ? LabelSet::find($this->editingSetId) : null;
    }

    /**
     * A line of the open set only. Lines carry no company scope of their own
     * (they are reached through their set), so a bare find() on a client-sent
     * id would reach any company's line.
     */
    private function lineInEditingSet(int $lineId): ?LabelSetLine
    {
        $set = $this->editingSet();

        return $set ? LabelSetLine::where('label_set_id', $set->id)->find($lineId) : null;
    }

    private function createLine(LabelSet $set, ?string $type, ?int $id, ?string $customName): void
    {
        $labelType = 'prep';

        LabelSetLine::create([
            'label_set_id'   => $set->id,
            'sort_order'     => (int) $set->lines()->max('sort_order') + 1,
            'labelable_type' => $type,
            'labelable_id'   => $id,
            'custom_name'    => $customName,
            'label_type'     => $labelType,
            'storage_state'  => LabelTemplate::DEFAULT_STORAGE_STATE[$labelType] ?? 'chill',
            'copies'         => 1,
        ]);
    }

    /** Delegated: one search implementation for every label screen. */
    private function searchResults(): array
    {
        if (! $this->editingSetId) {
            return [];
        }

        return app(\App\Services\Labels\LabelItemSearch::class)
            ->groups(Auth::user()->company_id, $this->search);
    }
}
