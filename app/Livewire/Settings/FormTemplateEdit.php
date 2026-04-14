<?php

namespace App\Livewire\Settings;

use App\Models\Department;
use App\Models\FormTemplate;
use App\Models\FormTemplateLine;
use App\Models\Ingredient;
use App\Models\IngredientCategory;
use App\Models\Recipe;
use App\Models\Supplier;
use App\Models\UnitOfMeasure;
use Livewire\Component;

class FormTemplateEdit extends Component
{
    public ?int   $templateId  = null;
    public string $name        = '';
    public string $form_type   = '';
    public string $description = '';
    public bool   $is_active   = true;
    public string $sort_order  = '0';

    // PO header defaults
    public ?int   $supplier_id            = null;
    public string $receiver_name          = '';
    public ?int   $department_id          = null;

    // Lines loaded from DB (keyed by line ID for easy update/remove)
    public array  $lines       = [];

    // Search
    public string $itemSearch  = '';

    public function mount(?int $id = null): void
    {
        if (! $id) {
            abort(404);
        }

        $template = FormTemplate::with([
            'lines.ingredient.baseUom',
            'lines.ingredient.recipeUom',
            'lines.recipe.yieldUom',
        ])->findOrFail($id);

        $this->templateId  = $template->id;
        $this->name        = $template->name;
        $this->form_type   = $template->form_type;
        $this->description = $template->description ?? '';
        $this->is_active   = $template->is_active;
        $this->sort_order  = (string) $template->sort_order;

        // PO header defaults
        $this->supplier_id            = $template->supplier_id;
        $this->receiver_name          = $template->receiver_name ?? '';
        $this->department_id          = $template->department_id;

        $formType = $this->form_type;
        $this->lines = $template->lines->map(function ($l) use ($formType) {
            $packInfo = '';
            if ($l->item_type === 'recipe') {
                $uomAbbr = $l->recipe?->yieldUom?->abbreviation ?? '';
            } else {
                $uom = $this->ingredientUomFor($l->ingredient, $formType);
                $uomAbbr = $uom?->abbreviation ?? '';

                // Pack info only makes sense on purchase-order templates where
                // items are ordered in packs; for counting/wastage it's noise.
                if ($formType === 'purchase_order' && $l->ingredient) {
                    $packSize = floatval($l->ingredient->pack_size ?? 1) ?: 1;
                    if ($packSize > 1 && $l->ingredient->baseUom) {
                        $formatted = rtrim(rtrim(number_format($packSize, 4, '.', ''), '0'), '.');
                        $packInfo = '(' . $formatted . ' ' . strtoupper($l->ingredient->baseUom->abbreviation) . '/PACK)';
                        $uomAbbr = 'pack';
                    }
                }
            }

            return [
                'id'               => $l->id,
                'item_type'        => $l->item_type,
                'ingredient_id'    => $l->ingredient_id,
                'recipe_id'        => $l->recipe_id,
                'item_name'        => $l->itemName(),
                'uom_abbr'         => $uomAbbr,
                'pack_info'        => $packInfo,
                'default_quantity' => (string) $l->default_quantity,
            ];
        })->toArray();
    }

    // ── Header save ───────────────────────────────────────────────────────

    public function saveHeader(): void
    {
        $this->validate([
            'name'      => 'required|string|max:100',
            'sort_order'=> 'required|integer|min:0|max:9999',
        ]);

        $data = [
            'name'        => $this->name,
            'description' => $this->description ?: null,
            'is_active'   => $this->is_active,
            'sort_order'  => (int) $this->sort_order,
        ];

        if ($this->form_type === 'purchase_order') {
            $data['supplier_id']            = $this->supplier_id ?: null;
            $data['receiver_name']          = $this->receiver_name ?: null;
            $data['department_id']          = $this->department_id ?: null;
        }

        FormTemplate::findOrFail($this->templateId)->update($data);

        session()->flash('success', 'Template details saved.');
    }

    // ── Add items ─────────────────────────────────────────────────────────

