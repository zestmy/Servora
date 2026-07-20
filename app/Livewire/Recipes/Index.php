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

    // Activity slide-over (recent add/update/delete audit trail)
    public bool $showActivityLog = false;

    protected $queryString = ['tab'];

    /** Session key holding the last-chosen list filters so they survive navigation (e.g. saving the form). */
    private const FILTERS_KEY = 'recipes.index.filters';

    public function mount(): void
    {
        // The tab is owned by the URL query string, never the session — otherwise
        // opening Prep Items would be forced back to the last-used Recipes tab.
        $this->tab = request()->query('tab') === 'prep-items' ? 'prep-items' : 'recipes';

        // Filters are scoped per tab so a filter picked while browsing Recipes
        // doesn't silently follow you to Prep Items (and vice versa).
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
        $recipe = Recipe::findOrFail($id);

        if ($recipe->is_prep) {
            $error = $this->deletePrepRecipe($recipe);
            if ($error) {
                session()->flash('error', $error);
                return;
            }
            session()->flash('success', 'Prep item deleted.');
            return;
        }

        $recipe->delete();
        session()->flash('success', 'Recipe deleted.');
    }

    /**
     * Delete a prep recipe together with its linked ingredient — otherwise the
     * ingredient stays behind as an orphan that still appears in ingredient
     * search but no longer exists in the Prep Items list (and re-creating the
     * prep item then duplicates it). Blocked while other live recipes still
     * use the ingredient; returns the error message when blocked, null on success.
     */
    private function deletePrepRecipe(Recipe $recipe): ?string
    {
        $ingredient = $recipe->ingredient;

        if ($ingredient) {
            $usedBy = \App\Models\RecipeLine::where('ingredient_id', $ingredient->id)
                ->whereHas('recipe', fn ($q) => $q->where('recipes.id', '!=', $recipe->id))
                ->with('recipe:id,name,is_prep')
                ->get()
                ->pluck('recipe.name')
                ->filter()
                ->unique()
                ->values();

            if ($usedBy->isNotEmpty()) {
                $names = $usedBy->take(5)->implode(', ') . ($usedBy->count() > 5 ? ', …' : '');
                return "Cannot delete '{$recipe->name}' — it is used as an ingredient in: {$names}. Remove it from those recipes first.";
            }

            $ingredient->delete();
        }

        $recipe->delete();

        return null;
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
        if (count($this->selectedIds) === 0) return;

        $deleted = 0;
        $blocked = [];

        // Model deletes (not a bulk query) so the audit observer logs each one
        // and prep items take their linked ingredient along.
        foreach (Recipe::whereIn('id', $this->selectedIds)->get() as $recipe) {
            if ($recipe->is_prep) {
                if ($this->deletePrepRecipe($recipe)) {
                    $blocked[] = $recipe->name;
                    continue;
                }
            } else {
                $recipe->delete();
            }
            $deleted++;
        }
        $this->clearSelection();

        $label = $this->tab === 'prep-items' ? 'prep item' : 'recipe';
        if (! empty($blocked)) {
            $names = implode(', ', array_slice($blocked, 0, 5)) . (count($blocked) > 5 ? ', …' : '');
            session()->flash('error', "{$deleted} {$label}(s) deleted. Skipped (still used as an ingredient in other recipes): {$names}.");
        } else {
            session()->flash('success', "{$deleted} {$label}(s) deleted.");
        }
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
        // v3: case-insensitive category matching — versioned so stale cached
        // shapes aren't served.
        return 'recipes.category-stats.v3.' . Auth::user()->company_id;
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
            // Keys are lowercased: recipes.category is matched case-insensitively
            // everywhere else (MySQL collation), so "RETAIL" must hit "Retail".
            $cats = RecipeCategory::with('parent')->get();
            $byName = [];
            foreach ($cats as $cat) {
                $key = mb_strtolower($cat->name);
                if (! isset($byName[$key]) || $cat->parent_id !== null) {
                    $byName[$key] = $cat;
                }
            }

            $groups = [];
            foreach ($recipes as $recipe) {
                $cat  = $recipe->category ? ($byName[mb_strtolower($recipe->category)] ?? null) : null;
                $root = $cat?->parent?->name ?? $cat?->name ?? 'Uncategorised';
                $sub  = ($cat && $cat->parent_id !== null) ? $cat->name : null;

                $cost    = $recipe->total_cost;
                $selling = $recipe->effective_selling_price;
                $pct     = $selling > 0 ? ($cost / $selling) * 100 : null;

                $groups[$root]['sort']    = $groups[$root]['sort'] ?? ($byName[mb_strtolower($root)]->sort_order ?? 9999);
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
        ])
            ->where('recipes.is_prep', $isPrep);

        // Recipes AND prep items both group by the menu category string
        // (which FK's by name to recipe_categories).
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

        $query->select('recipes.*')->withCount('lines');

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('recipes.name', 'like', '%' . $this->search . '%')
                  ->orWhere('recipes.code', 'like', '%' . $this->search . '%');
            });
        }

        if ($this->categoryFilter === 'uncategorized') {
            // Same definition as the stat cards' "Uncategorised" group: the
            // category string matches no recipe category (or is empty).
            $query->whereNull('rc.id')->whereNull('rc_root.id');
        } elseif ($this->categoryFilter) {
            // Both tabs match by the menu-category string.
            $selectedCat = RecipeCategory::with('children')->find((int) $this->categoryFilter);
            if ($selectedCat) {
                $names = collect([$selectedCat->name]);
                if ($selectedCat->children->isNotEmpty()) {
                    $names = $names->merge($selectedCat->children->pluck('name'));
                }
                $query->whereIn('recipes.category', $names->toArray());
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
        // rc = sub-category match, rc_root = root fallback, rcp = parent of sub
        // Parent sort: if sub matched → use rcp (parent), else use rc_root
        $query->orderByRaw('COALESCE(rcp.sort_order, rc_root.sort_order, rc.sort_order) IS NULL')
              ->orderByRaw('COALESCE(rcp.sort_order, rc_root.sort_order, rc.sort_order) ASC')
              ->orderByRaw('COALESCE(rcp.name, rc_root.name, rc.name) ASC')
              ->orderByRaw('COALESCE(rc.sort_order, rc_root.sort_order) ASC')
              ->orderByRaw('COALESCE(rc.name, rc_root.name) ASC')
              ->orderBy('recipes.menu_sort_order')
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

        // Both tabs share the same menu categories (Settings → Recipe Categories).
        $recipeCategories = RecipeCategory::with(['children' => function ($q) {
                $q->where('is_active', true)->orderBy('sort_order')->orderBy('name');
            }])
            ->roots()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        // Central kitchen outlets are included so items tagged to the central
        // kitchen can be filtered too; the dropdown labels them "(CK)".
        $centralKitchenOutletIds = CentralKitchen::whereNotNull('outlet_id')
            ->pluck('outlet_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->all();
        $outlets = Outlet::where('company_id', Auth::user()->company_id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        // First two price classes (as ordered in Settings) become quick-edit
        // columns on the recipes tab. Empty for prep items (no selling price).
        $priceClasses = $isPrep
            ? collect()
            : \App\Models\RecipePriceClass::ordered()->take(2)->get();

        $categoryStats = $isPrep ? [] : $this->buildCategoryStats();

        // Activity slide-over: latest recipe/prep audit entries for the active
        // tab (who added / updated / deleted what). Both tabs audit the Recipe
        // model, so narrow by is_prep via a subquery (withTrashed keeps entries
        // for soft-deleted records attributable). Queried only while open.
        $activityLogs   = collect();
        $activityLabels = [];
        if ($this->showActivityLog) {
            // Prep tab: only entries for known prep recipes. Recipes tab: every-
            // thing EXCEPT known preps, so entries for hard-deleted records
            // (is_prep no longer resolvable) still show up on exactly one tab.
            $prepIds = Recipe::withTrashed()->where('is_prep', true)->select('id');
            $activityLogs = \App\Models\AuditLog::with('user')
                ->where('auditable_type', Recipe::class)
                ->when($isPrep,
                    fn ($q) => $q->whereIn('auditable_id', $prepIds),
                    fn ($q) => $q->whereNotIn('auditable_id', $prepIds))
                ->orderByDesc('created_at')->orderByDesc('id')
                ->limit(40)
                ->get();
            $activityLabels = \App\Services\AuditLogService::recordLabels($activityLogs);
        }

        return view('livewire.recipes.index', compact(
            'recipes', 'recipeCategories', 'outlets', 'centralKitchenOutletIds', 'isPrep', 'priceClasses', 'categoryStats',
            'activityLogs', 'activityLabels'
        ))->layout('layouts.app', ['title' => $isPrep ? 'Prep Items' : 'Recipes']);
    }
}
