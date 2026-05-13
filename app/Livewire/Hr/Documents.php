<?php

namespace App\Livewire\Hr;

use App\Models\DocumentFolder;
use App\Services\GoogleDriveService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Documents extends Component
{
    public ?int $activeFolder = null;
    public ?string $currentFolderId = null;
    public string $searchQuery = '';
    public string $viewMode = 'grid';
    public array $breadcrumbs = [];

    // Preview modal
    public bool $showPreview = false;
    public ?array $previewFile = null;

    protected GoogleDriveService $driveService;

    public function boot(GoogleDriveService $driveService): void
    {
        $this->driveService = $driveService;
    }

    public function mount(): void
    {
        // Restore view mode preference
        $this->viewMode = session('documents_view_mode', 'grid');

        // Default to first active folder
        $first = DocumentFolder::active()->ordered()->first();
        if ($first) {
            $this->activeFolder = $first->id;
            $this->currentFolderId = $first->google_drive_folder_id;
        }
    }

    public function setActiveFolder(int $id): void
    {
        $folder = DocumentFolder::find($id);
        if (!$folder) return;

        $this->activeFolder = $id;
        $this->currentFolderId = $folder->google_drive_folder_id;
        $this->breadcrumbs = [];
        $this->searchQuery = '';
    }

    public function navigateToFolder(string $folderId): void
    {
        $folder = $this->getActiveDocumentFolder();
        if (!$folder) return;

        // Update breadcrumbs
        $this->breadcrumbs = $this->driveService->getBreadcrumbs($folderId, $folder->google_drive_folder_id);
        $this->currentFolderId = $folderId;
        $this->searchQuery = '';
    }

    public function navigateToBreadcrumb(int $index): void
    {
        if (!isset($this->breadcrumbs[$index])) return;

        $targetId = $this->breadcrumbs[$index]['id'];
        $this->breadcrumbs = array_slice($this->breadcrumbs, 0, $index + 1);
        $this->currentFolderId = $targetId;
    }

    public function navigateToRoot(): void
    {
        $folder = $this->getActiveDocumentFolder();
        if (!$folder) return;

        $this->currentFolderId = $folder->google_drive_folder_id;
        $this->breadcrumbs = [];
    }

    public function search(): void
    {
        // Search is triggered by wire:model.live
    }

    public function clearSearch(): void
    {
        $this->searchQuery = '';
    }

    public function toggleViewMode(): void
    {
        $this->viewMode = $this->viewMode === 'grid' ? 'list' : 'grid';
        session(['documents_view_mode' => $this->viewMode]);
    }

    public function openPreview(string $fileId): void
    {
        $file = $this->driveService->getFile($fileId);
        if ($file && !$file['isFolder']) {
            $this->previewFile = $file;
            $this->showPreview = true;
        }
    }

    public function closePreview(): void
    {
        $this->showPreview = false;
        $this->previewFile = null;
    }

    public function getPreviewUrl(): string
    {
        if (!$this->previewFile) return '';
        return $this->driveService->getPreviewUrl($this->previewFile['id'], $this->previewFile['mimeType']);
    }

    public function getDownloadUrl(string $fileId): string
    {
        return $this->driveService->getDownloadUrl($fileId);
    }

    protected function getActiveDocumentFolder(): ?DocumentFolder
    {
        return $this->activeFolder ? DocumentFolder::find($this->activeFolder) : null;
    }

    public function render()
    {
        $folders = DocumentFolder::active()->ordered()->get();
        $currentFolder = $this->getActiveDocumentFolder();
        $canManage = Auth::user()->hasPermissionTo('hr.documents.manage');
        $isConfigured = $this->driveService->isConfigured();

        $files = [];
        if ($isConfigured && $this->currentFolderId) {
            if ($this->searchQuery) {
                $files = $this->driveService->search($this->currentFolderId, $this->searchQuery);
            } else {
                $files = $this->driveService->listFiles($this->currentFolderId);
            }
        }

        return view('livewire.hr.documents', compact(
            'folders',
            'currentFolder',
            'canManage',
            'isConfigured',
            'files'
        ))->layout('layouts.app', ['title' => 'Documents']);
    }
}
