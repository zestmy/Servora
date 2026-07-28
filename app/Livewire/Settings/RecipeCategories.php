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

    /**
     * Outlet menu categories and Central Kitchen production categories are
     * separate lists. This screen always edits the one belonging to the
     * workspace the user is currently in.
     */
    protected function scope(): string
    {
        return RecipeCategory::currentScope();
    }

    protected function rules(): array
    {
        return [
            'name'       => 'required|string|max:100',
            'color'      => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'sort_order' => 'required|integer|min:0|max:9999',
            // A sub-category can only sit under a parent from the same list.
            'parent_id'  => [
                'nullable',
                \Illuminate\Validation\Rule::exists('recipe_categories', 'id')
                    ->where('scope', $this->scope())
                    ->whereNull('deleted_at'),
            ],
        ];
    }

    public function openCreate(?int $parentId = null): void
    {
        $this->resetForm();
        $this->parent_id = $parentId;

        // Inherit parent color for sub-categories
        if ($parentId) {
            $parent = RecipeCategory::inScope($this->scope())->find($parentId);
            if ($parent) {
                $this->color = $parent->color;
            }
        }

        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        $cat = RecipeCategory::inScope($this->scope())->findOrFail($id);

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
            $cat = RecipeCategory::inScope($this->scope())->findOrFail($this->editingId);
            $oldName = $cat->name;
            $cat->update($data);

            // Sync the text category field on whichever recipes use this list
            if ($oldName !== $this->name) {
                if ($this->scope() === RecipeCategory::SCOPE_KITCHEN) {
                    \App\Models\ProductionRecipe::where('category', $oldName)->update(['category' => $this->name]);
                } else {
                    \App\Models\Recipe::where('category', $oldName)->update(['category' => $this->name]);
                }
            }

            session()->flash('success', 'Category updated.');
        } else {
            $data['company_id'] = Auth::user()->company_id;
            $data['scope']      = $this->scope();
            RecipeCategory::create($data);
            session()->flash('success', 'Category created.');
        }

        $this->closeModal();
    }

    public function delete(int $id): void
    {
        $cat = RecipeCategory::inScope($this->scope())->withCount('children')->findOrFail($id);
        if ($cat->children_count > 0) {
            session()->flash('error', 'Cannot delete a category that has sub-categories. Remove sub-categories first.');
            return;
        }
        $cat->delete();
        session()->flash('success', 'Category deleted.');
    }

    public function toggleActive(int $id): void
    {
        $cat = RecipeCategory::inScope($this->scope())->findOrFail($id);
        $cat->update(['is_active' => ! $cat->is_active]);
    }

    public function reorderParents(array $orderedIds): void
    {
        foreach ($orderedIds as $index => $id) {
            RecipeCategory::inScope($this->scope())
                ->where('id', (int) $id)
                ->update(['sort_order' => $index]);
        }
    }

    public function reorderChildren(int $parentId, array $orderedIds): void
    {
        foreach ($orderedIds as $index => $id) {
            RecipeCategory::inScope($this->scope())
                ->where('id', (int) $id)
                ->where('parent_id', $parentId)
                ->update(['sort_order' => $index]);
        }
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    public function render()
    {
        $scope = $this->scope();

        $categories = RecipeCategory::with(['children' => function ($q) {
            $q->orderBy('sort_order')->orderBy('name');
        }])
            ->inScope($scope)
            ->roots()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        // Count whatever this list actually categorises (category is a string,
        // matched by name — there's no FK either side).
        $isKitchen    = $scope === RecipeCategory::SCOPE_KITCHEN;
        $recipeCounts = ($isKitchen ? \App\Models\ProductionRecipe::query() : \App\Models\Recipe::query())
            ->selectRaw('category, count(*) as total')
            ->whereNotNull('category')
            ->groupBy('category')
            ->pluck('total', 'category');

        $colorOptions = RecipeCategory::colorOptions();
        $scopeLabel   = RecipeCategory::SCOPES[$scope];
        $countLabel   = $isKitchen ? 'production recipe' : 'recipe';

        return view('livewire.settings.recipe-categories', compact(
            'categories', 'colorOptions', 'recipeCounts', 'scope', 'isKitchen', 'scopeLabel', 'countLabel'
        ))->layout(\App\Helpers\WorkspaceLayout::get(), [
            'title' => $isKitchen ? 'Production Categories' : 'Recipe Categories',
        ]);
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
