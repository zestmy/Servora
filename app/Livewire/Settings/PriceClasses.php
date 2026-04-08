<?php

namespace App\Livewire\Settings;

use App\Models\RecipePrice;
use App\Models\RecipePriceClass;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class PriceClasses extends Component
{
    public bool $showModal = false;
    public ?int $editingId = null;

    public string $name       = '';
    public string $sort_order = '0';
    public bool   $is_default = false;

    protected function rules(): array
    {
        return [
            'name'       => 'required|string|max:100',
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
        $pc = RecipePriceClass::findOrFail($id);

        $this->editingId  = $pc->id;
        $this->name       = $pc->name;
        $this->sort_order = (string) $pc->sort_order;
        $this->is_default = $pc->is_default;

        $this->showModal = true;
    }

    public function save(): void
    {
        $this->validate();

        $data = [
            'name'       => $this->name,
            'sort_order' => (int) $this->sort_order,
            'is_default' => $this->is_default,
        ];

        if ($this->is_default) {
            RecipePriceClass::where('is_default', true)->update(['is_default' => false]);
        }

        if ($this->editingId) {
            RecipePriceClass::findOrFail($this->editingId)->update($data);
            session()->flash('success', 'Price class updated.');
        } else {
            $data['company_id'] = Auth::user()->company_id;
            RecipePriceClass::create($data);
            session()->flash('success', 'Price class created.');
        }

        $this->closeModal();
    }

    public function delete(int $id): void
    {
        $pc = RecipePriceClass::findOrFail($id);

        $usedCount = RecipePrice::where('recipe_price_class_id', $pc->id)->count();
        if ($usedCount > 0) {
            session()->flash('error', "Cannot delete \"{$pc->name}\" — it has {$usedCount} recipe " . ($usedCount === 1 ? 'price' : 'prices') . ' assigned.');
            return;
        }

        $pc->delete();
        session()->flash('success', 'Price class deleted.');
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    public function render()
    {
        $priceClasses = RecipePriceClass::ordered()->get();

        $usage = RecipePrice::selectRaw('recipe_price_class_id, count(*) as total')
            ->groupBy('recipe_price_class_id')
            ->pluck('total', 'recipe_price_class_id');

        return view('livewire.settings.price-classes', compact('priceClasses', 'usage'))
            ->layout(\App\Helpers\WorkspaceLayout::get(), ['title' => 'Price Classes']);
    }

    private function resetForm(): void
    {
        $this->editingId  = null;
        $this->name       = '';
        $this->sort_order = '0';
        $this->is_default = false;
        $this->resetValidation();
    }
}
