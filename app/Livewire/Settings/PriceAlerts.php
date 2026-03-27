<?php

namespace App\Livewire\Settings;

use App\Models\Ingredient;
use App\Models\Supplier;
use App\Models\SupplierPriceAlert;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class PriceAlerts extends Component
{
    public bool $showForm = false;
    public ?int $editId = null;

    public ?int $ingredient_id = null;
    public ?int $supplier_id = null;
    public string $alert_type = 'increase';
    public string $threshold_percent = '';
    public string $threshold_amount = '';
    public bool $is_active = true;

    protected function rules(): array
    {
        return [
            'ingredient_id'    => 'required|exists:ingredients,id',
            'supplier_id'      => 'nullable|exists:suppliers,id',
            'alert_type'       => 'required|in:increase,decrease,threshold',
            'threshold_percent' => 'nullable|numeric|min:0|max:100',
            'threshold_amount'  => 'nullable|numeric|min:0',
        ];
    }

    public function openCreate(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function openEdit(int $id): void
    {
        $alert = SupplierPriceAlert::findOrFail($id);
        $this->editId = $alert->id;
        $this->ingredient_id = $alert->ingredient_id;
        $this->supplier_id = $alert->supplier_id;
        $this->alert_type = $alert->alert_type;
        $this->threshold_percent = (string) ($alert->threshold_percent ?? '');
        $this->threshold_amount = (string) ($alert->threshold_amount ?? '');
        $this->is_active = $alert->is_active;
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->validate();

        $data = [
            'company_id'        => Auth::user()->company_id,
            'ingredient_id'     => $this->ingredient_id,
            'supplier_id'       => $this->supplier_id,
            'alert_type'        => $this->alert_type,
            'threshold_percent' => $this->threshold_percent ?: null,
            'threshold_amount'  => $this->threshold_amount ?: null,
            'is_active'         => $this->is_active,
        ];

        if ($this->editId) {
            SupplierPriceAlert::findOrFail($this->editId)->update($data);
        } else {
            SupplierPriceAlert::create($data);
        }

        $this->showForm = false;
        $this->resetForm();
        session()->flash('success', $this->editId ? 'Alert updated.' : 'Alert created.');
    }

    public function toggleActive(int $id): void
    {
        $alert = SupplierPriceAlert::findOrFail($id);
        $alert->update(['is_active' => ! $alert->is_active]);
    }

    public function delete(int $id): void
    {
        SupplierPriceAlert::findOrFail($id)->delete();
        session()->flash('success', 'Alert deleted.');
    }

    private function resetForm(): void
    {
        $this->editId = null;
        $this->ingredient_id = null;
        $this->supplier_id = null;
        $this->alert_type = 'increase';
        $this->threshold_percent = '';
        $this->threshold_amount = '';
        $this->is_active = true;
    }

    public function render()
    {
        $alerts = SupplierPriceAlert::with(['ingredient', 'supplier'])
            ->orderByDesc('created_at')
            ->get();

        $ingredients = Ingredient::where('is_active', true)->orderBy('name')->get();
        $suppliers = Supplier::where('is_active', true)->orderBy('name')->get();

        return view('livewire.settings.price-alerts', compact('alerts', 'ingredients', 'suppliers'))
            ->layout('layouts.app', ['title' => 'Price Alerts']);
    }
}
