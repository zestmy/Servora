<?php

namespace App\Livewire\Settings;

use App\Models\DocumentFolder;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class DocumentFolders extends Component
{
    public bool $showModal = false;
    public ?int $editingId = null;

    public string $name                   = '';
    public string $description            = '';
    public string $google_drive_folder_id = '';
    public bool   $allow_upload           = false;
    public string $sort_order             = '0';
    public bool   $is_active              = true;

    protected function rules(): array
    {
        return [
            'name'                   => 'required|string|max:100',
            'description'            => 'nullable|string|max:500',
            'google_drive_folder_id' => 'required|string|max:100',
            'sort_order'             => 'required|integer|min:0|max:9999',
        ];
    }

    public function openCreate(): void
    {
        $this->resetForm();
        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        $folder = DocumentFolder::findOrFail($id);

        $this->editingId               = $folder->id;
        $this->name                    = $folder->name;
        $this->description             = $folder->description ?? '';
        $this->google_drive_folder_id  = $folder->google_drive_folder_id;
        $this->allow_upload            = $folder->allow_upload;
        $this->sort_order              = (string) $folder->sort_order;
        $this->is_active               = $folder->is_active;

        $this->showModal = true;
    }

    public function save(): void
    {
        $this->validate();

        $data = [
            'name'                   => $this->name,
            'description'            => $this->description ?: null,
            'google_drive_folder_id' => $this->google_drive_folder_id,
            'allow_upload'           => $this->allow_upload,
            'sort_order'             => (int) $this->sort_order,
            'is_active'              => $this->is_active,
        ];

        if ($this->editingId) {
            DocumentFolder::findOrFail($this->editingId)->update($data);
            session()->flash('success', 'Document folder updated.');
        } else {
            $data['company_id'] = Auth::user()->company_id;
            DocumentFolder::create($data);
            session()->flash('success', 'Document folder created.');
        }

        $this->closeModal();
    }

    public function delete(int $id): void
    {
        DocumentFolder::findOrFail($id)->delete();
        session()->flash('success', 'Document folder deleted.');
    }

    public function toggleActive(int $id): void
    {
        $folder = DocumentFolder::findOrFail($id);
        $folder->update(['is_active' => !$folder->is_active]);
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    public function render()
    {
        $folders = DocumentFolder::ordered()->get();

        return view('livewire.settings.document-folders', compact('folders'))
            ->layout('layouts.app', ['title' => 'Document Folders']);
    }

    private function resetForm(): void
    {
        $this->editingId               = null;
        $this->name                    = '';
        $this->description             = '';
        $this->google_drive_folder_id  = '';
        $this->allow_upload            = false;
        $this->sort_order              = '0';
        $this->is_active               = true;
        $this->resetValidation();
    }
}
