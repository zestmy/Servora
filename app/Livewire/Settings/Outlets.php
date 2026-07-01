<?php

namespace App\Livewire\Settings;

use App\Models\CentralKitchen;
use App\Models\CentralPurchasingUnit;
use App\Models\Outlet;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Component;

class Outlets extends Component
{
    public bool   $showModal = false;
    public ?int   $editingId = null;

    public string $name    = '';
    public string $code    = '';
    public string $phone   = '';
    public string $address = '';
    public string $country = '';
    public string $state   = '';
    public bool   $is_active = true;
    public ?int   $default_kitchen_id = null;
    public ?int   $default_cpu_id     = null;

    public function openCreate(): void
    {
        $this->resetForm();
        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        $outlet = Outlet::where('company_id', Auth::user()->company_id)->findOrFail($id);

        $this->editingId = $outlet->id;
        $this->name      = $outlet->name;
        $this->code      = $outlet->code ?? '';
        $this->phone     = $outlet->phone ?? '';
        $this->address   = $outlet->address ?? '';
        $this->country   = $outlet->country ?? '';
        $this->state     = $outlet->state ?? '';
        $this->is_active = $outlet->is_active;
        $this->default_kitchen_id = $outlet->default_kitchen_id;
        $this->default_cpu_id     = $outlet->default_cpu_id;
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    public function save(): void
    {
        $companyId = Auth::user()->company_id;

        $this->validate([
            'name'    => 'required|string|max:100',
            'code'    => ['required', 'string', 'max:20', Rule::unique('outlets', 'code')->where('company_id', $companyId)->ignore($this->editingId)],
            'phone'   => 'nullable|string|max:30',
            'address' => 'nullable|string|max:500',
            'country' => 'nullable|string|max:100',
            'state'   => 'nullable|string|max:100',
            'default_kitchen_id' => ['nullable', Rule::exists('central_kitchens', 'id')->where('company_id', $companyId)->whereNull('deleted_at')],
            'default_cpu_id'     => ['nullable', Rule::exists('central_purchasing_units', 'id')->where('company_id', $companyId)->whereNull('deleted_at')],
        ]);

        $data = [
            'company_id' => $companyId,
            'name'       => $this->name,
            'code'       => strtoupper($this->code),
            'phone'      => $this->phone ?: null,
            'address'    => $this->address ?: null,
            'country'    => $this->country ?: null,
            'state'      => $this->state ?: null,
            'is_active'  => $this->is_active,
            'default_kitchen_id' => $this->default_kitchen_id ?: null,
            'default_cpu_id'     => $this->default_cpu_id ?: null,
        ];

        if ($this->editingId) {
            $outlet = Outlet::where('company_id', $companyId)->findOrFail($this->editingId);
            $outlet->update($data);
            session()->flash('success', 'Branch updated.');
        } else {
            Outlet::create($data);
            session()->flash('success', 'Branch created.');
        }

        $this->closeModal();
    }

    public function toggleActive(int $id): void
    {
        $outlet = Outlet::where('company_id', Auth::user()->company_id)->findOrFail($id);
        $outlet->update(['is_active' => ! $outlet->is_active]);
    }

    public function delete(int $id): void
    {
        $outlet = Outlet::where('company_id', Auth::user()->company_id)->findOrFail($id);

        if ($outlet->users()->count() > 0) {
            session()->flash('error', 'Cannot delete — this branch still has assigned users.');
            return;
        }

        $outlet->delete();
        session()->flash('success', 'Branch deleted.');
    }

    public function render()
    {
        $outlets = Outlet::where('company_id', Auth::user()->company_id)
            ->withCount('users')
            ->with(['defaultKitchen:id,name', 'defaultCpu:id,name'])
            ->orderBy('name')
            ->get();

        // Facility options for routing assignment (CompanyScope keeps these tenant-safe).
        $kitchens = CentralKitchen::where('is_active', true)->orderBy('name')->get(['id', 'name']);
        $cpus     = CentralPurchasingUnit::where('is_active', true)->orderBy('name')->get(['id', 'name']);
        $cpuMode  = Auth::user()->company?->ordering_mode === 'cpu';

        return view('livewire.settings.outlets', compact('outlets', 'kitchens', 'cpus', 'cpuMode'))
            ->layout(\App\Helpers\WorkspaceLayout::get(), ['title' => 'Branches']);
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->name      = '';
        $this->code      = '';
        $this->phone     = '';
        $this->address   = '';
        $this->country   = '';
        $this->state     = '';
        $this->is_active = true;
        $this->default_kitchen_id = null;
        $this->default_cpu_id     = null;
        $this->resetValidation();
    }
}