    public function addIngredient(int $ingredientId): void
    {
        foreach ($this->lines as $line) {
            if ($line['item_type'] === 'ingredient' && (int) $line['ingredient_id'] === $ingredientId) {
                $this->itemSearch = '';
                return;
            }
        }

        $ingredient = Ingredient::with(['baseUom', 'recipeUom'])->findOrFail($ingredientId);
        $this->addIngredientToLines($ingredient);
        $this->itemSearch = '';
    }

    public function loadByCategory(int $categoryId): void
    {
        $category = IngredientCategory::with('children')->find($categoryId);
        if (!$category) return;

        // Get all category IDs (self + children if it's a root)
        $catIds = collect([$category->id]);
        if ($category->children->isNotEmpty()) {
            $catIds = $catIds->merge($category->children->pluck('id'));
        }

        $existingIngIds = collect($this->lines)
            ->where('item_type', 'ingredient')
            ->pluck('ingredient_id')
            ->map(fn ($id) => (int) $id)
            ->toArray();

        $ingredients = Ingredient::with(['baseUom', 'recipeUom'])
            ->where('is_active', true)
            ->whereIn('ingredient_category_id', $catIds)
            ->when($existingIngIds, fn ($q) => $q->whereNotIn('id', $existingIngIds))
            ->orderBy('name')
            ->get();

        $added = 0;
        foreach ($ingredients as $ingredient) {
            $this->addIngredientToLines($ingredient);
            $added++;
        }

        if ($added > 0) {
            session()->flash('success', "{$added} ingredient(s) added from {$category->name}.");
        } else {
            session()->flash('info', "No new ingredients to add from {$category->name}.");
        }
    }

    public function loadBySupplier(int $supplierId): void
    {
        $supplier = Supplier::find($supplierId);
        if (!$supplier) return;

        $existingIngIds = collect($this->lines)
            ->where('item_type', 'ingredient')
            ->pluck('ingredient_id')
            ->map(fn ($id) => (int) $id)
            ->toArray();

        $ingredients = Ingredient::with(['baseUom', 'recipeUom'])
            ->where('is_active', true)
            ->whereHas('suppliers', fn ($q) => $q->where('suppliers.id', $supplierId))
            ->when($existingIngIds, fn ($q) => $q->whereNotIn('id', $existingIngIds))
            ->orderBy('name')
            ->get();

        $added = 0;
        foreach ($ingredients as $ingredient) {
            $this->addIngredientToLines($ingredient);
            $added++;
        }

        if ($added > 0) {
            session()->flash('success', "{$added} ingredient(s) added from {$supplier->name}.");
        } else {
            session()->flash('info', "No new ingredients to add from {$supplier->name}.");
        }
    }

    private function addIngredientToLines(Ingredient $ingredient): void
    {
        $line = FormTemplateLine::create([
            'form_template_id' => $this->templateId,
            'item_type'        => 'ingredient',
            'ingredient_id'    => $ingredient->id,
            'recipe_id'        => null,
            'default_quantity' => 0,
            'sort_order'       => count($this->lines),
        ]);

        $packInfo = '';
        $uom = $this->ingredientUomFor($ingredient, $this->form_type);
        $uomAbbr = $uom?->abbreviation ?? '';

        if ($this->form_type === 'purchase_order') {
            $packSize = floatval($ingredient->pack_size ?? 1) ?: 1;
            if ($packSize > 1 && $ingredient->baseUom) {
                $formatted = rtrim(rtrim(number_format($packSize, 4, '.', ''), '0'), '.');
                $packInfo = '(' . $formatted . ' ' . strtoupper($ingredient->baseUom->abbreviation) . '/PACK)';
                $uomAbbr = 'pack';
            }
        }

        $this->lines[] = [
            'id'               => $line->id,
            'item_type'        => 'ingredient',
            'ingredient_id'    => $ingredient->id,
            'recipe_id'        => null,
            'item_name'        => $ingredient->name,
            'uom_abbr'         => $uomAbbr,
            'pack_info'        => $packInfo,
            'default_quantity' => '0',
        ];
    }

