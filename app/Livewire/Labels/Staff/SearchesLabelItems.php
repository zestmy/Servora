<?php

namespace App\Livewire\Labels\Staff;

use App\Models\Ingredient;
use App\Models\ProductionRecipe;
use App\Models\Recipe;
use App\Scopes\CompanyScope;

/**
 * Item lookup shared by the staff print screen and the set editor.
 *
 * Every query is company-scoped by hand: the staff app runs with no
 * authenticated web user, so CompanyScope cannot be relied on to do it.
 */
trait SearchesLabelItems
{
    /** Company-scoped lookup of one item by short type key. */
    protected function findLabelItem(?string $type, ?int $id)
    {
        if (! $type || ! $id) {
            return null;
        }

        $query = match ($type) {
            'ingredient' => Ingredient::withoutGlobalScope(CompanyScope::class),
            'recipe'     => Recipe::withoutGlobalScope(CompanyScope::class),
            'production' => ProductionRecipe::withoutGlobalScope(CompanyScope::class),
            default      => null,
        };

        return $query?->where('company_id', $this->staff()->company_id)->find($id);
    }

    /**
     * Three groups, deliberately not merged: someone looking for "chicken"
     * needs to see whether they're picking the raw ingredient or the
     * prepped recipe, and a flat list hides that.
     *
     * @return array<string, array<int, array{type: string, id: int, name: string}>>
     */
    protected function labelSearchResults(string $term): array
    {
        if (strlen(trim($term)) < 2) {
            return [];
        }

        $companyId = $this->staff()->company_id;
        $like      = '%' . trim($term) . '%';

        $scoped = fn ($class) => $class::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $companyId)
            ->where('name', 'like', $like)
            ->orderBy('name')
            ->limit(6);

        return array_filter([
            'Market List' => $scoped(Ingredient::class)->where('is_prep', false)->get()
                ->map(fn ($i) => ['type' => 'ingredient', 'id' => $i->id, 'name' => $i->name])->all(),
            'Recipes & Prep' => $scoped(Recipe::class)->get()
                ->map(fn ($r) => ['type' => 'recipe', 'id' => $r->id, 'name' => $r->name])->all(),
            'Production' => $scoped(ProductionRecipe::class)->get()
                ->map(fn ($p) => ['type' => 'production', 'id' => $p->id, 'name' => $p->name])->all(),
        ], fn ($group) => count($group) > 0);
    }
}
