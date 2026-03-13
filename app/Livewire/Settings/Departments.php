<?php

namespace App\Livewire\Settings;

use App\Models\Department;
use App\Models\PurchaseOrder;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Departments extends Component
{
    public bool $showModal = false;
    public ?int $editingId = null;

    public string $name       = '';
    public string $sort_order = '0';
    public bool   $is_active  = true;

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
        $dept = Department::findOrFail($id);

        $this->editingId  = $dept->id;
        $this->name       = $dept->name;
        $this->sort_order = (string) $dept->sort_order;
        $this->is_active  = $dept->is_active;

        $this->showModal = true;
    }

    public function save(): void
    {
        $this->validate();

        $data = [
            'name'       => $this->name,
            'sort_order' => (int) $this->sort_order,
            'is_active'  => $this->is_active,
        ];

        if ($this->editingId) {
            Department::findOrFail($this->editingId)->update($data);
            session()->flash('success', 'Department updated.');
        } else {
            $data['company_id'] = Auth::user()->company_id;
            Department::create($data);
            session()->flash('success', 'Department created.');
        }

        $this->closeModal();
    }

    public function delete(int $id): void
    {
        $dept = Department::findOrFail($id);

        $usedCount = PurchaseOrder::where('department_id', $dept->id)->count();
        if ($usedCount > 0) {
            session()->flash('error', "Cannot delete \"{$dept->name}\" — it is used by {$usedCount} purchase " . ($usedCount === 1 ? 'order' : 'orders') . '.');
            return;
        }

        $dept->delete();
        session()->flash('success', 'Department deleted.');
    }

    public function toggleActive(int $id): void
    {
        $dept = Department::findOrFail($id);
        $dept->update(['is_active' => ! $dept->is_active]);
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    public function render()
    {
        $departments = Department::ordered()->get();

        $usage = PurchaseOrder::selectRaw('department_id, count(*) as total')
            ->whereNotNull('department_id')
            ->groupBy('department_id')
            ->pluck('total', 'department_id');

        return view('livewire.settings.departments', compact('departments', 'usage'))
            ->layout('layouts.app', ['title' => 'Departments']);
    }

    private function resetForm(): void
    {
        $this->editingId  = null;
        $this->name       = '';
        $this->sort_order = '0';
        $this->is_active  = true;
        $this->resetValidation();
    }
}
