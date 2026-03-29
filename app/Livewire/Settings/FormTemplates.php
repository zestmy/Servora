<?php

namespace App\Livewire\Settings;

use App\Models\FormTemplate;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class FormTemplates extends Component
{
    public string $typeFilter  = '';
    public bool   $showModal   = false;
    public ?int   $editingId   = null;

    public string $name        = '';
    public string $form_type   = '';
    public string $description = '';
    public bool   $is_active   = true;
    public string $sort_order  = '0';

    protected function rules(): array
    {
        return [
            'name'      => 'required|string|max:100',
            'form_type' => ['required', 'string', 'in:stock_take,purchase_order,wastage'],
            'sort_order'=> 'required|integer|min:0|max:9999',
        ];
    }

    public function openCreate(): void
    {
        $this->resetForm();
        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        $t = FormTemplate::findOrFail($id);

        $this->editingId   = $t->id;
        $this->name        = $t->name;
        $this->form_type   = $t->form_type;
        $this->description = $t->description ?? '';
        $this->is_active   = $t->is_active;
        $this->sort_order  = (string) $t->sort_order;

        $this->showModal = true;
    }

    public function save(): void
    {
        $this->validate();

        $data = [
            'name'        => $this->name,
            'form_type'   => $this->form_type,
            'description' => $this->description ?: null,
            'is_active'   => $this->is_active,
            'sort_order'  => (int) $this->sort_order,
        ];

        if ($this->editingId) {
            FormTemplate::findOrFail($this->editingId)->update($data);
            session()->flash('success', 'Template updated.');
            $this->closeModal();
        } else {
            $data['company_id'] = Auth::user()->company_id;
            $t = FormTemplate::create($data);
            session()->flash('success', 'Template created. Now add items to it.');
            $this->redirectRoute('settings.form-templates.edit', $t->id);
        }
    }

    public function delete(int $id): void
    {
        $t = FormTemplate::withCount('lines')->findOrFail($id);

        if ($t->lines_count > 0) {
            session()->flash('error', "Cannot delete \"{$t->name}\" — it has {$t->lines_count} item(s). Remove items first.");
            return;
        }

        $t->delete();
        session()->flash('success', 'Template deleted.');
    }

    public function toggleActive(int $id): void
    {
        $t = FormTemplate::findOrFail($id);
        $t->update(['is_active' => ! $t->is_active]);
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    public function render()
    {
        $templates = FormTemplate::withCount('lines')
            ->when($this->typeFilter, fn ($q) => $q->where('form_type', $this->typeFilter))
            ->ordered()
            ->get();

        $typeOptions = FormTemplate::formTypeOptions();

        return view('livewire.settings.form-templates', compact('templates', 'typeOptions'))
            ->layout(\App\Helpers\WorkspaceLayout::get(), ['title' => 'Form Templates']);
    }

    private function resetForm(): void
    {
        $this->editingId   = null;
        $this->name        = '';
        $this->form_type   = $this->typeFilter ?: '';
        $this->description = '';
        $this->is_active   = true;
        $this->sort_order  = '0';
        $this->resetValidation();
    }
}
