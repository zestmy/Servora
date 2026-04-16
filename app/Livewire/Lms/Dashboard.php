<?php

namespace App\Livewire\Lms;

use App\Models\IngredientCategory;
use App\Models\Recipe;
use App\Models\RecipeCategory;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Dashboard extends Component
{
    public string $search = '';
    public string $categoryFilter = '';

    public function render()
    {
        $user = Auth::guard('lms')->user();

        $outletScope = fn ($q) => $user->outlet_id
            ? $q->where(function ($q) use ($user) {
                $q->whereDoesntHave('outlets')
                  ->orWhereHas('outlets', fn ($o) => $o->where('outlets.id', $user->outlet_id));
            })
            : $q;

        // Build parent/sub hierarchy sort map (same approach as LMS sidebar)
        $categorySortMap = [];
        $allRecipeCategories = RecipeCategory::where('company_id', $user->company_id)
            ->with('parent')
            ->get();
        foreach ($allRecipeCategories as $rc) {
            $parentSort = $rc->parent ? $rc->parent->sort_order : $rc->sort_order;
            $parentName = $rc->parent ? strtolower($rc->parent->name) : strtolower($rc->name);
            $subSort    = $rc->parent ? $rc->sort_order : 0;
            $subName    = $rc->parent ? strtolower($rc->name) : '';
            $categorySortMap[strtolower($rc->name)] = [$parentSort, $parentName, $subSort, $subName];
        }

        $prepCategorySortMap = IngredientCategory::where('company_id', $user->company_id)
            ->with('parent')
            ->get()
            ->mapWithKeys(fn ($ic) => [
                $ic->id => [
                    $ic->parent ? $ic->parent->sort_order : $ic->sort_order,
                    strtolower($ic->parent ? $ic->parent->name : $ic->name),
                    $ic->parent ? $ic->sort_order : 0,
                ],
            ]);

        // Build category filter names (include children when parent selected)
        $filterNames = null;
        if ($this->categoryFilter) {
            $selectedCat = RecipeCategory::with('children')->find((int) $this->categoryFilter);
            if ($selectedCat) {
                $filterNames = collect([$selectedCat->name]);
                if ($selectedCat->children->isNotEmpty()) {
                    $filterNames = $filterNames->merge($selectedCat->children->pluck('name'));
                }
            }
        }

        $recipes = Recipe::where('company_id', $user->company_id)
            ->where('is_active', true)
            ->where('exclude_from_lms', false)
            ->tap($outletScope)
            ->with(['images', 'steps', 'ingredientCategory'])
            ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->when($filterNames, fn ($q) => $q->whereIn('category', $filterNames->toArray()))
            ->get()
            ->sortBy(function ($r) use ($categorySortMap, $prepCategorySortMap) {
                if ($r->is_prep) {
                    $ps = $prepCategorySortMap[$r->ingredient_category_id] ?? [PHP_INT_MAX, '~', 0];
                    return [1, $ps[0], $ps[1], $ps[2], $r->menu_sort_order ?? 0, strtolower($r->name)];
                }
                $cs = $categorySortMap[strtolower($r->category ?? '')] ?? [PHP_INT_MAX, '~', 0, ''];
                return [0, $cs[0], $cs[1], $cs[2], $r->menu_sort_order ?? 0, strtolower($r->name)];
            })
            ->values();

        // Category dropdown with hierarchy
        $categories = RecipeCategory::with(['children' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order')->orderBy('name')])
            ->roots()
            ->where('company_id', $user->company_id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $grouped = $recipes->groupBy(fn ($r) => $r->is_prep
            ? 'Prep — ' . ($r->ingredientCategory?->name ?? 'Uncategorised')
            : ($r->category ?? 'Uncategorised'));

        return view('livewire.lms.dashboard', compact('recipes', 'categories', 'grouped'))
            ->layout('layouts.lms', ['title' => 'Training SOPs']);
    }
}
