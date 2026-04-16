<?php

namespace App\Livewire\Recipes;

use App\Models\CentralKitchen;
use App\Models\Outlet;
use App\Models\Recipe;
use App\Models\RecipeCategory;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public string $tab = 'recipes'; // recipes | prep-items
    public string $search = '';
    public string $categoryFilter = '';
    public string $statusFilter = 'all';
    public string $outletFilter = '';
    public string $costFilter = '';

    protected $queryString = ['tab'];

    public function updatedSearch(): void         { $this->resetPage(); }
    public function updatedCategoryFilter(): void { $this->resetPage(); }
    public function updatedStatusFilter(): void   { $this->resetPage(); }
    public function updatedOutletFilter(): void   { $this->resetPage(); }
    public function updatedCostFilter(): void     { $this->resetPage(); }

    public function getLockedProperty(): bool
    {
        $user = Auth::user();
        return (bool) ($user?->company?->recipes_locked)
            && ! $user?->canBypassLock();
    }

    private function assertUnlocked(): bool
    {
        if ($this->locked) {
            session()->flash('error', 'Recipes are locked. Ask a company admin to unlock in Settings → Company Details.');
            return false;
        }
        return true;
    }

    public function delete(int $id): void
    {
        if (! $this->assertUnlocked()) return;
        Recipe::findOrFail($id)->delete();
        session()->flash('success', 'Recipe deleted.');
    }

    public function toggleActive(int $id): void
    {
        if (! $this->assertUnlocked()) return;
        $r = Recipe::findOrFail($id);
        $r->update(['is_active' => ! $r->is_active]);
    }

    /**
     * Reorder recipes on the current page by reassigning their existing
     * menu_sort_order values to new positions. Stable across pages because
     * only the dragged rows' slots are reshuffled.
     */
    public function reorder(array $orderedIds): void
    {
        if (! $this->assertUnlocked()) return;
        $ids = array_map('intval', array_values($orderedIds));
        if (count($ids) < 2) return;

        $existing = Recipe::whereIn('id', $ids)->pluck('menu_sort_order', 'id')->toArray();
        $values = array_values($existing);
        sort($values, SORT_NUMERIC);

        // If all rows currently share the same menu_sort_order (e.g., fresh data),
        // use sequential slots starting from that value.
        if (count(array_unique($values)) < count($ids)) {
            $base = (int) ($values[0] ?? 0);
            $values = range($base, $base + count($ids) - 1);
        }

        foreach ($ids as $idx => $id) {
            Recipe::where('id', $id)->update(['menu_sort_order' => $values[$idx]]);
        }
    }

    public function render()
    {
        $isPrep = $this->tab === 'prep-items';

        $query = Recipe::with([
            'yieldUom',
            'outlets',
            'lines.ingredient.baseUom',
            'lines.ingredient.uomConversions',
            'lines.uom',
            'prices.priceClass',
            'ingredientCategory.parent',
        ])
            ->where('recipes.is_prep', $isPrep);

        // Prep items group by ingredient_category_id; recipes group by menu
        // category string (which FK's by name to recipe_categories).
        if ($isPrep) {
            $query->leftJoin('ingredient_categories as rc', function ($join) {
                    $join->on('rc.id', '=', 'recipes.ingredient_category_id')
                         ->whereNull('rc.deleted_at');
                })
                ->leftJoin('ingredient_categories as rcp', 'rcp.id', '=', 'rc.parent_id');
        } else {
            // Prefer sub-category match (has parent_id) over root when same name exists at both levels
            $query->leftJoin('recipe_categories as rc', function ($join) {
                    $join->on('rc.name', '=', 'recipes.category')
                         ->on('rc.company_id', '=', 'recipes.company_id')
                         ->whereNull('rc.deleted_at')
                         ->whereNotNull('rc.parent_id');
                })
                ->leftJoin('recipe_categories as rc_root', function ($join) {
                    $join->on('rc_root.name', '=', 'recipes.category')
                         ->on('rc_root.company_id', '=', 'recipes.company_id')
                         ->whereNull('rc_root.deleted_at')
                         ->whereNull('rc_root.parent_id');
                })
                ->leftJoin('recipe_categories as rcp', 'rcp.id', '=', 'rc.parent_id');
        }

        $query->select('recipes.*')->withCount('lines');

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('recipes.name', 'like', '%' . $this->search . '%')
                  ->orWhere('recipes.code', 'like', '%' . $this->search . '%');
            });
        }

        if ($this->categoryFilter) {
            if ($isPrep) {
                // Prep items use ingredient_category_id (FK). Include children.
                $selectedCat = \App\Models\IngredientCategory::with('children')->find((int) $this->categoryFilter);
                if ($selectedCat) {
                    $ids = collect([$selectedCat->id]);
                    if ($selectedCat->children->isNotEmpty()) {
                        $ids = $ids->merge($selectedCat->children->pluck('id'));
                    }
                    $query->whereIn('recipes.ingredient_category_id', $ids->toArray());
                }
            } else {
                // Recipes match by the menu-category string.
                $selectedCat = RecipeCategory::with('children')->find((int) $this->categoryFilter);
                if ($selectedCat) {
                    $names = collect([$selectedCat->name]);
                    if ($selectedCat->children->isNotEmpty()) {
                        $names = $names->merge($selectedCat->children->pluck('name'));
                    }
                    $query->whereIn('recipes.category', $names->toArray());
                }
            }
        }

        if ($this->statusFilter === 'active') {
            $query->where('recipes.is_active', true);
        } elseif ($this->statusFilter === 'inactive') {
            $query->where('recipes.is_active', false);
        }

        if ($this->outletFilter) {
            $outletId = (int) $this->outletFilter;
            // Show recipes tagged to this outlet OR recipes with no outlet tags (available everywhere)
            $query->where(function ($q) use ($outletId) {
                $q->whereHas('outlets', fn ($sub) => $sub->where('outlets.id', $outletId))
                  ->orWhereDoesntHave('outlets');
            });
        }

        // Sort by category hierarchy → manual menu order → recipe name.
        // Recipes whose category string doesn't match any category go last.
        if ($isPrep) {
            $query->orderByRaw('COALESCE(rcp.sort_order, rc.sort_order) IS NULL')
                  ->orderByRaw('COALESCE(rcp.sort_order, rc.sort_order) ASC')
                  ->orderByRaw('COALESCE(rcp.name, rc.name) ASC')
                  ->orderBy('rc.sort_order')
                  ->orderBy('rc.name');
        } else {
            // rc = sub-category match, rc_root = root fallback, rcp = parent of sub
            // Parent sort: if sub matched → use rcp (parent), else use rc_root
            $query->orderByRaw('COALESCE(rcp.sort_order, rc_root.sort_order, rc.sort_order) IS NULL')
                  ->orderByRaw('COALESCE(rcp.sort_order, rc_root.sort_order, rc.sort_order) ASC')
                  ->orderByRaw('COALESCE(rcp.name, rc_root.name, rc.name) ASC')
                  ->orderByRaw('COALESCE(rc.sort_order, rc_root.sort_order) ASC')
                  ->orderByRaw('COALESCE(rc.name, rc_root.name) ASC');
        }
        $query->orderBy('recipes.menu_sort_order')
              ->orderBy('recipes.name');

        if ($this->costFilter) {
            $allRecipes = $query->get();

            $filtered = $allRecipes->filter(function ($recipe) {
                $totalCost = $recipe->total_cost;
                $selling   = $recipe->effective_selling_price;
                $pct       = $selling > 0 ? ($totalCost / $selling) * 100 : null;

                return match ($this->costFilter) {
                    'under25' => $pct !== null && $pct <= 25,
                    '25to35'  => $pct !== null && $pct > 25 && $pct <= 35,
                    '35to45'  => $pct !== null && $pct > 35 && $pct <= 45,
                    'over45'  => $pct !== null && $pct > 45,
                    'none'    => $pct === null,
                    default   => true,
                };
            });

            $page    = $this->getPage();
            $perPage = 15;
            $recipes = new LengthAwarePaginator(
                $filtered->forPage($page, $perPage)->values(),
                $filtered->count(),
                $perPage,
                $page,
                ['path' => request()->url()],
            );
        } else {
            $recipes = $query->paginate(15);
        }

        if ($isPrep) {
            // Cost-category picker for prep items.
            $recipeCategories = \App\Models\IngredientCategory::with(['children' => function ($q) {
                    $q->where('is_active', true)->orderBy('sort_order')->orderBy('name');
                }])
                ->roots()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get();
        } else {
            $recipeCategories = RecipeCategory::with(['children' => function ($q) {
                    $q->where('is_active', true)->orderBy('sort_order')->orderBy('name');
                }])
                ->roots()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get();
        }

        $centralKitchenOutletIds = CentralKitchen::whereNotNull('outlet_id')->pluck('outlet_id')->all();
        $outlets = Outlet::where('company_id', Auth::user()->company_id)
            ->where('is_active', true)
            ->whereNotIn('id', $centralKitchenOutletIds)
            ->orderBy('name')
            ->get();

        return view('livewire.recipes.index', compact('recipes', 'recipeCategories', 'outlets', 'isPrep'))
            ->layout('layouts.app', ['title' => $isPrep ? 'Prep Items' : 'Recipes']);
    }
}
