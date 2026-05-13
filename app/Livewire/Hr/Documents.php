<?php

namespace App\Livewire\Hr;

use App\Models\DocumentFolder;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Documents extends Component
{
    public ?int $activeFolder = null;

    public function mount(): void
    {
        // Default to first active folder
        $first = DocumentFolder::active()->ordered()->first();
        $this->activeFolder = $first?->id;
    }

    public function setActiveFolder(int $id): void
    {
        $this->activeFolder = $id;
    }

    public function render()
    {
        $folders = DocumentFolder::active()->ordered()->get();
        $currentFolder = $this->activeFolder
            ? $folders->firstWhere('id', $this->activeFolder)
            : $folders->first();

        $canManage = Auth::user()->hasPermissionTo('hr.documents.manage');

        return view('livewire.hr.documents', compact('folders', 'currentFolder', 'canManage'))
            ->layout('layouts.app', ['title' => 'Documents']);
    }
}