    public function addRecipe(int $recipeId): void
    {
        foreach ($this->lines as $line) {
            if ($line['item_type'] === 'recipe' && (int) $line['recipe_id'] === $recipeId) {
                $this->itemSearch = '';
                return;
            }
        }

        $recipe = Recipe::with('yieldUom')->findOrFail($recipeId);

        $line = FormTemplateLine::create([
            'form_template_id' => $this->templateId,
            'item_type'        => 'recipe',
            'ingredient_id'    => null,
            'recipe_id'        => $recipe->id,
            'default_quantity' => 0,
            'sort_order'       => count($this->lines),
        ]);

        $this->lines[] = [
            'id'               => $line->id,
            'item_type'        => 'recipe',
            'ingredient_id'    => null,
            'recipe_id'        => $recipe->id,
            'item_name'        => $recipe->name,
            'uom_abbr'         => $recipe->yieldUom?->abbreviation ?? '',
            'pack_info'        => '',
            'default_quantity' => '0',
        ];

        $this->itemSearch = '';
    }

    public function removeLine(int $lineId): void
    {
        FormTemplateLine::destroy($lineId);
        $this->lines = array_values(array_filter($this->lines, fn ($l) => $l['id'] !== $lineId));
    }

    /**
     * Pick the UOM to display/seed for an ingredient line based on the
     * template's form_type. Purchase orders use base UOM (pack-based);
     * stock take / wastage / staff meal count loose quantities so they
     * prefer the recipe UOM, falling back to base if none is set.
     */
    private function ingredientUomFor(?Ingredient $ingredient, string $formType): ?\App\Models\UnitOfMeasure
    {
        if (! $ingredient) return null;

        if ($formType === 'purchase_order') {
            return $ingredient->baseUom;
        }

        return $ingredient->recipeUom ?: $ingredient->baseUom;
    }

    public function reorderLines(array $orderedIds): void
    {
        $ids = array_map('intval', $orderedIds);

        foreach ($ids as $idx => $id) {
            FormTemplateLine::where('id', $id)->update(['sort_order' => $idx]);
        }

        $byId = collect($this->lines)->keyBy('id');
        $ordered = [];
        foreach ($ids as $id) {
            if (isset($byId[$id])) {
                $ordered[] = $byId[$id];
            }
        }
        if (count($ordered) === count($this->lines)) {
            $this->lines = $ordered;
        }
    }

    public function updateQty(int $lineId, string $qty): void
    {
        $val = max(0, (int) $qty);
        FormTemplateLine::where('id', $lineId)->update(['default_quantity' => $val]);

        foreach ($this->lines as &$line) {
            if ($line['id'] === $lineId) {
                $line['default_quantity'] = (string) $val;
                break;
            }
        }
    }

    public function render()
    {
        $ingredientResults = collect();
        $recipeResults     = collect();

        if (strlen($this->itemSearch) >= 2) {
            $existingIngIds = collect($this->lines)
                ->where('item_type', 'ingredient')
                ->pluck('ingredient_id')
                ->map(fn ($id) => (int) $id)
                ->toArray();

            $existingRecipeIds = collect($this->lines)
                ->where('item_type', 'recipe')
                ->pluck('recipe_id')
                ->map(fn ($id) => (int) $id)
                ->toArray();

            $ingredientResults = Ingredient::with('baseUom')
                ->where('is_active', true)
                ->where(function ($q) {
                    $q->where('name', 'like', '%' . $this->itemSearch . '%')
                      ->orWhere('code', 'like', '%' . $this->itemSearch . '%');
                })
                ->when($existingIngIds, fn ($q) => $q->whereNotIn('id', $existingIngIds))
                ->orderBy('name')
                ->limit(8)
                ->get();

            if ($this->form_type === 'wastage') {
                $recipeResults = Recipe::with('yieldUom')
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
        }

        $suppliers   = Supplier::where('is_active', true)->orderBy('name')->get();
        $departments = Department::active()->ordered()->get();
        $categories  = IngredientCategory::roots()->with('children')->active()->ordered()->get();

        return view('livewire.settings.form-template-edit', compact('ingredientResults', 'recipeResults', 'suppliers', 'departments', 'categories'))
            ->layout(\App\Helpers\WorkspaceLayout::get(), ['title' => 'Edit Template: ' . $this->name]);
    }
}
