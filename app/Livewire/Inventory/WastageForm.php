<?php

namespace App\Livewire\Inventory;

use App\Models\FormTemplate;
use App\Models\Ingredient;
use App\Models\Outlet;
use App\Models\Recipe;
use App\Models\WastageRecord;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class WastageForm extends Component
{
    public ?int $recordId      = null;
    public ?int $department_id = null;

    public string $wastage_date     = '';
    public string $reference_number = '';
    public string $notes            = '';

    // Lines: [item_type, ingredient_id, recipe_id, item_name, is_prep,
    //         uom_id, uom_abbr, quantity, unit_cost, total_cost, reason]
    public array  $lines              = [];
    public string $itemSearch         = '';
    public string $selectedTemplateId = '';

    protected function rules(): array
    {
        return [
            'wastage_date'       => 'required|date',
            'reference_number'   => 'nullable|string|max:100',
            'notes'              => 'nullable|string',
            'lines'              => 'required|array|min:1',
            'lines.*.quantity'   => 'required|numeric|min:0',
            'lines.*.unit_cost'  => 'required|numeric|min:0',
        ];
    }

    protected function messages(): array
    {
        return [
            'lines.required'       => 'Add at least one item.',
            'lines.min'            => 'Add at least one item.',
            'lines.*.quantity.min' => 'Quantity must be greater than zero.',
        ];
    }

    public function mount(?int $id = null): void
    {
        $this->wastage_date = now()->toDateString();

        if (! $id) return;

        $record = WastageRecord::with(['lines.ingredient.baseUom', 'lines.recipe.yieldUom'])->findOrFail($id);

        $this->recordId         = $record->id;
        $this->department_id    = $record->department_id;
        $this->wastage_date     = $record->wastage_date->toDateString();
        $this->reference_number = $record->reference_number ?? '';
        $this->notes            = $record->notes ?? '';

        $this->lines = $record->lines->map(function ($l) {
            if ($l->recipe_id) {
                return [
                    'item_type'     => 'recipe',
                    'ingredient_id' => null,
                    'recipe_id'     => $l->recipe_id,
                    'item_name'     => $l->recipe?->name ?? '—',
                    'is_prep'       => false,
                    'uom_id'        => $l->uom_id,
                    'uom_abbr'      => $l->uom?->abbreviation ?? '',
                    'quantity'      => (string) floatval($l->quantity),
                    'unit_cost'     => (string) floatval($l->unit_cost),
                    'total_cost'    => floatval($l->total_cost),
                    'reason'        => $l->reason ?? '',
                ];
            }

            return [
                'item_type'     => 'ingredient',
                'ingredient_id' => $l->ingredient_id,
                'recipe_id'     => null,
                'item_name'     => $l->ingredient?->name ?? '—',
                'is_prep'       => (bool) ($l->ingredient?->is_prep ?? false),
                'uom_id'        => $l->uom_id,
                'uom_abbr'      => $l->uom?->abbreviation ?? '',
                'quantity'      => (string) floatval($l->quantity),
                'unit_cost'     => (string) floatval($l->unit_cost),
                'total_cost'    => floatval($l->total_cost),
                'reason'        => $l->reason ?? '',
            ];
        })->toArray();
    }

    // ── Load from template ────────────────────────────────────────────────

    public function loadTemplate(): void
    {
        if (! $this->selectedTemplateId) return;

        $template = FormTemplate::with([
            'lines.ingredient.baseUom',
            'lines.recipe.yieldUom',
        ])->find((int) $this->selectedTemplateId);

        if (! $template) {
            $this->selectedTemplateId = '';
            return;
        }

        $existingIngIds    = collect($this->lines)->where('item_type', 'ingredient')->pluck('ingredient_id')->map(fn ($id) => (int) $id)->toArray();
        $existingRecipeIds = collect($this->lines)->where('item_type', 'recipe')->pluck('recipe_id')->map(fn ($id) => (int) $id)->toArray();
        $added = 0;

        foreach ($template->lines as $tLine) {
            if ($tLine->item_type === 'ingredient' && $tLine->ingredient) {
                if (in_array($tLine->ingredient_id, $existingIngIds)) continue;

                $unitCost = floatval($tLine->ingredient->current_cost);

                $qty = max(0, (int) $tLine->default_quantity);

                $this->lines[] = [
                    'item_type'     => 'ingredient',
                    'ingredient_id' => $tLine->ingredient->id,
                    'recipe_id'     => null,
                    'item_name'     => $tLine->ingredient->name,
                    'is_prep'       => (bool) $tLine->ingredient->is_prep,
                    'uom_id'        => $tLine->ingredient->base_uom_id,
                    'uom_abbr'      => $tLine->ingredient->baseUom?->abbreviation ?? '',
                    'quantity'      => (string) $qty,
                    'unit_cost'     => (string) $unitCost,
                    'total_cost'    => round($qty * $unitCost, 4),
                    'reason'        => '',
                ];
                $existingIngIds[] = $tLine->ingredient_id;
                $added++;
            } elseif ($tLine->item_type === 'recipe' && $tLine->recipe) {
                if (in_array($tLine->recipe_id, $existingRecipeIds)) continue;

                $unitCost = floatval($tLine->recipe->cost_per_yield_unit);
                $qty = max(0, (int) $tLine->default_quantity);

                $this->lines[] = [
                    'item_type'     => 'recipe',
                    'ingredient_id' => null,
                    'recipe_id'     => $tLine->recipe->id,
                    'item_name'     => $tLine->recipe->name,
                    'is_prep'       => false,
                    'uom_id'        => $tLine->recipe->yield_uom_id,
                    'uom_abbr'      => $tLine->recipe->yieldUom?->abbreviation ?? '',
                    'quantity'      => (string) $qty,
                    'unit_cost'     => (string) $unitCost,
                    'total_cost'    => round($qty * $unitCost, 4),
                    'reason'        => '',
                ];
                $existingRecipeIds[] = $tLine->recipe_id;
                $added++;
            }
        }

        $this->selectedTemplateId = '';

        if ($added === 0) {
            session()->flash('info', 'All items from that template are already in the form.');
        }
    }

    // ── Add ingredient ────────────────────────────────────────────────────

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

        $this->lines[] = [
            'item_type'     => 'ingredient',
            'ingredient_id' => $ingredient->id,
            'recipe_id'     => null,
            'item_name'     => $ingredient->name,
            'is_prep'       => (bool) $ingredient->is_prep,
            'uom_id'        => $ingredient->base_uom_id,
            'uom_abbr'      => $ingredient->baseUom->abbreviation ?? '',
            'quantity'      => '1',
            'unit_cost'     => (string) $unitCost,
            'total_cost'    => $unitCost,
            'reason'        => '',
        ];

        $this->itemSearch = '';
    }

    // ── Add recipe (finished/semi-finished product) ───────────────────────

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

        $this->lines[] = [
            'item_type'     => 'recipe',
            'ingredient_id' => null,
            'recipe_id'     => $recipe->id,
            'item_name'     => $recipe->name,
            'is_prep'       => false,
            'uom_id'        => $recipe->yield_uom_id,
            'uom_abbr'      => $recipe->yieldUom?->abbreviation ?? '',
            'quantity'      => '1',
            'unit_cost'     => (string) $unitCost,
            'total_cost'    => $unitCost,
            'reason'        => '',
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
        if (count($parts) === 2 && in_array($parts[1], ['quantity', 'unit_cost'])) {
            $this->recalcLine((int) $parts[0]);
        }
    }

    // ── Save ─────────────────────────────────────────────────────────────

    public function save(): void
    {
        $this->validate();

        $totalCost = collect($this->lines)->sum(fn ($l) => floatval($l['total_cost']));
        $outletId  = Outlet::where('company_id', Auth::user()->company_id)->value('id');

        $data = [
            'department_id'    => $this->department_id ?: null,
            'wastage_date'     => $this->wastage_date,
            'reference_number' => $this->reference_number ?: null,
            'notes'            => $this->notes ?: null,
            'total_cost'       => round($totalCost, 4),
        ];

        if ($this->recordId) {
            $record = WastageRecord::findOrFail($this->recordId);
            $record->update($data);
            session()->flash('success', 'Wastage record updated.');
        } else {
            $data['company_id'] = Auth::user()->company_id;
            $data['outlet_id']  = $outletId;
            $data['created_by'] = Auth::id();
            $record = WastageRecord::create($data);
            session()->flash('success', 'Wastage record created.');
        }

        // Remove items with zero quantity
        $this->lines = array_values(array_filter($this->lines, fn ($l) => floatval($l['quantity']) > 0));

        $record->lines()->delete();
        foreach ($this->lines as $line) {
            $qty      = floatval($line['quantity']);
            $unitCost = floatval($line['unit_cost']);

            $record->lines()->create([
                'ingredient_id' => $line['ingredient_id'] ?: null,
                'recipe_id'     => $line['recipe_id'] ?: null,
                'uom_id'        => $line['uom_id'],
                'quantity'      => $qty,
                'unit_cost'     => $unitCost,
                'total_cost'    => round($qty * $unitCost, 4),
                'reason'        => $line['reason'] ?: null,
            ]);
        }

        $this->redirectRoute('inventory.index');
    }

    public function render()
    {
        $ingredientResults = collect();
        $recipeResults     = collect();

        if (strlen($this->itemSearch) >= 2) {
            $existingIngredientIds = collect($this->lines)
                ->where('item_type', 'ingredient')
                ->pluck('ingredient_id')
                ->map(fn ($id) => (int) $id)
                ->toArray();

            $existingRecipeIds = collect($this->lines)
                ->where('item_type', 'recipe')
                ->pluck('recipe_id')
                ->map(fn ($id) => (int) $id)
                ->toArray();

            $ingredientResults = Ingredient::with(['baseUom'])
                ->where('is_active', true)
                ->where(function ($q) {
                    $q->where('name', 'like', '%' . $this->itemSearch . '%')
                      ->orWhere('code', 'like', '%' . $this->itemSearch . '%');
                })
                ->when($existingIngredientIds, fn ($q) => $q->whereNotIn('id', $existingIngredientIds))
                ->orderBy('name')
                ->limit(6)
                ->get();

            // Only non-prep sale recipes (prep items already appear as ingredients)
            $recipeResults = Recipe::with(['yieldUom'])
                ->where('is_active', true)
                ->where('is_prep', false)
                ->where(function ($q) {
                    $q->where('name', 'like', '%' . $this->itemSearch . '%')
                      ->orWhere('code', 'like', '%' . $this->itemSearch . '%');
                })
                ->when($existingRecipeIds, fn ($q) => $q->whereNotIn('id', $existingRecipeIds))
                ->orderBy('name')
                ->limit(6)
                ->get();
        }

        $totalCost          = collect($this->lines)->sum(fn ($l) => floatval($l['total_cost']));
        $pageTitle          = $this->recordId ? 'Edit Wastage Record' : 'New Wastage Entry';
        $availableTemplates = FormTemplate::ofType('wastage')->active()->ordered()->get();
        $departments = \App\Models\Department::active()->ordered()->get();

        return view('livewire.inventory.wastage-form', compact(
            'ingredientResults', 'recipeResults', 'totalCost', 'availableTemplates', 'departments'
        ))->layout(\App\Helpers\WorkspaceLayout::get(), ['title' => $pageTitle]);
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    private function recalcLine(int $idx): void
    {
        if (! isset($this->lines[$idx])) return;
        $qty      = floatval($this->lines[$idx]['quantity'] ?? 0);
        $unitCost = floatval($this->lines[$idx]['unit_cost'] ?? 0);
        $this->lines[$idx]['total_cost'] = round($qty * $unitCost, 4);
    }
}
