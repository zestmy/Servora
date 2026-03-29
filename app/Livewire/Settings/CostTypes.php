<?php

namespace App\Livewire\Settings;

use App\Models\CostType;
use App\Models\IngredientCategory;
use App\Models\SalesCategory;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Component;

class CostTypes extends Component
{
    public bool $showModal = false;
    public ?int $editingId = null;

    public string $name       = '';
    public string $slug       = '';
    public string $color      = '#6b7280';
    public string $sort_order = '0';
    public bool   $is_active  = true;

    protected function rules(): array
    {
        $companyId = Auth::user()->company_id;
        $unique = $this->editingId
            ? "unique:cost_types,slug,{$this->editingId},id,company_id,{$companyId}"
            : "unique:cost_types,slug,NULL,id,company_id,{$companyId}";

        return [
            'name'       => 'required|string|max:100',
            'slug'       => ['required', 'string', 'max:30', 'regex:/^[a-z0-9_]+$/', $unique],
            'color'      => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'sort_order' => 'required|integer|min:0|max:9999',
        ];
    }

    protected function messages(): array
    {
        return [
            'slug.regex'  => 'Slug must be lowercase letters, numbers and underscores only.',
            'slug.unique' => 'This slug is already taken.',
        ];
    }

    public function updatedName(): void
    {
        if (! $this->editingId) {
            $this->slug = Str::slug($this->name, '_');
        }
    }

    public function openCreate(): void
    {
        $this->resetForm();
        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        $ct = CostType::findOrFail($id);

        $this->editingId  = $ct->id;
        $this->name       = $ct->name;
        $this->slug       = $ct->slug;
        $this->color      = $ct->color;
        $this->sort_order = (string) $ct->sort_order;
        $this->is_active  = $ct->is_active;

        $this->showModal = true;
    }

    public function save(): void
    {
        $this->validate();

        $data = [
            'name'       => $this->name,
            'slug'       => $this->slug,
            'color'      => $this->color,
            'sort_order' => (int) $this->sort_order,
            'is_active'  => $this->is_active,
        ];

        if ($this->editingId) {
            CostType::findOrFail($this->editingId)->update($data);
            session()->flash('success', 'Cost type updated.');
        } else {
            $data['company_id'] = Auth::user()->company_id;
            CostType::create($data);
            session()->flash('success', 'Cost type created.');
        }

        $this->closeModal();
    }

    public function delete(int $id): void
    {
        $ct = CostType::findOrFail($id);

        // Check usage
        $usedByCategories = IngredientCategory::where('type', $ct->slug)->count();
        $usedBySales = SalesCategory::where('type', $ct->slug)->count();

        if ($usedByCategories > 0 || $usedBySales > 0) {
            session()->flash('error', "Cannot delete \"{$ct->name}\" — it is used by {$usedByCategories} ingredient " .
                Str::plural('category', $usedByCategories) . " and {$usedBySales} sales " .
                Str::plural('category', $usedBySales) . '.');
            return;
        }

        $ct->delete();
        session()->flash('success', 'Cost type deleted.');
    }

    public function toggleActive(int $id): void
    {
        $ct = CostType::findOrFail($id);
        $ct->update(['is_active' => ! $ct->is_active]);
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    public function render()
    {
        $costTypes = CostType::withTrashed()->ordered()->get();

        // Count usage
        $categoryUsage = IngredientCategory::selectRaw('type, count(*) as total')
            ->whereNotNull('type')
            ->groupBy('type')
            ->pluck('total', 'type');

        $salesUsage = SalesCategory::selectRaw('type, count(*) as total')
            ->groupBy('type')
            ->pluck('total', 'type');

        $colorOptions = [
            '#ef4444' => 'Red',    '#f97316' => 'Orange', '#eab308' => 'Yellow',
            '#22c55e' => 'Green',  '#14b8a6' => 'Teal',   '#3b82f6' => 'Blue',
            '#6366f1' => 'Indigo', '#a855f7' => 'Purple', '#ec4899' => 'Pink',
            '#6b7280' => 'Gray',
        ];

        return view('livewire.settings.cost-types', compact('costTypes', 'categoryUsage', 'salesUsage', 'colorOptions'))
            ->layout(\App\Helpers\WorkspaceLayout::get(), ['title' => 'Cost Types']);
    }

    private function resetForm(): void
    {
        $this->editingId  = null;
        $this->name       = '';
        $this->slug       = '';
        $this->color      = '#6b7280';
        $this->sort_order = '0';
        $this->is_active  = true;
        $this->resetValidation();
    }
}
