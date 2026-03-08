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

    protected function rules(): array
    {
        return [
            'name'       => 'required|string|max:100',
            'color'      => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'sort_order' => 'required|integer|min:0|max:9999',
        ];
    }

    public function openCreate(): void
    {
        $this->resetForm();
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
        RecipeCategory::findOrFail($id)->delete();
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
        $categories = RecipeCategory::orderBy('sort_order')->orderBy('name')->get();

        // Count recipes per category name (stored as string, no FK)
        $recipeCounts = \App\Models\Recipe::selectRaw('category, count(*) as total')
            ->whereNotNull('category')
            ->groupBy('category')
            ->pluck('total', 'category');

        $colorOptions = RecipeCategory::colorOptions();

        return view('livewire.settings.recipe-categories', compact('categories', 'colorOptions', 'recipeCounts'))
            ->layout('layouts.app', ['title' => 'Recipe Categories']);
    }

    private function resetForm(): void
    {
        $this->editingId  = null;
        $this->name       = '';
        $this->color      = '#6366f1';
        $this->sort_order = '0';
        $this->is_active  = true;
        $this->resetValidation();
    }
}
