<?php

namespace App\Livewire\Settings;

use App\Models\Supplier;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class Suppliers extends Component
{
    use WithPagination;

    public string $search = '';
    public string $statusFilter = 'all';

    public bool $showModal = false;
    public ?int $editingId = null;

    public string $name = '';
    public string $code = '';
    public string $contact_person = '';
    public string $email = '';
    public string $phone = '';
    public string $address = '';
    public string $payment_terms = '';
    public bool $is_active = true;

    protected function rules(): array
    {
        return [
            'name'           => 'required|string|max:255',
            'code'           => 'nullable|string|max:50',
            'contact_person' => 'nullable|string|max:255',
            'email'          => 'nullable|email|max:255',
            'phone'          => 'nullable|string|max:50',
            'address'        => 'nullable|string|max:500',
            'payment_terms'  => 'nullable|string|max:100',
        ];
    }

    public function updatedSearch(): void      { $this->resetPage(); }
    public function updatedStatusFilter(): void { $this->resetPage(); }

    public function openCreate(): void
    {
        $this->resetForm();
        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        $supplier = Supplier::findOrFail($id);

        $this->editingId      = $supplier->id;
        $this->name           = $supplier->name;
        $this->code           = $supplier->code ?? '';
        $this->contact_person = $supplier->contact_person ?? '';
        $this->email          = $supplier->email ?? '';
        $this->phone          = $supplier->phone ?? '';
        $this->address        = $supplier->address ?? '';
        $this->payment_terms  = $supplier->payment_terms ?? '';
        $this->is_active      = $supplier->is_active;

        $this->showModal = true;
    }

    public function save(): void
    {
        $this->validate();

        $data = [
            'name'           => $this->name,
            'code'           => $this->code ?: null,
            'contact_person' => $this->contact_person ?: null,
            'email'          => $this->email ?: null,
            'phone'          => $this->phone ?: null,
            'address'        => $this->address ?: null,
            'payment_terms'  => $this->payment_terms ?: null,
            'is_active'      => $this->is_active,
        ];

        if ($this->editingId) {
            Supplier::findOrFail($this->editingId)->update($data);
            session()->flash('success', 'Supplier updated.');
        } else {
            $data['company_id'] = Auth::user()->company_id;
            Supplier::create($data);
            session()->flash('success', 'Supplier created.');
        }

        $this->closeModal();
    }

    public function delete(int $id): void
    {
        Supplier::findOrFail($id)->delete();
        session()->flash('success', 'Supplier deleted.');
    }

    public function toggleActive(int $id): void
    {
        $s = Supplier::findOrFail($id);
        $s->update(['is_active' => ! $s->is_active]);
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    public function render()
    {
        $query = Supplier::withCount('ingredients');

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                  ->orWhere('code', 'like', '%' . $this->search . '%')
                  ->orWhere('contact_person', 'like', '%' . $this->search . '%');
            });
        }

        if ($this->statusFilter === 'active') {
            $query->where('is_active', true);
        } elseif ($this->statusFilter === 'inactive') {
            $query->where('is_active', false);
        }

        $suppliers = $query->orderBy('name')->paginate(15);

        return view('livewire.settings.suppliers', compact('suppliers'))
            ->layout(\App\Helpers\WorkspaceLayout::get(), ['title' => 'Suppliers']);
    }

    private function resetForm(): void
    {
        $this->editingId      = null;
        $this->name           = '';
        $this->code           = '';
        $this->contact_person = '';
        $this->email          = '';
        $this->phone          = '';
        $this->address        = '';
        $this->payment_terms  = '';
        $this->is_active      = true;
        $this->resetValidation();
    }
}
