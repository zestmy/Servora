<?php

namespace App\Livewire\Hr;

use App\Models\DocumentFolder;
use App\Services\GoogleDriveService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithFileUploads;

class Documents extends Component
{
    use WithFileUploads;
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

    // Upload
    public $uploadFiles = [];
    public bool $isUploading = false;

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

    public function updatedUploadFiles(): void
    {
        $this->uploadFiles();
    }

    public function uploadFiles(): void
    {
        if (empty($this->uploadFiles) || !$this->currentFolderId) {
            return;
        }

        // Check permission
        if (!Auth::user()->hasPermissionTo('hr.documents.manage')) {
            session()->flash('error', 'You do not have permission to upload files.');
            $this->uploadFiles = [];
            return;
        }

        // Check if folder allows uploads
        $folder = $this->getActiveDocumentFolder();
        if (!$folder || !$folder->allow_upload) {
            session()->flash('error', 'Uploads are not allowed in this folder.');
            $this->uploadFiles = [];
            return;
        }

        $this->isUploading = true;
        $uploaded = 0;
        $failed = 0;

        foreach ($this->uploadFiles as $file) {
            try {
                $result = $this->driveService->uploadFile(
                    $this->currentFolderId,
                    $file->getRealPath(),
                    $file->getClientOriginalName(),
                    $file->getMimeType()
                );

                if ($result) {
                    $uploaded++;
                } else {
                    $failed++;
                }
            } catch (\Exception $e) {
                $failed++;
            }
        }

        $this->uploadFiles = [];
        $this->isUploading = false;

        // Clear cache to refresh file list
        $this->driveService->clearCache($this->currentFolderId);

        if ($uploaded > 0) {
            session()->flash('success', "{$uploaded} file(s) uploaded successfully." . ($failed > 0 ? " {$failed} failed." : ''));
        } else {
            session()->flash('error', 'Failed to upload files.');
        }
    }

    public function deleteFile(string $fileId): void
    {
        if (!Auth::user()->hasPermissionTo('hr.documents.manage')) {
            session()->flash('error', 'You do not have permission to delete files.');
            return;
        }

        if ($this->driveService->deleteFile($fileId)) {
            // Clear cache
            if ($this->currentFolderId) {
                $this->driveService->clearCache($this->currentFolderId);
            }
            session()->flash('success', 'File deleted.');
        } else {
            session()->flash('error', 'Failed to delete file.');
        }
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
        $canUpload = $canManage && $currentFolder && $currentFolder->allow_upload;

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
            'canUpload',
            'isConfigured',
            'files'
        ))->layout('layouts.app', ['title' => 'Documents']);
    }
}
