<?php

namespace App\Livewire\Settings;

use App\Models\CostType;
use App\Models\IngredientCategory;
use App\Models\SalesCategory;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class SalesCategories extends Component
{
    public bool $showModal = false;
    public ?int $editingId = null;

    public string $name                    = '';
    public string $type                    = 'food';
    public ?int   $ingredient_category_id  = null;
    public string $color                   = '#22c55e';
    public string $sort_order              = '0';
    public bool   $is_active               = true;

    protected function rules(): array
    {
        return [
            'name'                    => 'required|string|max:100',
            'type'                    => 'required|in:' . implode(',', array_keys(CostType::options())),
            'ingredient_category_id'  => 'nullable|exists:ingredient_categories,id',
            'color'                   => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'sort_order'              => 'required|integer|min:0|max:9999',
        ];
    }

    public function openCreate(): void
    {
        $this->resetForm();
        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        $cat = SalesCategory::findOrFail($id);

        $this->editingId              = $cat->id;
        $this->name                   = $cat->name;
        $this->type                   = $cat->type;
        $this->ingredient_category_id = $cat->ingredient_category_id;
        $this->color                  = $cat->color;
        $this->sort_order             = (string) $cat->sort_order;
        $this->is_active              = $cat->is_active;

        $this->showModal = true;
    }

    public function save(): void
    {
        $this->validate();

        $data = [
            'name'                   => $this->name,
            'type'                   => $this->type,
            'ingredient_category_id' => $this->ingredient_category_id,
            'color'                  => $this->color,
            'sort_order'             => (int) $this->sort_order,
            'is_active'              => $this->is_active,
        ];

        if ($this->editingId) {
            SalesCategory::findOrFail($this->editingId)->update($data);
            session()->flash('success', 'Sales category updated.');
        } else {
            $data['company_id'] = Auth::user()->company_id;
            SalesCategory::create($data);
            session()->flash('success', 'Sales category created.');
        }

        $this->closeModal();
    }

    public function delete(int $id): void
    {
        SalesCategory::findOrFail($id)->delete();
        session()->flash('success', 'Sales category deleted.');
    }

    public function toggleActive(int $id): void
    {
        $cat = SalesCategory::findOrFail($id);
        $cat->update(['is_active' => ! $cat->is_active]);
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    public function render()
    {
        $categories   = SalesCategory::withTrashed()->with('ingredientCategory')->orderBy('sort_order')->orderBy('name')->get();
        $typeOptions  = SalesCategory::typeOptions();
        $colorOptions = SalesCategory::colorOptions();
        $costCenters  = IngredientCategory::roots()->active()->revenue()->ordered()->get();

        // Count sales lines per category
        $lineCounts = \App\Models\SalesRecordLine::selectRaw('sales_category_id, count(*) as total')
            ->whereNotNull('sales_category_id')
            ->groupBy('sales_category_id')
            ->pluck('total', 'sales_category_id');

        return view('livewire.settings.sales-categories', compact('categories', 'typeOptions', 'colorOptions', 'costCenters', 'lineCounts'))
            ->layout('layouts.app', ['title' => 'Sales Categories']);
    }

    private function resetForm(): void
    {
        $this->editingId              = null;
        $this->name                   = '';
        $this->type                   = 'food';
        $this->ingredient_category_id = null;
        $this->color                  = '#22c55e';
        $this->sort_order             = '0';
        $this->is_active              = true;
        $this->resetValidation();
    }
}
