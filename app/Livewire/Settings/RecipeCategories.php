<?php

namespace App\Livewire\Settings;

use App\Models\RecipeCategory;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class RecipeCategories extends Component
{
    public bool $showModal = false;
    public ?int $editingId = null;

    public string $name = '';
    public string $color = '#6366f1';
    public string $sort_order = '0';
    public bool $is_active = true;
    public ?int $parent_id = null;

    protected function rules(): array
    {
        return [
            'name'       => 'required|string|max:100',
            'color'      => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'sort_order' => 'required|integer|min:0|max:9999',
            'parent_id'  => 'nullable|exists:recipe_categories,id',
        ];
    }

    public function openCreate(?int $parentId = null): void
    {
        $this->resetForm();
        $this->parent_id = $parentId;

        // Inherit parent color for sub-categories
        if ($parentId) {
            $parent = RecipeCategory::find($parentId);
            if ($parent) {
                $this->color = $parent->color;
            }
        }

        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        $cat = RecipeCategory::findOrFail($id);

        $this->editingId  = $cat->id;
        $this->name       = $cat->name;
        $this->color      = $cat->color;
        $this->sort_order = (string) $cat->sort_order;
        $this->is_active  = $cat->is_active;
        $this->parent_id  = $cat->parent_id;

        $this->showModal = true;
    }

    public function save(): void
    {
        $this->validate();

        $data = [
            'name'       => $this->name,
            'color'      => $this->color,
            'sort_order' => (int) $this->sort_order,
            'is_active'  => $this->is_active,
            'parent_id'  => $this->parent_id,
        ];

        if ($this->editingId) {
            RecipeCategory::findOrFail($this->editingId)->update($data);
            session()->flash('success', 'Category updated.');
        } else {
            $data['company_id'] = Auth::user()->company_id;
            RecipeCategory::create($data);
            session()->flash('success', 'Category created.');
        }

        $this->closeModal();
    }

    public function delete(int $id): void
    {
        $cat = RecipeCategory::withCount('children')->findOrFail($id);
        if ($cat->children_count > 0) {
            session()->flash('error', 'Cannot delete a category that has sub-categories. Remove sub-categories first.');
            return;
        }
        $cat->delete();
        session()->flash('success', 'Category deleted.');
    }

    public function toggleActive(int $id): void
    {
        $cat = RecipeCategory::findOrFail($id);
        $cat->update(['is_active' => ! $cat->is_active]);
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    public function render()
    {
        $categories = RecipeCategory::with(['children' => function ($q) {
            $q->orderBy('sort_order')->orderBy('name');
        }])
            ->roots()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        // Count recipes per category name (stored as string, no FK)
        $recipeCounts = \App\Models\Recipe::selectRaw('category, count(*) as total')
            ->whereNotNull('category')
            ->groupBy('category')
            ->pluck('total', 'category');

        $colorOptions = RecipeCategory::colorOptions();

        return view('livewire.settings.recipe-categories', compact('categories', 'colorOptions', 'recipeCounts'))
            ->layout(\App\Helpers\WorkspaceLayout::get(), ['title' => 'Recipe Categories']);
    }

    private function resetForm(): void
    {
        $this->editingId  = null;
        $this->name       = '';
        $this->color      = '#6366f1';
        $this->sort_order = '0';
        $this->is_active  = true;
        $this->parent_id  = null;
        $this->resetValidation();
    }
}
