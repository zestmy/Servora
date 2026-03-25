<?php

namespace App\Livewire\Admin;

use App\Models\Announcement;
use Livewire\Component;

class Announcements extends Component
{
    public bool $showModal = false;
    public ?int $editingId = null;

    public string $title     = '';
    public string $body      = '';
    public string $type      = 'info';
    public bool   $is_active = true;
    public string $starts_at = '';
    public string $ends_at   = '';

    protected function rules(): array
    {
        return [
            'title'     => 'required|string|max:200',
            'body'      => 'required|string|max:2000',
            'type'      => 'required|in:info,warning,success,promo',
            'starts_at' => 'nullable|date',
            'ends_at'   => 'nullable|date|after_or_equal:starts_at',
        ];
    }

    public function openCreate(): void
    {
        $this->resetForm();
        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        $ann = Announcement::findOrFail($id);
        $this->editingId = $ann->id;
        $this->title     = $ann->title;
        $this->body      = $ann->body;
        $this->type      = $ann->type;
        $this->is_active = $ann->is_active;
        $this->starts_at = $ann->starts_at?->format('Y-m-d\TH:i') ?? '';
        $this->ends_at   = $ann->ends_at?->format('Y-m-d\TH:i') ?? '';
        $this->showModal = true;
    }

    public function save(): void
    {
        $this->validate();

        $data = [
            'title'     => $this->title,
            'body'      => $this->body,
            'type'      => $this->type,
            'is_active' => $this->is_active,
            'starts_at' => $this->starts_at ?: null,
            'ends_at'   => $this->ends_at ?: null,
        ];

        if ($this->editingId) {
            Announcement::findOrFail($this->editingId)->update($data);
            session()->flash('success', 'Announcement updated.');
        } else {
            Announcement::create($data);
            session()->flash('success', 'Announcement created.');
        }

        $this->closeModal();
    }

    public function toggleActive(int $id): void
    {
        $ann = Announcement::findOrFail($id);
        $ann->update(['is_active' => !$ann->is_active]);
    }

    public function delete(int $id): void
    {
        Announcement::findOrFail($id)->delete();
        session()->flash('success', 'Announcement deleted.');
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->title = '';
        $this->body = '';
        $this->type = 'info';
        $this->is_active = true;
        $this->starts_at = '';
        $this->ends_at = '';
        $this->resetValidation();
    }

    public function render()
    {
        $announcements = Announcement::latest()->get();

        return view('livewire.admin.announcements', compact('announcements'))
            ->layout('layouts.app', ['title' => 'Announcements']);
    }
}
