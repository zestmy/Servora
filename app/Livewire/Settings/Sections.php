<?php

namespace App\Livewire\Settings;

use App\Models\Employee;
use App\Models\Section;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Sections extends Component
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
        $section = Section::findOrFail($id);

        $this->editingId  = $section->id;
        $this->name       = $section->name;
        $this->sort_order = (string) $section->sort_order;
        $this->is_active  = $section->is_active;

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
            Section::findOrFail($this->editingId)->update($data);
            session()->flash('success', 'Section updated.');
        } else {
            $data['company_id'] = Auth::user()->company_id;
            Section::create($data);
            session()->flash('success', 'Section created.');
        }

        $this->closeModal();
    }

    public function delete(int $id): void
    {
        $section = Section::findOrFail($id);

        $usedCount = Employee::where('section_id', $section->id)->count();
        if ($usedCount > 0) {
            session()->flash('error', "Cannot delete \"{$section->name}\" — it is assigned to {$usedCount} " . ($usedCount === 1 ? 'employee' : 'employees') . '.');
            return;
        }

        $section->delete();
        session()->flash('success', 'Section deleted.');
    }

    public function toggleActive(int $id): void
    {
        $section = Section::findOrFail($id);
        $section->update(['is_active' => ! $section->is_active]);
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    public function render()
    {
        $sections = Section::ordered()->get();

        $usage = Employee::selectRaw('section_id, count(*) as total')
            ->whereNotNull('section_id')
            ->groupBy('section_id')
            ->pluck('total', 'section_id');

        return view('livewire.settings.sections', compact('sections', 'usage'))
            ->layout(\App\Helpers\WorkspaceLayout::get(), ['title' => 'Sections']);
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
