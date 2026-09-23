<?php

namespace App\Livewire\Inventory;

use App\Models\Ingredient;
use App\Models\Outlet;
use App\Models\OutletTransfer;
use App\Models\Recipe;
use App\Models\UnitOfMeasure;
use App\Traits\LocksLineUnitCost;
use App\Traits\ScopesToActiveOutlet;
use App\Traits\ValidatesCompanyOutlet;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class TransferForm extends Component
{
    use ScopesToActiveOutlet, LocksLineUnitCost;
    use ValidatesCompanyOutlet;

    public ?int $transferId = null;

    public string $transfer_date   = '';
    public string $transfer_number = '';
    public string $from_outlet_id  = '';
    public string $to_outlet_id    = '';
    public string $status          = 'draft';
    public string $notes           = '';

    /*
     * A line is one of three kinds (item_type):
     *   ingredient: a Market List or prep item, the only kind that moves stock on hand
     *   recipe:     a finished dish, costed at the recipe's cost per yield unit
     *   custom:     free text for anything not in the catalogue; its unit cost is
     *               typed, because there is no price of record to take it from
     */
    public array  $lines      = [];
    public string $itemSearch = '';

    protected function rules(): array
    {
        return [
            'transfer_date'     => 'required|date',
            /*
             * SCOPED TO THE COMPANY, not just "an outlet that exists".
             *
             * `exists:outlets,id` alone accepts any id in the table, including
             * another tenant's. The source was covered anyway because save()
             * re-checks canAccessOutlet() on it, but the DESTINATION had no
             * such check — so a forged value could have pushed stock into
             * another company's outlet. A select box is not a control; the
             * rule is.
             */
            'from_outlet_id'    => ['required', $this->outletExistsRule()],
            'to_outlet_id'      => ['required', 'different:from_outlet_id', $this->outletExistsRule()],
            'lines'             => 'required|array|min:1',
            'lines.*.quantity'  => 'required|numeric|min:0.0001',
            'lines.*.item_type' => 'required|in:ingredient,recipe,custom',
            'lines.*.uom_id'    => 'required|exists:units_of_measure,id',
            'lines.*.custom_name' => 'required_if:lines.*.item_type,custom|nullable|string|max:200',
            'lines.*.unit_cost' => 'nullable|numeric|min:0',
        ];
    }

    protected function messages(): array
    {
        return [
            'lines.required'          => 'Add at least one item.',
            'lines.min'               => 'Add at least one item.',
            'lines.*.quantity.min'     => 'Quantity must be greater than zero.',
            'to_outlet_id.different'   => 'Destination outlet must be different from source.',
            'lines.*.custom_name.required_if' => 'Give every custom item a name.',
            'lines.*.uom_id.required'  => 'Choose a unit for every item.',
            'lines.*.unit_cost.min'    => 'Unit cost cannot be negative.',
        ];
    }

    public function mount(?int $id = null): void
    {
        $this->transfer_date = now()->toDateString();

        if ($id) {
            $transfer = OutletTransfer::with(['lines.ingredient.baseUom', 'lines.recipe', 'lines.uom'])->findOrFail($id);

            // Check user can access either the source or destination outlet
            $user = Auth::user();
            if (! $user->canAccessOutlet($transfer->from_outlet_id) && ! $user->canAccessOutlet($transfer->to_outlet_id)) {
                abort(403, 'You do not have access to this transfer.');
            }

            $this->transferId      = $transfer->id;
            $this->transfer_date   = $transfer->transfer_date->toDateString();
            $this->transfer_number = $transfer->transfer_number;
            $this->from_outlet_id  = (string) $transfer->from_outlet_id;
            $this->to_outlet_id    = (string) $transfer->to_outlet_id;
            $this->status          = $transfer->status;
            $this->notes           = $transfer->notes ?? '';

            $this->lines = $transfer->lines->map(function ($l) {
                $type = $l->ingredient_id ? 'ingredient' : ($l->recipe_id ? 'recipe' : 'custom');
                $line = [
                    'item_type'     => $type,
                    'ingredient_id' => $l->ingredient_id,
                    'recipe_id'     => $l->recipe_id,
                    'custom_name'   => $l->custom_name ?? '',
                    'item_name'     => $l->item_name,
                    'is_prep'       => (bool) ($l->ingredient?->is_prep ?? false),
                    'uom_id'        => $l->uom_id,
                    'uom_abbr'      => $l->uom?->abbreviation ?? '',
                    'quantity'      => (string) floatval($l->quantity),
                    'total_cost'    => round(floatval($l->quantity) * floatval($l->unit_cost), 4),
                ];

                // A custom line's cost is its own; only catalogue items lock theirs.
                if ($type === 'custom') {
                    $line['unit_cost'] = (string) round(floatval($l->unit_cost), 4);
                    return $line;
                }

                return $this->rememberLineCost($line, floatval($l->unit_cost));
            })->toArray();
        } else {
            $this->transfer_number = $this->generateTransferNumber();

            // Source defaults to where you're working — the kitchen's own
            // outlet in kitchen mode, otherwise the active outlet.
            $sourceOutletId = Auth::user()->kitchenOutletId() ?: Auth::user()->activeOutletId();
            if ($sourceOutletId) {
                $this->from_outlet_id = (string) $sourceOutletId;
            }
        }
    }

    public function addIngredient(int $ingredientId): void
    {
        foreach ($this->lines as $line) {
            if ($line['item_type'] === 'ingredient' && (int) $line['ingredient_id'] === $ingredientId) {
                $this->itemSearch = '';
                return;
            }
        }

        $ingredient = Ingredient::with(['baseUom'])->findOrFail($ingredientId);

        $unitCost = floatval($ingredient->current_cost);

        $this->lines[] = $this->rememberLineCost([
            'item_type'     => 'ingredient',
            'ingredient_id' => $ingredient->id,
            'recipe_id'     => null,
            'custom_name'   => '',
            'item_name'     => $ingredient->name,
            'is_prep'       => (bool) $ingredient->is_prep,
            'uom_id'        => $ingredient->base_uom_id,
            'uom_abbr'      => $ingredient->baseUom->abbreviation ?? '',
            'quantity'      => '1',
            'total_cost'    => $unitCost,
        ], $unitCost);

        $this->itemSearch = '';
    }

    /** A finished dish, moved at what one yield unit of it costs to make. */
    public function addRecipe(int $recipeId): void
    {
        foreach ($this->lines as $line) {
            if ($line['item_type'] === 'recipe' && (int) $line['recipe_id'] === $recipeId) {
                $this->itemSearch = '';
                return;
            }
        }

        $recipe = Recipe::with(['yieldUom'])->findOrFail($recipeId);

        $unitCost = floatval($recipe->cost_per_yield_unit);

        $this->lines[] = $this->rememberLineCost([
            'item_type'     => 'recipe',
            'ingredient_id' => null,
            'recipe_id'     => $recipe->id,
            'custom_name'   => '',
            'item_name'     => $recipe->name,
            'is_prep'       => false,
            'uom_id'        => $recipe->yield_uom_id,
            'uom_abbr'      => $recipe->yieldUom?->abbreviation ?? '',
            'quantity'      => '1',
            'total_cost'    => $unitCost,
        ], $unitCost);

        $this->itemSearch = '';
    }

    /**
     * Anything that is not in the catalogue. Named after whatever was typed in
     * the search box, since that is usually the thing nobody could find.
     */
    public function addCustomItem(): void
    {
        $uom = UnitOfMeasure::whereIn('abbreviation', ['pcs', 'pc', 'unit', 'ea'])->orderBy('id')->first()
            ?? UnitOfMeasure::orderBy('name')->first();

        $name = mb_substr(trim($this->itemSearch), 0, 200);

        $this->lines[] = [
            'item_type'     => 'custom',
            'ingredient_id' => null,
            'recipe_id'     => null,
            'custom_name'   => $name,
            'item_name'     => $name,
            'is_prep'       => false,
            'uom_id'        => $uom?->id,
            'uom_abbr'      => $uom?->abbreviation ?? '',
            'quantity'      => '1',
            'unit_cost'     => '0',
            'total_cost'    => 0.0,
        ];

        $this->itemSearch = '';
    }

    public function removeLine(int $idx): void
    {
        unset($this->lines[$idx]);
        $this->lines = array_values($this->lines);
    }

    public function updatedLines($value, $key): void
    {
        $parts = explode('.', $key);
        if (count($parts) !== 2 || ! isset($this->lines[(int) $parts[0]])) {
            return;
        }

        $idx = (int) $parts[0];

        if (in_array($parts[1], ['quantity', 'unit_cost'])) {
            $this->recalcLine($idx);
        }

        // Only a custom row may rename itself or change its unit; keep the label in step.
        if (($this->lines[$idx]['item_type'] ?? '') === 'custom') {
            if ($parts[1] === 'custom_name') {
                $this->lines[$idx]['item_name'] = trim((string) $value);
            }
            if ($parts[1] === 'uom_id') {
                $this->lines[$idx]['uom_abbr'] = UnitOfMeasure::find($value)?->abbreviation ?? '';
            }
        }
    }

    public function save(): void
    {
        // Re-checked here, not just on the route: a Livewire action is its own request.
        abort_unless(auth()->user()?->canDo('inventory.transfers.record'), 403);

        if ($this->status !== 'draft') {
            return;
        }

        $this->validate();

        // Whether this is an edit (transferId gets set on create below).
        $isEdit = (bool) $this->transferId;

        // Verify user has access to from_outlet_id
        $user = Auth::user();
        if (! $user->canAccessOutlet((int) $this->from_outlet_id)) {
            session()->flash('error', 'You do not have access to the source outlet.');
            return;
        }

        $totalCost = collect($this->lines)->sum(fn ($l) => floatval($l['total_cost']));

        $data = [
            'transfer_date'  => $this->transfer_date,
            'from_outlet_id' => (int) $this->from_outlet_id,
            'to_outlet_id'   => (int) $this->to_outlet_id,
            'notes'          => $this->notes ?: null,
        ];

        if ($this->transferId) {
            $transfer = OutletTransfer::findOrFail($this->transferId);
            $transfer->update($data);
            session()->flash('success', 'Transfer updated.');
        } else {
            $data['company_id']       = Auth::user()->company_id;
            $data['transfer_number']  = $this->transfer_number;
            $data['status']           = 'draft';
            $data['created_by']       = Auth::id();
            $transfer = OutletTransfer::create($data);
            $this->transferId = $transfer->id;
            session()->flash('success', 'Transfer created.');
        }

        // Capture existing lines for the activity trail before replacing them.
        $auditBefore = $isEdit
            ? $transfer->lines()->get()->map(fn ($l) => [
                'ingredient_id' => $l->ingredient_id, 'recipe_id' => $l->recipe_id, 'custom_name' => $l->custom_name,
                'uom_id' => $l->uom_id, 'quantity' => (float) $l->quantity,
            ])->all()
            : [];

        $transfer->lines()->delete();
        foreach ($this->lines as $line) {
            $type = $line['item_type'];

            // Exactly one identity per row, whatever else the row arrived
            // carrying: a custom row with an ingredient_id left on it would
            // move real stock at a typed price.
            $transfer->lines()->create([
                'ingredient_id' => $type === 'ingredient' ? ($line['ingredient_id'] ?: null) : null,
                'recipe_id'     => $type === 'recipe' ? ($line['recipe_id'] ?: null) : null,
                'custom_name'   => $type === 'custom' ? trim((string) $line['custom_name']) : null,
                'uom_id'        => $line['uom_id'],
                'quantity'      => floatval($line['quantity']),
                'unit_cost'     => $this->lineUnitCost($line),
            ]);
        }

        // Log item add / remove / quantity changes on edits.
        if ($isEdit) {
            \App\Services\AuditLogService::logItemLineChanges($transfer, $auditBefore, $this->lines);
        }

        $this->redirectRoute('inventory.index', ['tab' => 'transfers']);
    }

    public function send(): void
    {
        $transfer = OutletTransfer::findOrFail($this->transferId);
        if ($transfer->status !== 'draft') {
            return;
        }

        $transfer->update(['status' => 'in_transit']);
        $this->status = 'in_transit';
        session()->flash('success', 'Transfer sent — now in transit.');
    }

    public function receive(): void
    {
        $transfer = OutletTransfer::findOrFail($this->transferId);
        if ($transfer->status !== 'in_transit') {
            return;
        }

        $transfer->update(['status' => 'received']);
        $this->status = 'received';
        session()->flash('success', 'Transfer received successfully.');
    }

    public function cancel(): void
    {
        $transfer = OutletTransfer::findOrFail($this->transferId);
        if (! in_array($transfer->status, ['draft', 'in_transit'])) {
            return;
        }

        $transfer->update(['status' => 'cancelled']);
        $this->status = 'cancelled';
        session()->flash('success', 'Transfer cancelled.');
    }

    public function render()
    {
        $ingredientResults = collect();
        $prepResults       = collect();
        $recipeResults     = collect();

        if (strlen($this->itemSearch) >= 2) {
            $existingIds = collect($this->lines)
                ->where('item_type', 'ingredient')
                ->pluck('ingredient_id')
                ->map(fn ($id) => (int) $id)
                ->toArray();

            $existingRecipeIds = collect($this->lines)
                ->where('item_type', 'recipe')
                ->pluck('recipe_id')
                ->map(fn ($id) => (int) $id)
                ->toArray();

            $matches = function ($q) {
                $q->where('name', 'like', '%' . $this->itemSearch . '%')
                  ->orWhere('code', 'like', '%' . $this->itemSearch . '%');
            };

            // Market List and prep items are both ingredients, but asked for
            // separately so a common word cannot crowd prep items out of one
            // shared limit.
            $ingredients = fn (bool $isPrep) => Ingredient::with(['baseUom'])
                ->where('is_active', true)
                ->where('is_prep', $isPrep)
                ->where($matches)
                ->when($existingIds, fn ($q) => $q->whereNotIn('id', $existingIds))
                ->orderBy('name')
                ->limit(6)
                ->get();

            $ingredientResults = $ingredients(false);
            $prepResults       = $ingredients(true);

            // Non-prep recipes only: a prep recipe is already listed as its
            // prep item, and moving it as that item keeps it in stock on hand.
            $recipeResults = Recipe::with(['yieldUom'])
                ->where('is_active', true)
                ->where('is_prep', false)
                ->where($matches)
                ->when($existingRecipeIds, fn ($q) => $q->whereNotIn('id', $existingRecipeIds))
                ->orderBy('name')
                ->limit(6)
                ->get();
        }

        $uoms = UnitOfMeasure::orderBy('name')->get(['id', 'name', 'abbreviation']);

        /*
         * TWO LISTS, because the two ends of a transfer ask different
         * questions.
         *
         * SOURCE is what you may take stock OUT of, so it stays scoped to the
         * outlets you can access — and save() re-checks it, because taking
         * stock off a branch you cannot see is not something to allow from a
         * select box.
         *
         * DESTINATION is where you may send it, and that is every active
         * outlet in the company. Sharing one list is what broke this: a
         * central kitchen user is attached only to the kitchen's own outlet,
         * so the accessible list was one entry, the source defaulted to it,
         * and the destination select — which excludes the source — rendered
         * EMPTY. A kitchen exists precisely to send stock to branches it is
         * not itself attached to.
         *
         * It matches the rule already applied when opening an existing
         * transfer, which admits anyone who can see EITHER end: a transfer
         * legitimately spans an outlet you do not otherwise work in.
         */
        // selectable() keeps whichever outlets THIS transfer already names, so
        // reopening one raised before a branch closed still shows both ends.
        $sourceOutlets = Outlet::whereIn('id', array_unique(array_merge(
                Auth::user()->accessibleOutletIds(),
                $this->from_outlet_id ? [(int) $this->from_outlet_id] : [],
            )))
            ->selectable($this->from_outlet_id)
            ->orderBy('name')
            ->get();

        $destinationOutlets = Outlet::where('company_id', Auth::user()->company_id)
            ->selectable($this->to_outlet_id)
            ->orderBy('name')
            ->get();
        $totalCost = collect($this->lines)->sum(fn ($l) => floatval($l['total_cost']));
        $pageTitle = $this->transferId ? 'Transfer ' . $this->transfer_number : 'New Transfer';
        $isDraft   = $this->status === 'draft';

        return view('livewire.inventory.transfer-form', compact(
            'ingredientResults', 'prepResults', 'recipeResults', 'uoms', 'sourceOutlets', 'destinationOutlets', 'totalCost', 'isDraft'
        ))->layout(\App\Helpers\WorkspaceLayout::get(), ['title' => $pageTitle]);
    }

    private function recalcLine(int $idx): void
    {
        if (! isset($this->lines[$idx])) {
            return;
        }
        $qty      = floatval($this->lines[$idx]['quantity'] ?? 0);
        $unitCost = $this->lineUnitCost($this->lines[$idx]);
        $this->lines[$idx]['total_cost'] = round($qty * $unitCost, 4);
    }

    /**
     * Catalogue items take the locked, server-priced cost. A custom item has no
     * price of record anywhere, so the cost typed on the row is the only one.
     */
    private function lineUnitCost(array $line): float
    {
        if (($line['item_type'] ?? 'ingredient') === 'custom') {
            return round(max(0, floatval($line['unit_cost'] ?? 0)), 4);
        }

        return $this->lockedLineCost($line);
    }

    private function generateTransferNumber(): string
    {
        $prefix = 'TRF-' . now()->format('Ymd') . '-';
        $last   = OutletTransfer::withoutGlobalScopes()
            ->where('transfer_number', 'like', $prefix . '%')
            ->orderByDesc('transfer_number')
            ->value('transfer_number');
        $seq = $last ? ((int) substr($last, -3) + 1) : 1;

        return $prefix . str_pad($seq, 3, '0', STR_PAD_LEFT);
    }
}
