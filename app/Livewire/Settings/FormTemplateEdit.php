<?php

namespace App\Livewire\Settings;

use App\Models\FormTemplate;
use App\Models\FormTemplateLine;
use App\Models\Ingredient;
use App\Models\Recipe;
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
            'lines.recipe.yieldUom',
        ])->findOrFail($id);

        $this->templateId  = $template->id;
        $this->name        = $template->name;
        $this->form_type   = $template->form_type;
        $this->description = $template->description ?? '';
        $this->is_active   = $template->is_active;
        $this->sort_order  = (string) $template->sort_order;

        $this->lines = $template->lines->map(function ($l) {
            $packInfo = '';
            $uomAbbr = $l->item_type === 'recipe'
                ? ($l->recipe?->yieldUom?->abbreviation ?? '')
                : ($l->ingredient?->baseUom?->abbreviation ?? '');

            if ($l->item_type === 'ingredient' && $l->ingredient) {
                $packSize = floatval($l->ingredient->pack_size ?? 1) ?: 1;
                if ($packSize > 1 && $l->ingredient->baseUom) {
                    $formatted = rtrim(rtrim(number_format($packSize, 4, '.', ''), '0'), '.');
                    $packInfo = '(' . $formatted . ' ' . strtoupper($l->ingredient->baseUom->abbreviation) . '/PACK)';
                    $uomAbbr = 'pack';
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

        FormTemplate::findOrFail($this->templateId)->update([
            'name'        => $this->name,
            'description' => $this->description ?: null,
            'is_active'   => $this->is_active,
            'sort_order'  => (int) $this->sort_order,
        ]);

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

        $ingredient = Ingredient::with('baseUom')->findOrFail($ingredientId);

        $line = FormTemplateLine::create([
            'form_template_id' => $this->templateId,
            'item_type'        => 'ingredient',
            'ingredient_id'    => $ingredient->id,
            'recipe_id'        => null,
            'default_quantity' => 1,
            'sort_order'       => count($this->lines),
        ]);

        $packInfo = '';
        $uomAbbr = $ingredient->baseUom?->abbreviation ?? '';
        $packSize = floatval($ingredient->pack_size ?? 1) ?: 1;
        if ($packSize > 1 && $ingredient->baseUom) {
            $formatted = rtrim(rtrim(number_format($packSize, 4, '.', ''), '0'), '.');
            $packInfo = '(' . $formatted . ' ' . strtoupper($ingredient->baseUom->abbreviation) . '/PACK)';
            $uomAbbr = 'pack';
        }

        $this->lines[] = [
            'id'               => $line->id,
            'item_type'        => 'ingredient',
            'ingredient_id'    => $ingredient->id,
            'recipe_id'        => null,
            'item_name'        => $ingredient->name,
            'uom_abbr'         => $uomAbbr,
            'pack_info'        => $packInfo,
            'default_quantity' => '1',
        ];

        $this->itemSearch = '';
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
            'default_quantity' => 1,
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
            'default_quantity' => '1',
        ];

        $this->itemSearch = '';
    }

    public function removeLine(int $lineId): void
    {
        FormTemplateLine::destroy($lineId);
        $this->lines = array_values(array_filter($this->lines, fn ($l) => $l['id'] !== $lineId));
    }

    public function updateQty(int $lineId, string $qty): void
    {
        $val = max(0.0001, floatval($qty));
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

        return view('livewire.settings.form-template-edit', compact('ingredientResults', 'recipeResults'))
            ->layout('layouts.app', ['title' => 'Edit Template: ' . $this->name]);
    }
}
