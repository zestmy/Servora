<?php

namespace App\Services;

use Google\Client;
use Google\Service\Drive;
use Google\Service\Drive\DriveFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class GoogleDriveService
{
    protected ?Client $client = null;
    protected ?Drive $service = null;

    /**
     * Initialize the Google Client with service account credentials.
     */
    public function __construct()
    {
        $credentialsPath = config('services.google.drive.credentials_path');

        if (!$credentialsPath || !file_exists($credentialsPath)) {
            return;
        }

        try {
            $this->client = new Client();
            $this->client->setAuthConfig($credentialsPath);
            // Full DRIVE scope for read/write access to shared folders
            $this->client->addScope(Drive::DRIVE);
            $this->client->setAccessType('offline');

            $this->service = new Drive($this->client);
        } catch (\Exception $e) {
            Log::error('GoogleDriveService init failed: ' . $e->getMessage());
        }
    }

    /**
     * Check if the service is properly configured.
     */
    public function isConfigured(): bool
    {
        return $this->service !== null;
    }

    /**
     * List files in a folder.
     */
    public function listFiles(string $folderId, ?string $query = null, int $pageSize = 100): array
    {
        if (!$this->service) {
            return [];
        }

        $cacheKey = "drive_files_{$folderId}_" . md5($query ?? '') . "_{$pageSize}";

        return Cache::remember($cacheKey, 300, function () use ($folderId, $query, $pageSize) {
            try {
                $q = "'{$folderId}' in parents and trashed = false";

                if ($query) {
                    $q .= " and name contains '{$query}'";
                }

                $response = $this->service->files->listFiles([
                    'q' => $q,
                    'pageSize' => $pageSize,
                    'fields' => 'files(id,name,mimeType,size,createdTime,modifiedTime,thumbnailLink,webViewLink,webContentLink,iconLink,parents)',
                    'orderBy' => 'folder,name',
                ]);

                return array_map(function ($file) {
                    return $this->formatFile($file);
                }, $response->getFiles());
            } catch (\Exception $e) {
                Log::error('GoogleDriveService listFiles failed: ' . $e->getMessage());
                return [];
            }
        });
    }

    /**
     * Get a single file's metadata.
     */
    public function getFile(string $fileId): ?array
    {
        if (!$this->service) {
            return null;
        }

        $cacheKey = "drive_file_{$fileId}";

        return Cache::remember($cacheKey, 300, function () use ($fileId) {
            try {
                $file = $this->service->files->get($fileId, [
                    'fields' => 'id,name,mimeType,size,createdTime,modifiedTime,thumbnailLink,webViewLink,webContentLink,iconLink,parents',
                ]);

                return $this->formatFile($file);
            } catch (\Exception $e) {
                Log::error('GoogleDriveService getFile failed: ' . $e->getMessage());
                return null;
            }
        });
    }

    /**
     * Get folder breadcrumb path.
     */
    public function getBreadcrumbs(string $folderId, string $rootFolderId): array
    {
        if (!$this->service) {
            return [];
        }

        $breadcrumbs = [];
        $currentId = $folderId;
        $maxDepth = 10; // Prevent infinite loops

        while ($currentId && $currentId !== $rootFolderId && $maxDepth > 0) {
            try {
                $file = $this->service->files->get($currentId, [
                    'fields' => 'id,name,parents',
                ]);

                array_unshift($breadcrumbs, [
                    'id' => $file->getId(),
                    'name' => $file->getName(),
                ]);

                $parents = $file->getParents();
                $currentId = $parents ? $parents[0] : null;
                $maxDepth--;
            } catch (\Exception $e) {
                break;
            }
        }

        return $breadcrumbs;
    }

    /**
     * Get the preview URL for a file.
     */
    public function getPreviewUrl(string $fileId, string $mimeType): string
    {
        // Google native formats can use direct preview
        if ($this->isGoogleFormat($mimeType)) {
            return "https://drive.google.com/file/d/{$fileId}/preview";
        }

        // PDFs and Office documents use Google Docs Viewer
        if ($this->isPreviewable($mimeType)) {
            return "https://drive.google.com/file/d/{$fileId}/preview";
        }

        // Images and videos can be embedded directly
        return "https://drive.google.com/uc?id={$fileId}";
    }

    /**
     * Get the download URL for a file.
     */
    public function getDownloadUrl(string $fileId): string
    {
        return "https://drive.google.com/uc?export=download&id={$fileId}";
    }

    /**
     * Get thumbnail URL for a file.
     */
    public function getThumbnailUrl(string $fileId, int $size = 200): string
    {
        return "https://drive.google.com/thumbnail?id={$fileId}&sz=s{$size}";
    }

    /**
     * Search files across a folder and subfolders.
     */
    public function search(string $folderId, string $query): array
    {
        if (!$this->service || !$query) {
            return [];
        }

        try {
            // Search within folder tree
            $response = $this->service->files->listFiles([
                'q' => "name contains '{$query}' and trashed = false",
                'pageSize' => 50,
                'fields' => 'files(id,name,mimeType,size,createdTime,modifiedTime,thumbnailLink,webViewLink,webContentLink,iconLink,parents)',
                'orderBy' => 'modifiedTime desc',
            ]);

            // Filter to only files within the root folder tree
            $files = [];
            foreach ($response->getFiles() as $file) {
                if ($this->isInFolder($file, $folderId)) {
                    $files[] = $this->formatFile($file);
                }
            }

            return $files;
        } catch (\Exception $e) {
            Log::error('GoogleDriveService search failed: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Clear cache for a folder.
     */
    public function clearCache(string $folderId): void
    {
        // Clear all possible cache keys for this folder
        Cache::forget("drive_files_{$folderId}_");
        Cache::forget("drive_files_{$folderId}__100");
        Cache::forget("drive_files_{$folderId}_" . md5('') . "_100");
    }

    /**
     * Clear all drive cache (useful for debugging).
     */
    public function clearAllCache(): void
    {
        // Clear using cache tags if available, otherwise this is a no-op
        // For file-based cache, we can't easily clear by prefix
        Cache::flush();
    }

    /**
     * Upload a file to Google Drive.
     */
    public function uploadFile(string $folderId, string $filePath, string $fileName, string $mimeType): ?array
    {
        if (!$this->service) {
            return null;
        }

        try {
            $fileMetadata = new DriveFile([
                'name' => $fileName,
                'parents' => [$folderId],
            ]);

            $content = file_get_contents($filePath);

            $file = $this->service->files->create($fileMetadata, [
                'data' => $content,
                'mimeType' => $mimeType,
                'uploadType' => 'multipart',
                'fields' => 'id,name,mimeType,size,createdTime,modifiedTime,thumbnailLink,webViewLink,webContentLink,iconLink,parents',
            ]);

            // Clear folder cache so new file appears
            $this->clearCache($folderId);

            return $this->formatFile($file);
        } catch (\Exception $e) {
            Log::error('GoogleDriveService uploadFile failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Delete a file from Google Drive.
     */
    public function deleteFile(string $fileId): bool
    {
        if (!$this->service) {
            return false;
        }

        try {
            $this->service->files->delete($fileId);
            Cache::forget("drive_file_{$fileId}");
            return true;
        } catch (\Exception $e) {
            Log::error('GoogleDriveService deleteFile failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Format a Drive file into a consistent array structure.
     */
    protected function formatFile(DriveFile $file): array
    {
        $mimeType = $file->getMimeType();
        $isFolder = $mimeType === 'application/vnd.google-apps.folder';

        return [
            'id' => $file->getId(),
            'name' => $file->getName(),
            'mimeType' => $mimeType,
            'size' => $file->getSize() ? (int) $file->getSize() : null,
            'createdTime' => $file->getCreatedTime(),
            'modifiedTime' => $file->getModifiedTime(),
            'thumbnailLink' => $file->getThumbnailLink(),
            'webViewLink' => $file->getWebViewLink(),
            'webContentLink' => $file->getWebContentLink(),
            'iconLink' => $file->getIconLink(),
            'isFolder' => $isFolder,
            'isImage' => $this->isImage($mimeType),
            'isVideo' => $this->isVideo($mimeType),
            'isAudio' => $this->isAudio($mimeType),
            'isPdf' => $mimeType === 'application/pdf',
            'isGoogleDoc' => $this->isGoogleFormat($mimeType),
            'isPresentation' => $this->isPresentation($mimeType),
            'isGoogleSlides' => $mimeType === 'application/vnd.google-apps.presentation',
            'isPreviewable' => $this->isPreviewable($mimeType),
            'extension' => $this->getExtension($file->getName()),
            'icon' => $this->getFileIcon($mimeType),
        ];
    }

    /**
     * Check if file is within a folder tree.
     */
    protected function isInFolder(DriveFile $file, string $targetFolderId): bool
    {
        $parents = $file->getParents();
        if (!$parents) {
            return false;
        }

        // Simple check - just see if immediate parent matches
        // For deep check, would need to traverse up the tree
        return in_array($targetFolderId, $parents);
    }

    protected function isImage(string $mimeType): bool
    {
        return str_starts_with($mimeType, 'image/');
    }

    protected function isVideo(string $mimeType): bool
    {
        return str_starts_with($mimeType, 'video/');
    }

    protected function isAudio(string $mimeType): bool
    {
        return str_starts_with($mimeType, 'audio/');
    }

    protected function isPresentation(string $mimeType): bool
    {
        return in_array($mimeType, [
            'application/vnd.google-apps.presentation',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        ]);
    }

    protected function isGoogleFormat(string $mimeType): bool
    {
        return str_starts_with($mimeType, 'application/vnd.google-apps.');
    }

    protected function isPreviewable(string $mimeType): bool
    {
        $previewable = [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'text/plain',
            'text/csv',
        ];

        return $this->isImage($mimeType)
            || $this->isVideo($mimeType)
            || $this->isGoogleFormat($mimeType)
            || in_array($mimeType, $previewable);
    }

    protected function getExtension(string $filename): string
    {
        return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    }

    protected function getFileIcon(string $mimeType): string
    {
        if ($mimeType === 'application/vnd.google-apps.folder') return 'folder';
        if ($this->isImage($mimeType)) return 'image';
        if ($this->isVideo($mimeType)) return 'video';
        if ($this->isAudio($mimeType)) return 'audio';
        if ($mimeType === 'application/pdf') return 'pdf';
        if (str_contains($mimeType, 'word') || str_contains($mimeType, 'document')) return 'doc';
        if (str_contains($mimeType, 'sheet') || str_contains($mimeType, 'excel')) return 'sheet';
        if (str_contains($mimeType, 'presentation') || str_contains($mimeType, 'powerpoint')) return 'slides';
        if (str_starts_with($mimeType, 'text/')) return 'text';
        return 'file';
    }
}
