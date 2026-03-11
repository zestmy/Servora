<?php

namespace App\Livewire\Recipes;

use App\Models\Recipe;
use App\Models\RecipeCategory;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public string $search = '';
    public string $categoryFilter = '';
    public string $statusFilter = 'all';

    public function updatedSearch(): void        { $this->resetPage(); }
    public function updatedCategoryFilter(): void { $this->resetPage(); }
    public function updatedStatusFilter(): void   { $this->resetPage(); }

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

        $recipes = $query->orderBy('name')->paginate(15);

        $recipeCategories = RecipeCategory::with(['children' => function ($q) {
                $q->where('is_active', true)->orderBy('sort_order')->orderBy('name');
            }])
            ->roots()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('livewire.recipes.index', compact('recipes', 'recipeCategories'))
            ->layout('layouts.app', ['title' => 'Recipes']);
    }
}
