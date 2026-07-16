<?php

namespace App\Livewire\Lms;

use App\Models\Recipe;
use App\Models\RecipeCategory;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Dashboard extends Component
{
    public string $search = '';
    public string $categoryFilter = '';

    // Keep the active filters in the URL so they survive opening an SOP and
    // pressing "Back to all SOPs" (the back link carries these params).
    protected $queryString = [
        'search'         => ['except' => ''],
        'categoryFilter' => ['except' => ''],
    ];

    public function render()
    {
        $user = Auth::guard('lms')->user();

        // Per-user SOP access (Settings > Training Portal): untagged recipes plus
        // recipes tagged to any outlet the trainee has been granted.
        $accessibleOutletIds = $user->accessibleOutletIds();
        $outletScope = fn ($q) => $q->visibleToOutlets($accessibleOutletIds);

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

        // Prep items use the same menu categories as recipes (recipes.category);
        // they sort by the same hierarchy but group after the recipe sections.
        $recipes = Recipe::where('company_id', $user->company_id)
            ->where('is_active', true)
            ->where('exclude_from_lms', false)
            ->tap($outletScope)
            ->with(['images', 'steps'])
            ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->when($filterNames, fn ($q) => $q->whereIn('category', $filterNames->toArray()))
            ->get()
            ->sortBy(function ($r) use ($categorySortMap) {
                $cs = $categorySortMap[strtolower($r->category ?? '')] ?? [PHP_INT_MAX, '~', 0, ''];
                return [$r->is_prep ? 1 : 0, $cs[0], $cs[1], $cs[2], $cs[3], $r->menu_sort_order ?? 0, strtolower($r->name)];
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
            ? 'Prep — ' . ($r->category ?? 'Uncategorised')
            : ($r->category ?? 'Uncategorised'));

        return view('livewire.lms.dashboard', compact('recipes', 'categories', 'grouped'))
            ->layout('layouts.lms', ['title' => 'Training SOPs']);
    }
}
