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
            $query->where('category', $this->categoryFilter);
        }

        if ($this->statusFilter === 'active') {
            $query->where('is_active', true);
        } elseif ($this->statusFilter === 'inactive') {
            $query->where('is_active', false);
        }

        $recipes = $query->orderBy('name')->paginate(15);

        $recipeCategories = RecipeCategory::orderBy('sort_order')->orderBy('name')->get();

        return view('livewire.recipes.index', compact('recipes', 'recipeCategories'))
            ->layout('layouts.app', ['title' => 'Recipes']);
    }
}
