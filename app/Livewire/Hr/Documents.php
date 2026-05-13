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
    public int $previewIndex = 0;
    public array $previewableFiles = [];

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
        // Get all files and filter to only previewable (non-folder) files
        $allFiles = $this->currentFolderId
            ? $this->driveService->listFiles($this->currentFolderId)
            : [];

        $this->previewableFiles = array_values(array_filter($allFiles, fn($f) => !$f['isFolder']));

        // Find the index of the clicked file
        $this->previewIndex = 0;
        foreach ($this->previewableFiles as $index => $file) {
            if ($file['id'] === $fileId) {
                $this->previewIndex = $index;
                break;
            }
        }

        if (!empty($this->previewableFiles)) {
            $this->previewFile = $this->previewableFiles[$this->previewIndex];
            $this->showPreview = true;
        }
    }

    public function prevFile(): void
    {
        if (empty($this->previewableFiles)) return;

        $this->previewIndex = $this->previewIndex > 0
            ? $this->previewIndex - 1
            : count($this->previewableFiles) - 1;

        $this->previewFile = $this->previewableFiles[$this->previewIndex];
    }

    public function nextFile(): void
    {
        if (empty($this->previewableFiles)) return;

        $this->previewIndex = $this->previewIndex < count($this->previewableFiles) - 1
            ? $this->previewIndex + 1
            : 0;

        $this->previewFile = $this->previewableFiles[$this->previewIndex];
    }

    public function closePreview(): void
    {
        $this->showPreview = false;
        $this->previewFile = null;
        $this->previewableFiles = [];
        $this->previewIndex = 0;
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
