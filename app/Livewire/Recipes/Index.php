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
    public int $perPage = 15;

    public const PER_PAGE_OPTIONS = [15, 25, 50, 100];

    // Bulk selection
    public array $selectedIds = [];
    public bool $selectAll = false;

    protected $queryString = ['tab'];

    /** Session key holding the last-chosen list filters so they survive navigation (e.g. saving the form). */
    private const FILTERS_KEY = 'recipes.index.filters';

    public function mount(): void
    {
        // The tab is owned by the URL query string, never the session — otherwise
        // opening Prep Items would be forced back to the last-used Recipes tab.
        $this->tab = request()->query('tab') === 'prep-items' ? 'prep-items' : 'recipes';

        // Filters are scoped per tab because Recipes and Prep Items use different
        // category systems (recipe categories vs ingredient categories).
        $saved = session($this->filtersKey());
        if (is_array($saved)) {
            $this->search         = $saved['search']         ?? $this->search;
            $this->categoryFilter = $saved['categoryFilter'] ?? $this->categoryFilter;
            $this->statusFilter   = $saved['statusFilter']   ?? $this->statusFilter;
            $this->outletFilter   = $saved['outletFilter']   ?? $this->outletFilter;
            $this->costFilter     = $saved['costFilter']     ?? $this->costFilter;
            $this->perPage        = in_array($saved['perPage'] ?? null, self::PER_PAGE_OPTIONS, true) ? $saved['perPage'] : $this->perPage;
        }
    }

    private function filtersKey(): string
    {
        return self::FILTERS_KEY . '.' . $this->tab;
    }

    private function persistFilters(): void
    {
        session()->put($this->filtersKey(), [
            'search'         => $this->search,
            'categoryFilter' => $this->categoryFilter,
            'statusFilter'   => $this->statusFilter,
            'outletFilter'   => $this->outletFilter,
            'costFilter'     => $this->costFilter,
            'perPage'        => $this->perPage,
        ]);
    }

    public function updatedSearch(): void         { $this->resetPage(); $this->clearSelection(); }
    public function updatedCategoryFilter(): void { $this->resetPage(); $this->clearSelection(); }
    public function updatedStatusFilter(): void   { $this->resetPage(); $this->clearSelection(); }
    public function updatedOutletFilter(): void   { $this->resetPage(); $this->clearSelection(); }
    public function updatedCostFilter(): void     { $this->resetPage(); $this->clearSelection(); }
    public function updatedTab(): void            { $this->resetPage(); $this->clearSelection(); }

    public function updatedPerPage($value): void
    {
        $this->perPage = in_array((int) $value, self::PER_PAGE_OPTIONS, true) ? (int) $value : 15;
        $this->resetPage();
        $this->clearSelection();
    }

    public function updatedSelectAll(bool $value): void
    {
        // Handled by Alpine on the frontend
    }

    public function updatedSelectedIds(): void
    {
        // Auto-update selectAll state based on selection
    }

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

    public function duplicate(int $id): void
    {
        if (! $this->assertUnlocked()) return;

        $original = Recipe::with(['lines', 'images', 'steps', 'prices', 'outlets'])->findOrFail($id);

        \Illuminate\Support\Facades\DB::transaction(function () use ($original) {
            $copy = $original->replicate(['cost_per_yield_unit']);
            $copy->name = $original->name . ' (COPY)';
            $copy->is_active = false;
            $copy->save();

            foreach ($original->lines as $line) {
                $copy->lines()->create($line->only([
                    'ingredient_id', 'quantity', 'uom_id', 'waste_percentage', 'sort_order', 'is_packaging',
                ]));
            }

            foreach ($original->images as $image) {
                $newPath = $this->copyStoredFile($image->file_path, 'recipe-images');
                $copy->images()->create([
                    'type'       => $image->type,
                    'file_name'  => $image->file_name,
                    'file_path'  => $newPath,
                    'mime_type'  => $image->mime_type,
                    'file_size'  => $image->file_size,
                    'sort_order' => $image->sort_order,
                ]);
            }

            foreach ($original->steps as $step) {
                $copy->steps()->create([
                    'sort_order'  => $step->sort_order,
                    'title'       => $step->title,
                    'instruction' => $step->instruction,
                    'image_path'  => $this->copyStoredFile($step->image_path, 'recipe-steps'),
                ]);
            }

            foreach ($original->prices as $price) {
                $copy->prices()->create([
                    'recipe_price_class_id' => $price->recipe_price_class_id,
                    'selling_price'         => $price->selling_price,
                ]);
            }

            $copy->outlets()->sync($original->outlets->pluck('id')->all());
        });

        session()->flash('success', 'Recipe duplicated. Edit the copy to make your changes.');
    }

    /** Copy a stored file to a new randomized path on the public disk; returns the new path or null. */
    private function copyStoredFile(?string $path, string $dir): ?string
    {
        if (! $path) return null;

        $disk = \Illuminate\Support\Facades\Storage::disk('public');
        if (! $disk->exists($path)) return $path;

        $newPath = $dir . '/' . \Illuminate\Support\Str::random(40) . '.' . pathinfo($path, PATHINFO_EXTENSION);
        $disk->copy($path, $newPath);

        return $newPath;
    }

    public function bulkDelete(): void
    {
        if (! $this->assertUnlocked()) return;
        $count = count($this->selectedIds);
        if ($count === 0) return;

        foreach (Recipe::whereIn('id', $this->selectedIds)->get() as $recipe) {
            \App\Services\AuditLogService::logDeletion($recipe);
        }
        Recipe::whereIn('id', $this->selectedIds)->delete();
        $this->clearSelection();

        $label = $this->tab === 'prep-items' ? 'prep item' : 'recipe';
        session()->flash('success', "{$count} {$label}(s) deleted.");
    }

    private function clearSelection(): void
    {
        $this->selectedIds = [];
        $this->selectAll = false;
    }

    public function toggleActive(int $id): void
    {
        if (! $this->assertUnlocked()) return;
        $r = Recipe::findOrFail($id);
        $r->update(['is_active' => ! $r->is_active]);
    }

    /**
     * Quick-edit a selling price from the list. $priceClassId 0 targets the
     * legacy recipes.selling_price column (companies without price classes).
     * An empty/zero value clears the class price so the class shows "—" again.
     */
    public function updatePrice(int $recipeId, int $priceClassId, $value): void
    {
        if (! $this->assertUnlocked()) return;

        $value = trim((string) $value);
        if ($value !== '' && (! is_numeric($value) || floatval($value) < 0 || floatval($value) > 999999)) {
            session()->flash('error', 'Please enter a valid price.');
            return;
        }
        $price = $value === '' ? 0.0 : round(floatval($value), 2);

        $recipe = Recipe::findOrFail($recipeId);

        if ($priceClassId === 0) {
            $recipe->update(['selling_price' => $price]);
            $this->forgetCategoryStats();
            return;
        }

        // Class must belong to this company (global scope enforces it).
        $class = \App\Models\RecipePriceClass::findOrFail($priceClassId);

        if ($price <= 0) {
            $recipe->prices()->where('recipe_price_class_id', $class->id)->delete();
        } else {
            $recipe->prices()->updateOrCreate(
                ['recipe_price_class_id' => $class->id],
                ['selling_price' => $price],
            );
        }

        $this->forgetCategoryStats();
    }

    private function categoryStatsCacheKey(): string
    {
        // v2: cards gained avgPct — versioned so stale cached shapes aren't served.
        return 'recipes.category-stats.v2.' . Auth::user()->company_id;
    }

    private function forgetCategoryStats(): void
    {
        \Illuminate\Support\Facades\Cache::forget($this->categoryStatsCacheKey());
    }

    /**
     * Per-category cost stat cards: one card per root category (Food,
     * Beverage, …) with the average recipe cost, a per-sub-category average
     * breakdown, and the highest/lowest food-cost-% recipe in the group.
     * Computing recipe costs loads every line relation, so the result is
     * cached briefly; quick price edits bust the cache.
     */
    private function buildCategoryStats(): array
    {
        return \Illuminate\Support\Facades\Cache::remember($this->categoryStatsCacheKey(), 300, function () {
            $recipes = Recipe::with([
                'lines.ingredient.baseUom',
                'lines.ingredient.uomConversions',
                'lines.uom',
                'prices.priceClass',
            ])
                ->where('is_prep', false)
                ->where('is_active', true)
                ->get();

            // Map category name → its category row, preferring sub-categories
            // (recipes.category stores a name that may exist at both levels).
            $cats = RecipeCategory::with('parent')->get();
            $byName = [];
            foreach ($cats as $cat) {
                if (! isset($byName[$cat->name]) || $cat->parent_id !== null) {
                    $byName[$cat->name] = $cat;
                }
            }

            $groups = [];
            foreach ($recipes as $recipe) {
                $cat  = $recipe->category ? ($byName[$recipe->category] ?? null) : null;
                $root = $cat?->parent?->name ?? $cat?->name ?? 'Uncategorised';
                $sub  = ($cat && $cat->parent_id !== null) ? $cat->name : null;

                $cost    = $recipe->total_cost;
                $selling = $recipe->effective_selling_price;
                $pct     = $selling > 0 ? ($cost / $selling) * 100 : null;

                $groups[$root]['sort']    = $groups[$root]['sort'] ?? ($byName[$root]->sort_order ?? 9999);
                $groups[$root]['costs'][] = $cost;
                if ($sub !== null) {
                    $groups[$root]['subs'][$sub]['costs'][] = $cost;
                }
                if ($pct !== null) {
                    $groups[$root]['pcts'][] = $pct;
                    if ($sub !== null) {
                        $groups[$root]['subs'][$sub]['pcts'][] = $pct;
                    }
                    if (! isset($groups[$root]['highest']) || $pct > $groups[$root]['highest']['pct']) {
                        $groups[$root]['highest'] = ['name' => $recipe->name, 'pct' => $pct];
                    }
                    if (! isset($groups[$root]['lowest']) || $pct < $groups[$root]['lowest']['pct']) {
                        $groups[$root]['lowest'] = ['name' => $recipe->name, 'pct' => $pct];
                    }
                }
            }

            $avg = fn (array $nums) => count($nums) ? round(array_sum($nums) / count($nums), 2) : null;

            $cards = [];
            foreach ($groups as $rootName => $g) {
                $cards[] = [
                    'name'    => $rootName,
                    'sort'    => $g['sort'],
                    'count'   => count($g['costs']),
                    'avgCost' => $avg($g['costs']),
                    'avgPct'  => $avg($g['pcts'] ?? []),
                    'subs'    => collect($g['subs'] ?? [])
                        ->map(fn ($sub, $name) => [
                            'name'    => $name,
                            'count'   => count($sub['costs']),
                            'avgCost' => $avg($sub['costs']),
                            'avgPct'  => $avg($sub['pcts'] ?? []),
                        ])
                        ->values()->all(),
                    'highest' => $g['highest'] ?? null,
                    'lowest'  => $g['lowest'] ?? null,
                ];
            }

            usort($cards, fn ($a, $b) => [$a['sort'], $a['name']] <=> [$b['sort'], $b['name']]);

            return $cards;
        });
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
        $this->persistFilters();

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
            $perPage = $this->perPage;
            $recipes = new LengthAwarePaginator(
                $filtered->forPage($page, $perPage)->values(),
                $filtered->count(),
                $perPage,
                $page,
                ['path' => request()->url()],
            );
        } else {
            $recipes = $query->paginate($this->perPage);
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

        // First two price classes (as ordered in Settings) become quick-edit
        // columns on the recipes tab. Empty for prep items (no selling price).
        $priceClasses = $isPrep
            ? collect()
            : \App\Models\RecipePriceClass::ordered()->take(2)->get();

        $categoryStats = $isPrep ? [] : $this->buildCategoryStats();

        return view('livewire.recipes.index', compact('recipes', 'recipeCategories', 'outlets', 'isPrep', 'priceClasses', 'categoryStats'))
            ->layout('layouts.app', ['title' => $isPrep ? 'Prep Items' : 'Recipes']);
    }
}
