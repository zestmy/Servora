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

    public bool $showStorage = true;

    /** Empty means "work it out from the items in the set". */
    public array $storageStates = [];

    public function mount(): void
    {
        $this->outletId = Auth::user()->activeOutletId()
            ?? Outlet::where('company_id', Auth::user()->company_id)->value('id');
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
        $set = LabelSet::findOrFail($id);

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
            'outletId'    => 'required|integer|exists:outlets,id',
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
            LabelSet::findOrFail($this->modalSetId)->update([
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
                'outletId'         => 'required|integer|exists:outlets,id',
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

    public function deleteSet(int $id): void
    {
        LabelSet::findOrFail($id)->delete();

        if ($this->editingSetId === $id) {
            $this->editingSetId = null;
        }

        session()->flash('success', 'Set deleted.');
    }

    public function editLines(int $id): void
    {
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
        LabelSetLine::findOrFail($lineId)->delete();
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
        if (! in_array($field, ['label_type', 'storage_state', 'copies', 'custom_name'], true)) {
            return;
        }

        $line = LabelSetLine::findOrFail($lineId);

        if ($field === 'copies') {
            $value = max(1, (int) $value);
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
            'states'     => ShelfLifeRule::STORAGE_STATES,
            // This company's ranges, so the picker shows what will actually
            // print rather than the built-in defaults.
            'temperatures' => \App\Models\LabelSetting::temperaturesFor(Auth::user()->company_id),
        ])->layout(\App\Helpers\WorkspaceLayout::get(), ['title' => 'Print sets']);
    }

    private function editingSet(): ?LabelSet
    {
        return $this->editingSetId ? LabelSet::find($this->editingSetId) : null;
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
