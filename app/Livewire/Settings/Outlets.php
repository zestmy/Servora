<?php

namespace App\Livewire\Settings;

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
    public bool   $is_active = true;

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
        $this->is_active = $outlet->is_active;
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
        ]);

        $data = [
            'company_id' => $companyId,
            'name'       => $this->name,
            'code'       => strtoupper($this->code),
            'phone'      => $this->phone ?: null,
            'address'    => $this->address ?: null,
            'is_active'  => $this->is_active,
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
            ->orderBy('name')
            ->get();

        return view('livewire.settings.outlets', compact('outlets'))
            ->layout(\App\Helpers\WorkspaceLayout::get(), ['title' => 'Branches']);
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->name      = '';
        $this->code      = '';
        $this->phone     = '';
        $this->address   = '';
        $this->is_active = true;
        $this->resetValidation();
    }
}
