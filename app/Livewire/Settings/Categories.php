<?php

namespace App\Livewire\Settings;

use App\Models\IngredientCategory;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Categories extends Component
{
    public bool $showModal  = false;
    public ?int $editingId  = null;
    public ?int $parentId   = null; // null = main category, int = sub under this parent

    public string  $name       = '';
    public string  $color      = '#6366f1';
    public string  $sort_order = '0';
    public bool    $is_active  = true;

    protected function rules(): array
    {
        return [
            'name'       => 'required|string|max:100',
            'color'      => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'sort_order' => 'required|integer|min:0|max:9999',
        ];
    }

    public function openCreateMain(): void
    {
        $this->resetForm();
        $this->parentId  = null;
        $this->showModal = true;
    }

    public function openCreateSub(int $parentId): void
    {
        $this->resetForm();
        $this->parentId  = $parentId;
        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        $cat = IngredientCategory::findOrFail($id);

        $this->editingId  = $cat->id;
        $this->parentId   = $cat->parent_id;
        $this->name       = $cat->name;
        $this->color      = $cat->color;
        $this->sort_order = (string) $cat->sort_order;
        $this->is_active  = $cat->is_active;

        $this->showModal = true;
    }

    public function save(): void
    {
        $this->validate();

        $data = [
            'parent_id'  => $this->parentId,
            'name'       => $this->name,
            'color'      => $this->color,
            'sort_order' => (int) $this->sort_order,
            'is_active'  => $this->is_active,
        ];

        if ($this->editingId) {
            IngredientCategory::findOrFail($this->editingId)->update($data);
            session()->flash('success', 'Category updated.');
        } else {
            $data['company_id'] = Auth::user()->company_id;
            IngredientCategory::create($data);
            session()->flash('success', 'Category created.');
        }

        $this->closeModal();
    }

    public function delete(int $id): void
    {
        $cat = IngredientCategory::withCount(['ingredients', 'recipes', 'children'])->findOrFail($id);

        if ($cat->ingredients_count > 0 || $cat->recipes_count > 0) {
            session()->flash('error', "Cannot delete \"{$cat->name}\" — it has items assigned to it.");
            return;
        }

        if ($cat->children_count > 0) {
            session()->flash('error', "Cannot delete \"{$cat->name}\" — it has sub-categories. Delete them first.");
            return;
        }

        $cat->delete();
        session()->flash('success', 'Category deleted.');
    }

    public function toggleActive(int $id): void
    {
        $cat = IngredientCategory::findOrFail($id);
        $cat->update(['is_active' => ! $cat->is_active]);
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    public function render()
    {
        $categories = IngredientCategory::roots()
            ->with(['children' => fn ($q) => $q->withCount(['ingredients', 'recipes'])])
            ->withCount(['ingredients', 'recipes', 'children'])
            ->ordered()
            ->get();

        $colorOptions = IngredientCategory::colorOptions();

        $parentCategory = $this->parentId
            ? IngredientCategory::find($this->parentId)
            : null;

        return view('livewire.settings.categories', compact('categories', 'colorOptions', 'parentCategory'))
            ->layout(\App\Helpers\WorkspaceLayout::get(), ['title' => 'Categories']);
    }

    private function resetForm(): void
    {
        $this->editingId  = null;
        $this->parentId   = null;
        $this->name       = '';
        $this->color      = '#6366f1';
        $this->sort_order = '0';
        $this->is_active  = true;
        $this->resetValidation();
    }
}
