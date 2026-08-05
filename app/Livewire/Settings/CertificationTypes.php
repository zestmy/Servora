<?php

namespace App\Livewire\Settings;

use App\Models\CertificationType;
use App\Models\EmployeeCertification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * The company's catalogue of certifications and training courses.
 *
 * Adding a course here makes it selectable on the employee form and, if it
 * expires, puts it in the Employees compliance card and the reminder email —
 * no code change per course.
 */
class CertificationTypes extends Component
{
    public bool $showModal = false;
    public ?int $editingId = null;

    public string $name        = '';
    public string $description = '';
    public bool   $has_expiry  = true;
    public bool   $is_required = false;
    public string $sort_order  = '0';
    public bool   $is_active   = true;

    protected function rules(): array
    {
        return [
            // Unique per company: two courses with the same name would be
            // indistinguishable in the employee form's picker.
            'name' => [
                'required', 'string', 'max:120',
                Rule::unique('certification_types', 'name')
                    ->where('company_id', Auth::user()->company_id)
                    ->ignore($this->editingId),
            ],
            'description' => 'nullable|string|max:1000',
            'has_expiry'  => 'boolean',
            'is_required' => 'boolean',
            'sort_order'  => 'required|integer|min:0|max:9999',
        ];
    }

    protected function messages(): array
    {
        return [
            'name.unique' => 'A certification with this name already exists.',
        ];
    }

    public function openCreate(): void
    {
        $this->resetForm();
        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        $type = CertificationType::findOrFail($id);

        $this->editingId   = $type->id;
        $this->name        = $type->name;
        $this->description = $type->description ?? '';
        $this->has_expiry  = $type->has_expiry;
        $this->is_required = $type->is_required;
        $this->sort_order  = (string) $type->sort_order;
        $this->is_active   = $type->is_active;

        $this->showModal = true;
    }

    public function save(): void
    {
        $this->validate();

        $data = [
            'name'        => $this->name,
            'description' => $this->description ?: null,
            'has_expiry'  => $this->has_expiry,
            'is_required' => $this->is_required,
            'sort_order'  => (int) $this->sort_order,
            'is_active'   => $this->is_active,
        ];

        if ($this->editingId) {
            CertificationType::findOrFail($this->editingId)->update($data);
            session()->flash('success', 'Certification updated.');
        } else {
            $data['company_id'] = Auth::user()->company_id;
            CertificationType::create($data);
            session()->flash('success', 'Certification added.');
        }

        $this->closeModal();
    }

    public function delete(int $id): void
    {
        $type = CertificationType::findOrFail($id);

        // Deleting would take every employee's record of the course with it,
        // which is training history. Deactivating hides it from the picker
        // while leaving what staff already hold intact.
        $usedCount = EmployeeCertification::where('certification_type_id', $type->id)->count();
        if ($usedCount > 0) {
            session()->flash('error', "Cannot delete \"{$type->name}\" — it is recorded against {$usedCount} " . ($usedCount === 1 ? 'employee' : 'employees') . '. Deactivate it instead.');
            return;
        }

        $type->delete();
        session()->flash('success', 'Certification deleted.');
    }

    public function toggleActive(int $id): void
    {
        $type = CertificationType::findOrFail($id);
        $type->update(['is_active' => ! $type->is_active]);
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    public function render()
    {
        $types = CertificationType::ordered()->get();

        $usage = EmployeeCertification::selectRaw('certification_type_id, count(*) as total')
            ->groupBy('certification_type_id')
            ->pluck('total', 'certification_type_id');

        return view('livewire.settings.certification-types', compact('types', 'usage'))
            ->layout(\App\Helpers\WorkspaceLayout::get(), ['title' => 'Certifications & Training']);
    }

    private function resetForm(): void
    {
        $this->editingId   = null;
        $this->name        = '';
        $this->description = '';
        $this->has_expiry  = true;
        $this->is_required = false;
        $this->sort_order  = '0';
        $this->is_active   = true;
        $this->resetValidation();
    }
}
