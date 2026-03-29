<?php

namespace App\Livewire\Settings;

use App\Models\TaxRate;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class TaxRates extends Component
{
    public bool $showForm = false;
    public ?int $editId   = null;

    public string $country_code = '';
    public string $name         = '';
    public string $rate         = '';
    public bool   $is_inclusive  = false;
    public bool   $is_default   = false;
    public bool   $is_active    = true;

    protected function rules(): array
    {
        return [
            'country_code' => 'required|string|size:2',
            'name'         => 'required|string|max:50',
            'rate'         => 'required|numeric|min:0|max:100',
            'is_inclusive'  => 'boolean',
            'is_default'   => 'boolean',
            'is_active'    => 'boolean',
        ];
    }

    public function openCreate(): void
    {
        $this->resetForm();
        $company = Auth::user()->company;
        $this->country_code = $company?->default_tax_country ?? '';
        $this->showForm = true;
    }

    public function openEdit(int $id): void
    {
        $tax = TaxRate::findOrFail($id);
        $this->editId       = $tax->id;
        $this->country_code = $tax->country_code;
        $this->name         = $tax->name;
        $this->rate         = (string) $tax->rate;
        $this->is_inclusive  = $tax->is_inclusive;
        $this->is_default   = $tax->is_default;
        $this->is_active    = $tax->is_active;
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->validate();

        $companyId = Auth::user()->company_id;

        // If setting as default, unset other defaults for same country
        if ($this->is_default) {
            TaxRate::where('company_id', $companyId)
                ->where('country_code', strtoupper($this->country_code))
                ->where('is_default', true)
                ->when($this->editId, fn ($q) => $q->where('id', '!=', $this->editId))
                ->update(['is_default' => false]);
        }

        $data = [
            'company_id'   => $companyId,
            'country_code' => strtoupper($this->country_code),
            'name'         => $this->name,
            'rate'         => $this->rate,
            'is_inclusive'  => $this->is_inclusive,
            'is_default'   => $this->is_default,
            'is_active'    => $this->is_active,
        ];

        if ($this->editId) {
            TaxRate::findOrFail($this->editId)->update($data);
        } else {
            TaxRate::create($data);
        }

        $this->showForm = false;
        $this->resetForm();
        session()->flash('success', $this->editId ? 'Tax rate updated.' : 'Tax rate created.');
    }

    public function delete(int $id): void
    {
        TaxRate::findOrFail($id)->delete();
        session()->flash('success', 'Tax rate deleted.');
    }

    private function resetForm(): void
    {
        $this->editId = null;
        $this->country_code = '';
        $this->name = '';
        $this->rate = '';
        $this->is_inclusive = false;
        $this->is_default = false;
        $this->is_active = true;
    }

    public function render()
    {
        $taxRates = TaxRate::orderBy('country_code')->orderBy('name')->get();

        return view('livewire.settings.tax-rates', compact('taxRates'))
            ->layout(\App\Helpers\WorkspaceLayout::get(), ['title' => 'Tax Rates']);
    }
}
