<?php

namespace App\Livewire\Recipes;

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

    public string $search = '';
    public string $categoryFilter = '';
    public string $statusFilter = 'all';
    public string $outletFilter = '';
    public string $costFilter = '';

    public function updatedSearch(): void         { $this->resetPage(); }
    public function updatedCategoryFilter(): void { $this->resetPage(); }
    public function updatedStatusFilter(): void   { $this->resetPage(); }
    public function updatedOutletFilter(): void   { $this->resetPage(); }
    public function updatedCostFilter(): void     { $this->resetPage(); }

    public function delete(int $id): void
    {
        Recipe::findOrFail($id)->delete();
        session()->flash('success', 'Recipe deleted.');
    }

    public function toggleActive(int $id): void
    {
        $r = Recipe::findOrFail($id);
        $r->update(['is_active' => ! $r->is_active]);
    }

    public function render()
    {
        $query = Recipe::with([
            'yieldUom',
            'outlets',
            'lines.ingredient.baseUom',
            'lines.ingredient.uomConversions',
            'lines.uom',
        ])->withCount('lines');

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                  ->orWhere('code', 'like', '%' . $this->search . '%');
            });
        }

        if ($this->categoryFilter) {
            // Match both the selected category name and any sub-category names
            $selectedCat = RecipeCategory::with('children')->find((int) $this->categoryFilter);
            if ($selectedCat) {
                $names = collect([$selectedCat->name]);
                if ($selectedCat->children->isNotEmpty()) {
                    $names = $names->merge($selectedCat->children->pluck('name'));
                }
                $query->whereIn('category', $names->toArray());
            }
        }

        if ($this->statusFilter === 'active') {
            $query->where('is_active', true);
        } elseif ($this->statusFilter === 'inactive') {
            $query->where('is_active', false);
        }

        if ($this->outletFilter) {
            $outletId = (int) $this->outletFilter;
            // Show recipes tagged to this outlet OR recipes with no outlet tags (available everywhere)
            $query->where(function ($q) use ($outletId) {
                $q->whereHas('outlets', fn ($sub) => $sub->where('outlets.id', $outletId))
                  ->orWhereDoesntHave('outlets');
            });
        }

        if ($this->costFilter) {
            $allRecipes = $query->orderBy('name')->get();

            $filtered = $allRecipes->filter(function ($recipe) {
                $totalCost = $recipe->total_cost;
                $selling   = floatval($recipe->selling_price);
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
            $recipes = $query->orderBy('name')->paginate(15);
        }

        $recipeCategories = RecipeCategory::with(['children' => function ($q) {
                $q->where('is_active', true)->orderBy('sort_order')->orderBy('name');
            }])
            ->roots()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $outlets = Outlet::where('company_id', Auth::user()->company_id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('livewire.recipes.index', compact('recipes', 'recipeCategories', 'outlets'))
            ->layout('layouts.app', ['title' => 'Recipes']);
    }
}
