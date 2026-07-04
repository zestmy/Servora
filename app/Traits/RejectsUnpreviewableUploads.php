<?php

namespace App\Traits;

/**
 * Strips uploads Livewire can't preview (e.g. iPhone HEIC photos) as soon as
 * they land on a component property. Without this, the very next render calls
 * temporaryUrl() on the file and throws FileNotPreviewableException — a 500 —
 * before the mimes: validation rule ever gets a chance to reject it politely.
 */
trait RejectsUnpreviewableUploads
{
    /** Extensions browsers can render in an <img> tag. */
    protected array $previewableImageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    /**
     * Filter a multi-upload array, keeping only previewable files.
     * Rejected files produce a validation error under "$errorKey.{i}" so
     * wildcard error displays ($errors->get("$errorKey.*")) pick them up.
     */
    protected function keepPreviewableUploads(array $files, string $errorKey, ?array $allowed = null): array
    {
        $allowed ??= $this->previewableImageExtensions;
        $kept = [];

        foreach ($files as $i => $file) {
            if (! is_object($file)) continue;

            if (in_array(strtolower($file->getClientOriginalExtension()), $allowed, true)) {
                $kept[] = $file;
            } else {
                $this->addError("{$errorKey}.{$i}", $this->unpreviewableMessage($file, $allowed));
            }
        }

        return $kept;
    }

    /** Single-file variant; returns the file or null when rejected. */
    protected function keepPreviewableUpload($file, string $errorKey, ?array $allowed = null)
    {
        if (! is_object($file)) return null;

        $allowed ??= $this->previewableImageExtensions;

        if (in_array(strtolower($file->getClientOriginalExtension()), $allowed, true)) {
            return $file;
        }

        $this->addError($errorKey, $this->unpreviewableMessage($file, $allowed));

        return null;
    }

    private function unpreviewableMessage($file, array $allowed): string
    {
        $ext  = strtolower($file->getClientOriginalExtension());
        $name = $file->getClientOriginalName();
        $list = strtoupper(implode(', ', $allowed));

        $hint = in_array($ext, ['heic', 'heif'], true)
            ? ' iPhone tip: set Settings → Camera → Formats → Most Compatible, or share the photo as JPEG.'
            : '';

        return "\"{$name}\" is a .{$ext} file that can't be displayed here. Please upload {$list}.{$hint}";
    }
}
